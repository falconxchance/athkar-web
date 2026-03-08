<?php
require_once __DIR__ . '/../config/i18n.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function report_json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function report_strlen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function report_substr(string $value, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
}

function report_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $scheme = $https ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    return $scheme . '://' . $host;
}

function report_mail_domain(): string
{
    $host = (string)parse_url(report_base_url(), PHP_URL_HOST);
    $host = strtolower(trim(preg_replace('/:\d+$/', '', $host)));
    if ($host === '' || !preg_match('/^[a-z0-9.-]+$/', $host) || $host === 'localhost') return 'localhost.localdomain';
    return $host;
}

function report_mail_headers(string $fromEmail, array $report): array
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <report-' . bin2hex(random_bytes(8)) . '@' . report_mail_domain() . '>',
        'From: goAthkar Reports <' . $fromEmail . '>',
        'X-Mailer: goAthkar Reports',
        'X-Auto-Response-Suppress: All',
        'Auto-Submitted: auto-generated',
    ];
    if ($report['reporter_email'] !== '' && filter_var($report['reporter_email'], FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $report['reporter_email'];
    }
    return $headers;
}

function report_send_via_sendmail(string $destinationEmail, string $subject, string $body, array $headers, string $fromEmail): bool
{
    $sendmailPath = is_executable('/usr/sbin/sendmail') ? '/usr/sbin/sendmail' : (is_executable('/usr/lib/sendmail') ? '/usr/lib/sendmail' : '');
    if ($sendmailPath === '') return false;
    $command = [$sendmailPath, '-t', '-i'];
    if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $command[] = '-f';
        $command[] = $fromEmail;
    }
    $descriptorSpec = [
        0 => ['pipe', 'w'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open($command, $descriptorSpec, $pipes);
    if (!is_resource($process)) return false;
    $message = 'To: ' . $destinationEmail . "
" .
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=' . "
" .
        implode("
", $headers) . "

" .
        $body;
    fwrite($pipes[0], $message);
    fclose($pipes[0]);
    if (isset($pipes[1])) fclose($pipes[1]);
    if (isset($pipes[2])) fclose($pipes[2]);
    return proc_close($process) === 0;
}

function report_send_email(string $destinationEmail, array $report, array $item): bool
{
    if ($destinationEmail === '' || !filter_var($destinationEmail, FILTER_VALIDATE_EMAIL)) return false;

    $subject = '[Athkar Report] ' . ($item['title'] ?: $item['item_key']);
    $lines = [
        'A new athkar issue report was submitted.',
        '',
        'Item: ' . ($item['title'] ?: $item['item_key']),
        'Item key: ' . $item['item_key'],
        'Section: ' . $item['section_slug'],
        'Language: ' . $report['lang'],
        'Page context: ' . $report['page_context'],
        'Issue type: ' . $report['issue_type'],
        'Reporter name: ' . ($report['reporter_name'] !== '' ? $report['reporter_name'] : 'Anonymous'),
        'Reporter email: ' . ($report['reporter_email'] !== '' ? $report['reporter_email'] : 'Not provided'),
        'URL: ' . ($report['source_url'] !== '' ? $report['source_url'] : report_base_url()),
        'IP: ' . ($report['ip_address'] !== '' ? $report['ip_address'] : 'Unknown'),
        'User agent: ' . ($report['user_agent'] !== '' ? $report['user_agent'] : 'Unknown'),
        '',
        'Message:',
        $report['message'],
    ];
    $body = implode("
", $lines);
    $fromEmail = 'reports@' . report_mail_domain();
    $headers = report_mail_headers($fromEmail, $report);
    $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $params = ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) ? '-f' . $fromEmail : '';

    $mailSent = false;
    if (function_exists('mail')) {
        $mailSent = $params !== ''
            ? @mail($destinationEmail, $subjectEncoded, $body, implode("
", $headers), $params)
            : @mail($destinationEmail, $subjectEncoded, $body, implode("
", $headers));
    }
    if ($mailSent) return true;
    return report_send_via_sendmail($destinationEmail, $subject, $body, $headers, $fromEmail);
}

$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($requestMethod === 'GET' && isset($_GET['challenge'])) {
    $challenge = report_challenge_generate();
    echo json_encode([
        'ok' => true,
        'token' => (string)($challenge['token'] ?? ''),
        'question' => (string)($challenge['question'] ?? ''),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($requestMethod !== 'POST') {
    report_json_error('Method not allowed.', 405);
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) $data = $_POST;

$itemKey = trim((string)($data['item_key'] ?? ''));
$sectionSlug = trim((string)($data['section_slug'] ?? ''));
$lang = sanitize_lang($data['lang'] ?? 'en');
$pageContext = trim((string)($data['page_context'] ?? 'app'));
$issueType = trim((string)($data['issue_type'] ?? ''));
$reporterName = trim((string)($data['reporter_name'] ?? ''));
$reporterEmail = trim((string)($data['reporter_email'] ?? ''));
$message = trim((string)($data['message'] ?? ''));
$sourceUrl = trim((string)($data['source_url'] ?? ''));
$formStartedAt = (int)($data['form_started_at'] ?? 0);
$honeypot = trim((string)($data['company_name'] ?? ''));
$captchaToken = trim((string)($data['captcha_token'] ?? ''));
$captchaAnswer = trim((string)($data['captcha_answer'] ?? ''));
$ipAddress = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
$userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

$allowedIssueTypes = ['incorrect_source','incorrect_translation','incorrect_athkar_item','incorrect_transliteration','other'];
$allowedContexts = ['app','seo_item'];

if ($itemKey === '' || $sectionSlug === '') report_json_error('Missing report item data.');
if ($honeypot !== '') {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (!in_array($pageContext, $allowedContexts, true)) $pageContext = 'app';
if (!in_array($issueType, $allowedIssueTypes, true)) report_json_error('Please choose a valid issue type.');
if ($message === '' || report_strlen($message) < 3) report_json_error('Please add a short explanation.');
if ($formStartedAt <= 0 || abs(time() - $formStartedAt) > 14400 || (time() - $formStartedAt) < 3) {
    report_json_error('Please wait a few seconds and try again.', 429);
}
if ($reporterEmail !== '' && !filter_var($reporterEmail, FILTER_VALIDATE_EMAIL)) report_json_error('Please enter a valid email address.');
if (!report_challenge_verify($captchaToken, $captchaAnswer)) report_json_error('Please solve the quick check correctly before sending.', 429);

$reporterName = report_substr($reporterName, 120);
$reporterEmail = report_substr($reporterEmail, 190);
$message = report_substr($message, 2000);
$sourceUrl = report_substr($sourceUrl, 500);
$ipAddress = report_substr($ipAddress, 64);
$userAgent = report_substr($userAgent, 255);

try {
    $pdo = app_pdo();
    if (!(bool)$pdo->query("SHOW TABLES LIKE 'athkar_reports'")->fetchColumn()) {
        report_json_error('Reports table is missing. Please import db/upgrade-reports.sql first.', 500);
    }

    if ($ipAddress !== '') {
        $rateStmt = $pdo->prepare('SELECT COUNT(*) FROM athkar_reports WHERE ip_address = :ip AND created_at >= (NOW() - INTERVAL 15 MINUTE)');
        $rateStmt->execute(['ip' => $ipAddress]);
        if ((int)$rateStmt->fetchColumn() >= 2) {
            report_json_error('Please wait a little before sending another report.', 429);
        }

        $dailyStmt = $pdo->prepare('SELECT COUNT(*) FROM athkar_reports WHERE ip_address = :ip AND created_at >= (NOW() - INTERVAL 1 DAY)');
        $dailyStmt->execute(['ip' => $ipAddress]);
        if ((int)$dailyStmt->fetchColumn() >= 6) {
            report_json_error('Please wait a little before sending another report.', 429);
        }
    }

    $itemStmt = $pdo->prepare('SELECT i.id, i.item_key, i.section_slug, i.title FROM athkar_items i WHERE i.item_key = :item_key AND i.section_slug = :section_slug AND i.is_active = 1 LIMIT 1');
    $itemStmt->execute(['item_key' => $itemKey, 'section_slug' => $sectionSlug]);
    $item = $itemStmt->fetch();
    if (!$item) report_json_error('Athkar item not found.', 404);

    $insert = $pdo->prepare('INSERT INTO athkar_reports (item_id, item_key, section_slug, lang, page_context, issue_type, reporter_name, reporter_email, message, source_url, ip_address, user_agent) VALUES (:item_id, :item_key, :section_slug, :lang, :page_context, :issue_type, :reporter_name, :reporter_email, :message, :source_url, :ip_address, :user_agent)');
    $insert->execute([
        'item_id' => (int)$item['id'],
        'item_key' => $item['item_key'],
        'section_slug' => $item['section_slug'],
        'lang' => $lang,
        'page_context' => $pageContext,
        'issue_type' => $issueType,
        'reporter_name' => $reporterName,
        'reporter_email' => $reporterEmail,
        'message' => $message,
        'source_url' => $sourceUrl,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
    ]);

    $site = get_site_content($pdo, $lang, ['report_email' => '']);
    $destinationEmail = trim((string)($site['report_email'] ?? ''));
    if ($destinationEmail === '') {
        $siteEn = get_site_content($pdo, 'en', ['report_email' => '']);
        $destinationEmail = trim((string)($siteEn['report_email'] ?? ''));
    }
    report_send_email($destinationEmail, [
        'lang' => $lang,
        'page_context' => $pageContext,
        'issue_type' => $issueType,
        'reporter_name' => $reporterName,
        'reporter_email' => $reporterEmail,
        'message' => $message,
        'source_url' => $sourceUrl,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
    ], $item);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    report_json_error('Unable to save this report right now.', 500);
}
