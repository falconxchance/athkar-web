<?php
require_once __DIR__ . '/db.php';

/**
 * Roles:
 *  - user        : can only access profile (and future progress sync)
 *  - editor      : can edit athkar sections + items
 *  - super_admin : full access (site settings + user management)
 */
const ATHKAR_ROLE_USER = 'user';
const ATHKAR_ROLE_EDITOR = 'editor';
const ATHKAR_ROLE_SUPER = 'super_admin';

const ATHKAR_LOGIN_LOCK_WINDOW_SECONDS = 900;   // 15 minutes
const ATHKAR_LOGIN_MAX_USER_FAILURES = 5;       // per username in window
const ATHKAR_LOGIN_MAX_IP_FAILURES = 10;        // per IP in window
const ATHKAR_LOGIN_FALLBACK_MAX_FAILURES = 5;   // per session fallback if SQL upgrade not run yet

function auth_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return false;
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        if (function_exists('ini_set')) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
        }

        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => auth_is_https(),
        ]);
    }
}

function admin_is_logged_in(): bool
{
    start_secure_session();
    return !empty($_SESSION['athkar_admin']) && is_array($_SESSION['athkar_admin']);
}

function admin_current_user(): ?array
{
    start_secure_session();
    return admin_is_logged_in() ? $_SESSION['athkar_admin'] : null;
}

function admin_current_role(): ?string
{
    $u = admin_current_user();
    if (!$u) return null;

    $role = (string)($u['role'] ?? '');
    if ($role !== '') return $role;

    // Backward compatibility if an old session exists
    if (!empty($u['is_admin'])) return ATHKAR_ROLE_SUPER;
    return ATHKAR_ROLE_EDITOR;
}

function admin_is_superadmin(): bool
{
    return admin_current_role() === ATHKAR_ROLE_SUPER;
}

function admin_is_editor(): bool
{
    $r = admin_current_role();
    return $r === ATHKAR_ROLE_EDITOR || $r === ATHKAR_ROLE_SUPER;
}

function require_admin(): void
{
    // Any authenticated admin-panel user (user/editor/super_admin)
    if (!admin_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_editor(): void
{
    require_admin();
    if (!admin_is_editor()) {
        http_response_code(403);
        exit('Forbidden: Editor access required.');
    }
}

function require_superadmin(): void
{
    require_admin();
    if (!admin_is_superadmin()) {
        http_response_code(403);
        exit('Forbidden: Super Admin access required.');
    }
}

function admin_users_has_role_column(PDO $pdo): bool
{
    static $cached = null;
    if ($cached !== null) return (bool)$cached;

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'role'");
        $row = $stmt->fetch();
        $cached = $row ? true : false;
    } catch (Throwable $e) {
        $cached = false;
    }
    return (bool)$cached;
}

function admin_login_attempts_table_exists(PDO $pdo): bool
{
    static $cached = null;
    if ($cached !== null) return (bool)$cached;

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_login_attempts'");
        $cached = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $cached = false;
    }

    return (bool)$cached;
}

function admin_login_client_ip(): string
{
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '' || strlen($ip) > 45) {
        return 'unknown';
    }
    return $ip;
}

function admin_login_user_agent(): string
{
    $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($ua, 0, 190, 'UTF-8');
    }
    return substr($ua, 0, 190);
}

function admin_login_fallback_cleanup(): void
{
    start_secure_session();
    $window = ATHKAR_LOGIN_LOCK_WINDOW_SECONDS;
    $now = time();
    $attempts = $_SESSION['admin_login_failures'] ?? [];
    if (!is_array($attempts)) {
        $attempts = [];
    }

    $attempts = array_values(array_filter($attempts, static function ($row) use ($now, $window) {
        $ts = (int)($row['ts'] ?? 0);
        return $ts > 0 && ($now - $ts) < $window;
    }));

    $_SESSION['admin_login_failures'] = $attempts;
}

function admin_login_lock_status(string $username = ''): array
{
    start_secure_session();

    $username = trim($username);
    $window = ATHKAR_LOGIN_LOCK_WINDOW_SECONDS;
    $now = time();
    $default = [
        'locked' => false,
        'retry_after' => 0,
        'reason' => '',
    ];

    $pdo = null;
    try {
        $pdo = app_pdo();
    } catch (Throwable $e) {
        $pdo = null;
    }

    if ($pdo instanceof PDO && admin_login_attempts_table_exists($pdo)) {
        $since = date('Y-m-d H:i:s', $now - $window);
        $ip = admin_login_client_ip();
        $retryAfter = 0;
        $reason = '';

        try {
            $stmtIp = $pdo->prepare(
                'SELECT COUNT(*) AS fail_count, MAX(attempted_at) AS last_attempt
                 FROM admin_login_attempts
                 WHERE was_success = 0 AND ip_address = :ip AND attempted_at >= :since'
            );
            $stmtIp->execute(['ip' => $ip, 'since' => $since]);
            $rowIp = $stmtIp->fetch() ?: ['fail_count' => 0, 'last_attempt' => null];
            $ipCount = (int)($rowIp['fail_count'] ?? 0);
            if ($ipCount >= ATHKAR_LOGIN_MAX_IP_FAILURES && !empty($rowIp['last_attempt'])) {
                $retryAfter = max($retryAfter, strtotime((string)$rowIp['last_attempt']) + $window - $now);
                $reason = 'ip';
            }

            if ($username !== '') {
                $stmtUser = $pdo->prepare(
                    'SELECT COUNT(*) AS fail_count, MAX(attempted_at) AS last_attempt
                     FROM admin_login_attempts
                     WHERE was_success = 0 AND username = :username AND attempted_at >= :since'
                );
                $stmtUser->execute(['username' => $username, 'since' => $since]);
                $rowUser = $stmtUser->fetch() ?: ['fail_count' => 0, 'last_attempt' => null];
                $userCount = (int)($rowUser['fail_count'] ?? 0);
                if ($userCount >= ATHKAR_LOGIN_MAX_USER_FAILURES && !empty($rowUser['last_attempt'])) {
                    $retryAfter = max($retryAfter, strtotime((string)$rowUser['last_attempt']) + $window - $now);
                    $reason = 'username';
                }
            }
        } catch (Throwable $e) {
            return $default;
        }

        if ($retryAfter > 0) {
            return [
                'locked' => true,
                'retry_after' => $retryAfter,
                'reason' => $reason,
            ];
        }

        return $default;
    }

    // Session fallback: protects the current browser even before the SQL upgrade is run.
    admin_login_fallback_cleanup();
    $attempts = $_SESSION['admin_login_failures'] ?? [];
    $count = is_array($attempts) ? count($attempts) : 0;
    if ($count >= ATHKAR_LOGIN_FALLBACK_MAX_FAILURES) {
        $last = end($attempts);
        $lastTs = (int)($last['ts'] ?? 0);
        $retryAfter = max(0, ($lastTs + $window) - $now);
        if ($retryAfter > 0) {
            return [
                'locked' => true,
                'retry_after' => $retryAfter,
                'reason' => 'session',
            ];
        }
    }

    return $default;
}

function admin_login_lock_message(array $status): string
{
    if (empty($status['locked'])) {
        return 'Invalid username or password.';
    }

    $retry = max(1, (int)($status['retry_after'] ?? 0));
    $minutes = (int)ceil($retry / 60);
    if ($minutes <= 1) {
        return 'Too many login attempts. Try again in about 1 minute.';
    }
    return 'Too many login attempts. Try again in about ' . $minutes . ' minutes.';
}

function admin_record_login_attempt(string $username, bool $wasSuccess): void
{
    start_secure_session();

    $pdo = null;
    try {
        $pdo = app_pdo();
    } catch (Throwable $e) {
        $pdo = null;
    }

    if ($pdo instanceof PDO && admin_login_attempts_table_exists($pdo)) {
        try {
            $ins = $pdo->prepare(
                'INSERT INTO admin_login_attempts (username, ip_address, user_agent, was_success, attempted_at)
                 VALUES (:username, :ip, :ua, :ok, NOW())'
            );
            $ins->execute([
                'username' => $username,
                'ip' => admin_login_client_ip(),
                'ua' => admin_login_user_agent(),
                'ok' => $wasSuccess ? 1 : 0,
            ]);

            // Clear recent failures for this exact username + IP after a valid login.
            if ($wasSuccess) {
                $del = $pdo->prepare(
                    'DELETE FROM admin_login_attempts
                     WHERE was_success = 0 AND username = :username AND ip_address = :ip'
                );
                $del->execute([
                    'username' => $username,
                    'ip' => admin_login_client_ip(),
                ]);
            }

            // Light cleanup of old records (non-fatal).
            if (random_int(1, 25) === 1) {
                $pdo->exec("DELETE FROM admin_login_attempts WHERE attempted_at < (NOW() - INTERVAL 30 DAY)");
            }
        } catch (Throwable $e) {
            // Ignore throttle storage issues so login itself never white-screens.
        }
        return;
    }

    // Session fallback if the login-attempts SQL upgrade has not been run yet.
    admin_login_fallback_cleanup();
    if ($wasSuccess) {
        $_SESSION['admin_login_failures'] = [];
        return;
    }

    $attempts = $_SESSION['admin_login_failures'] ?? [];
    if (!is_array($attempts)) {
        $attempts = [];
    }
    $attempts[] = [
        'ts' => time(),
        'username' => $username,
    ];
    $_SESSION['admin_login_failures'] = $attempts;
}

function db_has_admin_users(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM admin_users');
        $count = (int)$stmt->fetchColumn();
        return $count > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function attempt_login(string $username, string $password): bool
{
    start_secure_session();
    $username = trim($username);

    // Prefer DB users if available (table exists + at least 1 user).
    $pdo = null;
    try {
        $pdo = app_pdo();
    } catch (Throwable $e) {
        $pdo = null;
    }

    if ($pdo instanceof PDO && db_has_admin_users($pdo)) {
        $hasRole = admin_users_has_role_column($pdo);

        if ($hasRole) {
            $stmt = $pdo->prepare('SELECT id, username, password_hash, role, is_active FROM admin_users WHERE username = :u LIMIT 1');
        } else {
            // Backward compatibility: old schema uses is_admin only
            $stmt = $pdo->prepare('SELECT id, username, password_hash, is_admin, is_active FROM admin_users WHERE username = :u LIMIT 1');
        }

        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch();

        if (!$row) {
            admin_record_login_attempt($username, false);
            return false;
        }
        if ((int)$row['is_active'] !== 1) {
            admin_record_login_attempt($username, false);
            return false;
        }
        if (!password_verify($password, (string)$row['password_hash'])) {
            admin_record_login_attempt($username, false);
            return false;
        }

        $role = ATHKAR_ROLE_EDITOR;
        if ($hasRole) {
            $role = (string)($row['role'] ?? ATHKAR_ROLE_EDITOR);
            if ($role !== ATHKAR_ROLE_USER && $role !== ATHKAR_ROLE_EDITOR && $role !== ATHKAR_ROLE_SUPER) {
                $role = ATHKAR_ROLE_EDITOR;
            }
        } else {
            $role = ((int)($row['is_admin'] ?? 0) === 1) ? ATHKAR_ROLE_SUPER : ATHKAR_ROLE_EDITOR;
        }

        session_regenerate_id(true);
        $_SESSION['athkar_admin'] = [
            'id' => (int)$row['id'],
            'username' => (string)$row['username'],
            'role' => $role,
            // legacy session flag (some UI used it)
            'is_admin' => $role === ATHKAR_ROLE_SUPER,
            'bootstrap' => false,
            'logged_in_at' => time(),
        ];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['admin_login_failures'] = [];

        admin_record_login_attempt($username, true);

        // update last login (non-fatal)
        try {
            $u = $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
            $u->execute(['id' => (int)$row['id']]);
        } catch (Throwable $e) { /* ignore */ }

        return true;
    }

    // Bootstrap fallback (only used until the first DB super admin user is created)
    $config = app_config();
    $admin = $config['admin'] ?? null;
    if (!$admin || empty($admin['username']) || empty($admin['password_hash'])) {
        admin_record_login_attempt($username, false);
        return false;
    }

    if (!hash_equals((string)$admin['username'], $username)) {
        admin_record_login_attempt($username, false);
        return false;
    }

    if (!password_verify($password, (string)$admin['password_hash'])) {
        admin_record_login_attempt($username, false);
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['athkar_admin'] = [
        'id' => 0,
        'username' => $username,
        'role' => ATHKAR_ROLE_SUPER,
        'is_admin' => true,
        'bootstrap' => true,
        'logged_in_at' => time(),
    ];

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['admin_login_failures'] = [];
    admin_record_login_attempt($username, true);

    return true;
}

function admin_logout(): void
{
    start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
    }
    session_destroy();
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_or_fail(): void
{
    start_secure_session();
    $sent = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!$sent || !$stored || !hash_equals($stored, $sent)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
}

function esc(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function role_label(?string $role): string
{
    if ($role === ATHKAR_ROLE_SUPER) return 'Super Admin';
    if ($role === ATHKAR_ROLE_EDITOR) return 'Editor';
    return 'User';
}

require_once __DIR__ . '/admin_ui.php';
