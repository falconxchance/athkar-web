-- Multi-language (AR/EN) foundation
-- Run this once on an existing database.

CREATE TABLE IF NOT EXISTS app_languages (
  code VARCHAR(8) NOT NULL PRIMARY KEY,
  label VARCHAR(64) NOT NULL,
  native_label VARCHAR(64) NOT NULL,
  dir ENUM('ltr','rtl') NOT NULL DEFAULT 'ltr',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  display_order INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_languages (code, label, native_label, dir, is_active, display_order) VALUES
('en', 'English', 'English', 'ltr', 1, 1),
('ar', 'Arabic', 'العربية', 'rtl', 1, 2);


CREATE TABLE IF NOT EXISTS athkar_sections_i18n (
  section_slug VARCHAR(50) NOT NULL,
  lang VARCHAR(8) NOT NULL,
  label VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (section_slug, lang),
  CONSTRAINT fk_sections_i18n_section FOREIGN KEY (section_slug)
    REFERENCES athkar_sections (slug)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS athkar_items_i18n (
  item_id INT UNSIGNED NOT NULL,
  lang VARCHAR(8) NOT NULL,
  title VARCHAR(255) DEFAULT NULL,
  transliteration TEXT DEFAULT NULL,
  translation TEXT DEFAULT NULL,
  source TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (item_id, lang),
  CONSTRAINT fk_items_i18n_item FOREIGN KEY (item_id)
    REFERENCES athkar_items (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ui_strings (
  string_key VARCHAR(100) NOT NULL,
  lang VARCHAR(8) NOT NULL,
  value TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (string_key, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_content_i18n (
  content_key VARCHAR(100) NOT NULL,
  lang VARCHAR(8) NOT NULL,
  value MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (content_key, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed section translations (English from current columns; Arabic from a safe slug mapping)
INSERT IGNORE INTO athkar_sections_i18n (section_slug, lang, label, description)
SELECT slug, 'en', label, description FROM athkar_sections;

INSERT IGNORE INTO athkar_sections_i18n (section_slug, lang, label, description)
SELECT
  slug,
  'ar',
  CASE slug
    WHEN 'morning' THEN 'أذكار الصباح'
    WHEN 'evening' THEN 'أذكار المساء'
    WHEN 'prayer' THEN 'أذكار الصلاة'
    WHEN 'after-prayer' THEN 'أذكار بعد الصلاة'
    ELSE label
  END AS label,
  CASE slug
    WHEN 'morning' THEN 'أذكار الصباح'
    WHEN 'evening' THEN 'أذكار المساء'
    WHEN 'prayer' THEN 'أذكار الصلاة'
    WHEN 'after-prayer' THEN 'أذكار بعد الصلاة'
    ELSE description
  END AS description
FROM athkar_sections;

-- Seed item translations (English from existing item columns)
INSERT IGNORE INTO athkar_items_i18n (item_id, lang, title, transliteration, translation, source)
SELECT id, 'en', title, transliteration, translation, source FROM athkar_items;

-- Seed Arabic item translation rows (empty by default; you can fill them later)
INSERT IGNORE INTO athkar_items_i18n (item_id, lang, title, transliteration, translation, source)
SELECT id, 'ar', NULL, NULL, NULL, source FROM athkar_items;

-- Seed site content per language by copying existing athkar_site_content values (if present)
INSERT IGNORE INTO site_content_i18n (content_key, lang, value)
SELECT content_key, 'en', content_html FROM athkar_site_content;
INSERT IGNORE INTO site_content_i18n (content_key, lang, value)
SELECT content_key, 'ar', content_html FROM athkar_site_content;

-- Seed UI strings
INSERT IGNORE INTO ui_strings (string_key, lang, value) VALUES
('app_name', 'en', 'Athkar'),
('app_name', 'ar', 'أذكار'),

('btn_reset', 'en', 'Reset'),
('btn_reset', 'ar', 'إعادة'),

('tab_transliteration', 'en', 'Transliteration'),
('tab_transliteration', 'ar', 'النطق'),
('tab_translation', 'en', 'Translation'),
('tab_translation', 'ar', 'الترجمة'),
('tab_source', 'en', 'Source'),
('tab_source', 'ar', 'المصدر'),

('dock_tap', 'en', 'Tap'),
('dock_tap', 'ar', 'اضغط'),
('dock_remaining', 'en', 'remaining'),
('dock_remaining', 'ar', 'متبقي'),
('dock_completed', 'en', 'Completed'),
('dock_completed', 'ar', 'تم'),

('msg_complete_of_total', 'en', '{done} / {total} complete'),
('msg_complete_of_total', 'ar', '{done} / {total} مكتمل'),
('msg_section_completed', 'en', 'Section completed ✓'),
('msg_section_completed', 'ar', 'اكتمل القسم ✓'),

('msg_no_active_sections_title', 'en', 'No active sections found'),
('msg_no_active_sections_title', 'ar', 'لا توجد أقسام مفعّلة'),
('msg_no_active_sections_body', 'en', 'Create or activate a section from /user/sections.php, then refresh this page.'),
('msg_no_active_sections_body', 'ar', 'أنشئ أو فعّل قسمًا من /user/sections.php ثم حدّث الصفحة.'),

('msg_sections_error_title', 'en', 'Unable to load sections'),
('msg_sections_error_title', 'ar', 'تعذر تحميل الأقسام'),
('msg_sections_error_body', 'en', 'Please check the database connection and api/sections.php on your hosting.'),
('msg_sections_error_body', 'ar', 'يرجى التحقق من اتصال قاعدة البيانات و api/sections.php على الاستضافة.'),

('msg_section_not_found_title', 'en', 'Section not found'),
('msg_section_not_found_title', 'ar', 'القسم غير موجود'),
('msg_section_not_found_body', 'en', 'Please go back and choose one of the available athkar sections.'),
('msg_section_not_found_body', 'ar', 'ارجع واختر أحد الأقسام المتاحة.'),

('msg_no_athkar_found_title', 'en', 'No athkar found'),
('msg_no_athkar_found_body', 'en', 'This section does not have any items yet.'),
('msg_no_athkar_found_title', 'ar', 'لا توجد أذكار'),
('msg_no_athkar_found_body', 'ar', 'هذا القسم لا يحتوي على عناصر بعد.'),

('msg_data_load_error_title', 'en', 'Unable to load data'),
('msg_data_load_error_title', 'ar', 'تعذر تحميل البيانات'),
('msg_data_load_error_body', 'en', 'Please check your database connection, active section status, and uploaded files.'),
('msg_data_load_error_body', 'ar', 'يرجى التحقق من اتصال قاعدة البيانات وتفعيل القسم والملفات المرفوعة.'),

('aria_prev', 'en', 'Previous athkar'),
('aria_prev', 'ar', 'الذكر السابق'),
('aria_next', 'en', 'Next athkar'),
('aria_next', 'ar', 'الذكر التالي'),
('aria_undo', 'en', 'Undo last count'),
('aria_undo', 'ar', 'تراجع عن آخر ضغطة'),
('aria_count', 'en', 'Count next repetition'),
('aria_count', 'ar', 'عدّ التسبيح التالي'),
('aria_back_home', 'en', 'Back to home'),
('aria_back_home', 'ar', 'العودة للرئيسية'),
('aria_toggle_dark', 'en', 'Toggle dark mode'),
('aria_toggle_dark', 'ar', 'تبديل الوضع الداكن'),

('lbl_open_section', 'en', 'Open section'),
('lbl_open_section', 'ar', 'افتح القسم'),

('lang_en', 'en', 'English'),
('lang_en', 'ar', 'الإنجليزية'),
('lang_ar', 'en', 'Arabic'),
('lang_ar', 'ar', 'العربية')
,
('home_welcome_eyebrow', 'en', 'Welcome'),
('home_welcome_eyebrow', 'ar', 'مرحبًا'),
('home_welcome_title', 'en', 'Welcome to {app}'),
('home_welcome_title', 'ar', 'مرحبًا بك في {app}'),
('home_welcome_intro', 'en', 'Read your daily athkar in a simple, polished, multilingual experience.'),
('home_welcome_intro', 'ar', 'اقرأ أذكارك اليومية في تجربة بسيطة وأنيقة ومتعددة اللغات.'),
('home_sections_title', 'en', 'Choose a section'),
('home_sections_title', 'ar', 'اختر قسمًا'),
('home_sections_intro', 'en', 'Start with one of the athkar sections below.'),
('home_sections_intro', 'ar', 'ابدأ بأحد أقسام الأذكار أدناه.'),
('home_footer_links_title', 'en', 'More'),
('home_footer_links_title', 'ar', 'المزيد'),
('home_link_sitemap', 'en', 'Sitemap'),
('home_link_sitemap', 'ar', 'خريطة الموقع'),
('home_link_open_page', 'en', 'Open page'),
('home_link_open_page', 'ar', 'افتح الصفحة'),
('seo_nav_home', 'en', 'Home'),
('seo_nav_home', 'ar', 'الرئيسية'),
('seo_nav_website_home', 'en', 'Website Home'),
('seo_nav_website_home', 'ar', 'الواجهة العامة'),
('seo_nav_back_home', 'en', 'Back to home'),
('seo_nav_back_home', 'ar', 'الرجوع للرئيسية'),
('seo_nav_back', 'en', 'Back'),
('seo_nav_back', 'ar', 'رجوع'),
('seo_nav_open_app', 'en', 'Open App'),
('seo_nav_open_app', 'ar', 'افتح التطبيق'),
('seo_sitemap_title', 'en', 'Sitemap'),
('seo_sitemap_title', 'ar', 'خريطة الموقع'),
('seo_sitemap_description', 'en', 'Browse the public pages and athkar sections.'),
('seo_sitemap_description', 'ar', 'تصفح صفحات الموقع العامة وأقسام الأذكار.'),
('seo_sitemap_pages', 'en', 'Pages'),
('seo_sitemap_pages', 'ar', 'الصفحات'),
('seo_sitemap_sections', 'en', 'Athkar sections'),
('seo_sitemap_sections', 'ar', 'أقسام الأذكار'),
('seo_item_open', 'en', 'Open item'),
('seo_item_open', 'ar', 'افتح الذكر'),
('seo_count_label', 'en', 'Count'),
('seo_count_label', 'ar', 'العدد'),
('seo_label_transliteration', 'en', 'Transliteration'),
('seo_label_transliteration', 'ar', 'الكتابة الصوتية'),
('seo_label_translation', 'en', 'Translation'),
('seo_label_translation', 'ar', 'الترجمة'),
('seo_label_source', 'en', 'Source'),
('seo_label_source', 'ar', 'المصدر'),
('seo_lang_label', 'en', 'Language'),
('seo_lang_label', 'ar', 'اللغة')
;
