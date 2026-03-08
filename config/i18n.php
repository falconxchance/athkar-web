<?php
require_once __DIR__ . '/db.php';

function sanitize_lang(?string $lang): string
{
    $lang = strtolower(trim((string)$lang));
    if ($lang === '') return 'en';
    $lang = explode('-', $lang)[0];
    if (!preg_match('/^[a-z]{2,8}$/', $lang)) return 'en';
    return $lang;
}

function default_lang_meta(string $code): array
{
    $rtlCodes = ['ar','fa','ur','he'];
    return [
        'code' => $code,
        'label' => strtoupper($code),
        'native_label' => strtoupper($code),
        'dir' => in_array($code, $rtlCodes, true) ? 'rtl' : 'ltr',
        'is_active' => 1,
        'display_order' => 999,
    ];
}

function get_languages(PDO $pdo, bool $onlyActive = false): array
{
    try {
        $has = (bool)$pdo->query("SHOW TABLES LIKE 'app_languages'")->fetchColumn();
        if ($has) {
            $sql = "SELECT code, label, native_label, COALESCE(dir, CASE WHEN code = 'ar' THEN 'rtl' ELSE 'ltr' END) AS dir, is_active, display_order FROM app_languages";
            if ($onlyActive) $sql .= ' WHERE is_active = 1';
            $sql .= ' ORDER BY display_order ASC, code ASC';
            $rows = $pdo->query($sql)->fetchAll();
            if (is_array($rows) && $rows) return $rows;
        }
    } catch (Throwable $e) {
    }

    $fallback = [
        ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'dir' => 'ltr', 'is_active' => 1, 'display_order' => 1],
        ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'العربية', 'dir' => 'rtl', 'is_active' => 1, 'display_order' => 2],
    ];
    return $onlyActive ? $fallback : $fallback;
}

function get_languages_map(PDO $pdo, bool $onlyActive = false): array
{
    $map = [];
    foreach (get_languages($pdo, $onlyActive) as $row) {
        $map[$row['code']] = $row;
    }
    return $map;
}

function get_default_language(PDO $pdo): array
{
    $langs = get_languages($pdo, true);
    if ($langs) return $langs[0];
    return default_lang_meta('en');
}

function get_request_lang(): string
{
    $requested = sanitize_lang($_GET['lang'] ?? null);

    try {
        $pdo = app_pdo();
        $map = get_languages_map($pdo, true);
        if (isset($map[$requested])) return $requested;
        $default = get_default_language($pdo);
        return (string)$default['code'];
    } catch (Throwable $e) {
    }

    return $requested === 'ar' ? 'ar' : 'en';
}

function lang_dir(string $lang): string
{
    $lang = sanitize_lang($lang);
    try {
        $pdo = app_pdo();
        $map = get_languages_map($pdo, false);
        if (isset($map[$lang]['dir'])) return $map[$lang]['dir'] === 'rtl' ? 'rtl' : 'ltr';
    } catch (Throwable $e) {
    }
    return $lang === 'ar' ? 'rtl' : 'ltr';
}




function ensure_app_session_started(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (headers_sent()) return;
    @session_start();
}

function report_challenge_generate(): array
{
    ensure_app_session_started();
    if (!isset($_SESSION['athkar_report_challenges']) || !is_array($_SESSION['athkar_report_challenges'])) {
        $_SESSION['athkar_report_challenges'] = [];
    }
    foreach ($_SESSION['athkar_report_challenges'] as $token => $entry) {
        if (!is_array($entry) || (int)($entry['expires'] ?? 0) < time()) unset($_SESSION['athkar_report_challenges'][$token]);
    }
    $a = random_int(2, 9);
    $b = random_int(1, 8);
    if (random_int(0, 1) === 1) {
        $question = $a . ' + ' . $b;
        $answer = (string)($a + $b);
    } else {
        if ($b > $a) [$a, $b] = [$b, $a];
        $question = $a . ' - ' . $b;
        $answer = (string)($a - $b);
    }
    $token = bin2hex(random_bytes(16));
    $_SESSION['athkar_report_challenges'][$token] = [
        'answer' => $answer,
        'attempts' => 0,
        'expires' => time() + 7200,
    ];
    return ['token' => $token, 'question' => $question];
}

function report_challenge_verify(string $token, string $answer): bool
{
    ensure_app_session_started();
    $token = trim($token);
    $answer = trim($answer);
    if ($token === '' || $answer === '') return false;
    $entry = $_SESSION['athkar_report_challenges'][$token] ?? null;
    if (!is_array($entry)) return false;
    if ((int)($entry['expires'] ?? 0) < time()) {
        unset($_SESSION['athkar_report_challenges'][$token]);
        return false;
    }
    $entry['attempts'] = (int)($entry['attempts'] ?? 0) + 1;
    $_SESSION['athkar_report_challenges'][$token] = $entry;
    if ($entry['attempts'] > 5) {
        unset($_SESSION['athkar_report_challenges'][$token]);
        return false;
    }
    if (!hash_equals((string)($entry['answer'] ?? ''), $answer)) return false;
    unset($_SESSION['athkar_report_challenges'][$token]);
    return true;
}

function report_captcha_question_text(string $question, array $ui = []): string
{
    $fallback = 'Quick check: solve {question}';
    $template = isset($ui['report_captcha_prompt']) && trim((string)$ui['report_captcha_prompt']) !== '' ? (string)$ui['report_captcha_prompt'] : $fallback;
    return str_replace('{question}', $question, $template);
}

function ensure_default_ui_strings(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    try {
        $has = (bool)$pdo->query("SHOW TABLES LIKE 'ui_strings'")->fetchColumn();
        if (!$has) { $done = true; return; }
        $defaults = [
            ['home_welcome_eyebrow','en','Welcome'],
            ['home_welcome_eyebrow','ar','مرحبًا'],
            ['home_welcome_title','en','Welcome to {app}'],
            ['home_welcome_title','ar','مرحبًا بك في {app}'],
            ['home_welcome_intro','en','Read your daily athkar in a simple, polished, multilingual experience.'],
            ['home_welcome_intro','ar','اقرأ أذكارك اليومية في تجربة بسيطة وأنيقة ومتعددة اللغات.'],
            ['home_sections_title','en','Choose a section'],
            ['home_sections_title','ar','اختر قسمًا'],
            ['home_sections_intro','en','Start with one of the athkar sections below.'],
            ['home_sections_intro','ar','ابدأ بأحد أقسام الأذكار أدناه.'],
            ['home_footer_links_title','en','More'],
            ['home_footer_links_title','ar','المزيد'],
            ['home_link_sitemap','en','Sitemap'],
            ['home_link_sitemap','ar','خريطة الموقع'],
            ['home_link_open_page','en','Open page'],
            ['home_link_open_page','ar','افتح الصفحة'],
            ['seo_nav_home','en','Home'],
            ['seo_nav_home','ar','الرئيسية'],
            ['seo_nav_website_home','en','Website Home'],
            ['seo_nav_website_home','ar','الواجهة العامة'],
            ['seo_nav_back_home','en','Back to home'],
            ['seo_nav_back_home','ar','الرجوع للرئيسية'],
            ['seo_nav_back','en','Back'],
            ['seo_nav_back','ar','رجوع'],
            ['seo_nav_open_app','en','Open App'],
            ['seo_nav_open_app','ar','افتح التطبيق'],
            ['seo_sitemap_title','en','Sitemap'],
            ['seo_sitemap_title','ar','خريطة الموقع'],
            ['seo_sitemap_description','en','Browse the public pages and athkar sections.'],
            ['seo_sitemap_description','ar','تصفح صفحات الموقع العامة وأقسام الأذكار.'],
            ['seo_sitemap_pages','en','Pages'],
            ['seo_sitemap_pages','ar','الصفحات'],
            ['seo_sitemap_sections','en','Athkar sections'],
            ['seo_sitemap_sections','ar','أقسام الأذكار'],
            ['seo_item_open','en','Open item'],
            ['seo_item_open','ar','افتح الذكر'],
            ['seo_count_label','en','Count'],
            ['seo_count_label','ar','العدد'],
            ['seo_label_transliteration','en','Transliteration'],
            ['seo_label_transliteration','ar','الكتابة الصوتية'],
            ['seo_label_translation','en','Translation'],
            ['seo_label_translation','ar','الترجمة'],
            ['seo_label_source','en','Source'],
            ['seo_label_source','ar','المصدر'],
            ['seo_lang_label','en','Language'],
            ['seo_lang_label','ar','اللغة'],
            ['public_sidebar_home','en','Home'],
            ['public_sidebar_home','ar','الرئيسية'],
            ['public_sidebar_sitemap','en','Sitemap'],
            ['public_sidebar_sitemap','ar','خريطة الموقع'],
            ['public_sidebar_help','en','Browse the app, switch language, and change the theme from one menu.'],
            ['public_sidebar_help','ar','تصفح التطبيق، وبدّل اللغة، وغيّر المظهر من قائمة واحدة.'],
            ['public_sidebar_language','en','Language'],
            ['public_sidebar_language','ar','اللغة'],
            ['public_sidebar_theme','en','Theme'],
            ['public_sidebar_theme','ar','المظهر'],
            ['public_sidebar_open_menu','en','Open menu'],
            ['public_sidebar_open_menu','ar','فتح القائمة'],
            ['public_sidebar_close_menu','en','Close menu'],
            ['public_sidebar_close_menu','ar','إغلاق القائمة'],
            ['report_button','en','Report'],
            ['report_button','ar','إبلاغ'],
            ['report_title','en','Report an issue'],
            ['report_title','ar','الإبلاغ عن مشكلة'],
            ['report_intro','en','Help us review this athkar item quickly.'],
            ['report_intro','ar','ساعدنا في مراجعة هذا الذكر بسرعة.'],
            ['report_name_label','en','Your name (optional)'],
            ['report_name_label','ar','اسمك (اختياري)'],
            ['report_email_label','en','Your email (optional)'],
            ['report_email_label','ar','بريدك الإلكتروني (اختياري)'],
            ['report_issue_label','en','Issue type'],
            ['report_issue_label','ar','نوع المشكلة'],
            ['report_message_label','en','Details'],
            ['report_message_label','ar','التفاصيل'],
            ['report_send_button','en','Send report'],
            ['report_send_button','ar','إرسال البلاغ'],
            ['report_cancel_button','en','Cancel'],
            ['report_cancel_button','ar','إلغاء'],
            ['report_success','en','Thank you. Your report has been sent.'],
            ['report_success','ar','شكرًا لك. تم إرسال البلاغ.'],
            ['report_error','en','Unable to send your report right now.'],
            ['report_error','ar','تعذر إرسال البلاغ الآن.'],
            ['report_validation_message','en','Please choose an issue type and add a short explanation.'],
            ['report_validation_message','ar','يرجى اختيار نوع المشكلة وإضافة شرح مختصر.'],
            ['report_option_source','en','Incorrect Source'],
            ['report_option_source','ar','مصدر غير صحيح'],
            ['report_option_translation','en','Incorrect Translation'],
            ['report_option_translation','ar','ترجمة غير صحيحة'],
            ['report_option_item','en','Incorrect Athkar Item'],
            ['report_option_item','ar','ذكر غير صحيح'],
            ['report_option_transliteration','en','Incorrect Transliteration'],
            ['report_option_transliteration','ar','كتابة صوتية غير صحيحة'],
            ['report_option_other','en','Other'],
            ['report_option_other','ar','أخرى'],
            ['report_spam_error','en','Please wait a few seconds and try again.'],
            ['report_spam_error','ar','يرجى الانتظار بضع ثوانٍ ثم المحاولة مرة أخرى.'],
            ['report_rate_limit_error','en','Please wait a little before sending another report.'],
            ['report_rate_limit_error','ar','يرجى الانتظار قليلًا قبل إرسال بلاغ آخر.'],
            ['report_captcha_prompt','en','Quick check: solve {question}'],
            ['report_captcha_prompt','ar','تحقق سريع: احسب {question}'],
            ['report_captcha_label','en','Answer'],
            ['report_captcha_label','ar','الإجابة'],
            ['report_captcha_placeholder','en','Type the result'],
            ['report_captcha_placeholder','ar','اكتب النتيجة'],
            ['report_captcha_error','en','Please solve the quick check correctly before sending.'],
            ['report_captcha_error','ar','يرجى حل التحقق السريع بشكل صحيح قبل الإرسال.'],
            ['report_open_item_label','en','Reporting this item'],
            ['report_open_item_label','ar','الإبلاغ عن هذا الذكر'],
            ['seo_nav_report','en','Report'],
            ['seo_nav_report','ar','إبلاغ'],
        ];
        $stmt = $pdo->prepare('INSERT IGNORE INTO ui_strings (string_key, lang, value) VALUES (:k, :l, :v)');
        foreach ($defaults as $row) {
            $stmt->execute(['k' => $row[0], 'l' => $row[1], 'v' => $row[2]]);
        }
    } catch (Throwable $e) {
    }
    $done = true;
}

function get_ui_strings(PDO $pdo, string $lang): array
{
    ensure_default_ui_strings($pdo);
    $stmt = $pdo->prepare('SELECT string_key, value FROM ui_strings WHERE lang = :l');
    $stmt->execute(['l' => $lang]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!is_array($rows)) $rows = [];

    if ($lang !== 'en') {
        $stmt2 = $pdo->prepare('SELECT string_key, value FROM ui_strings WHERE lang = :l');
        $stmt2->execute(['l' => 'en']);
        $en = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);
        if (is_array($en)) {
            foreach ($en as $k => $v) {
                if (!array_key_exists($k, $rows) || trim((string)$rows[$k]) === '') $rows[$k] = $v;
            }
        }
    }
    return $rows;
}

function get_site_content(PDO $pdo, string $lang, array $defaults = []): array
{
    $keys = array_keys($defaults);
    $out = $defaults;

    try {
        $hasNew = (bool)$pdo->query("SHOW TABLES LIKE 'site_content_i18n'")->fetchColumn();
        if ($hasNew) {
            if ($keys) {
                $placeholders = implode(',', array_fill(0, count($keys), '?'));
                $stmt = $pdo->prepare("SELECT content_key, value FROM site_content_i18n WHERE lang = ? AND content_key IN ($placeholders)");
                $stmt->execute(array_merge([$lang], $keys));
            } else {
                $stmt = $pdo->prepare('SELECT content_key, value FROM site_content_i18n WHERE lang = ?');
                $stmt->execute([$lang]);
            }
            foreach ($stmt->fetchAll() as $row) {
                $out[$row['content_key']] = $row['value'];
            }

            if ($lang !== 'en') {
                if ($keys) {
                    $placeholders = implode(',', array_fill(0, count($keys), '?'));
                    $stmt2 = $pdo->prepare("SELECT content_key, value FROM site_content_i18n WHERE lang = 'en' AND content_key IN ($placeholders)");
                    $stmt2->execute($keys);
                } else {
                    $stmt2 = $pdo->query("SELECT content_key, value FROM site_content_i18n WHERE lang = 'en'");
                }
                foreach ($stmt2->fetchAll() as $row) {
                    if (!isset($out[$row['content_key']]) || trim((string)$out[$row['content_key']]) === '') {
                        $out[$row['content_key']] = $row['value'];
                    }
                }
            }
            return $out;
        }
    } catch (Throwable $e) {
    }

    try {
        if ($keys) {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $pdo->prepare("SELECT content_key, content_html AS value FROM athkar_site_content WHERE content_key IN ($placeholders)");
            $stmt->execute($keys);
        } else {
            $stmt = $pdo->query('SELECT content_key, content_html AS value FROM athkar_site_content');
        }
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['content_key']] = $row['value'];
        }
    } catch (Throwable $e) {
    }
    return $out;
}
