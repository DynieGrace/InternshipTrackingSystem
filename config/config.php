<?php
/**
 * Internship Tracking System - application configuration.
 *
 * All environment-specific values live here so they can be changed in one
 * place. Database credentials may also be supplied through environment
 * variables, which take precedence over the defaults below.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_NAME', 'Internship Tracking System');
define('APP_SHORT_NAME', 'InternTrack');
define('APP_VERSION', '1.0.0');

/* ------------------------------------------------------------------ *
 * Database connection settings (XAMPP defaults).
 * Override with environment variables when your setup differs.
 * ------------------------------------------------------------------ */
define('DB_HOST', getenv('ITS_DB_HOST') ?: '127.0.0.1');
// NOTE: on this machine the Windows "MySQL80" service occupies port 3306, so
// XAMPP's MariaDB (which holds this database) runs on 3307. If you stop the
// MySQL80 service and start MariaDB on 3306, set this back to '3306' or set
// the ITS_DB_PORT environment variable.
define('DB_PORT', getenv('ITS_DB_PORT') ?: '3307');
define('DB_NAME', getenv('ITS_DB_NAME') ?: 'internship_tracking_system');
define('DB_USER', getenv('ITS_DB_USER') ?: 'root');
define('DB_PASS', getenv('ITS_DB_PASSWORD') ?: '');

/* ------------------------------------------------------------------ *
 * Uploads and files.
 * ------------------------------------------------------------------ */
define('UPLOAD_PATH', APP_ROOT . '/uploads');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);            // 5 MB
define('ALLOWED_EXTENSIONS', 'pdf,jpg,jpeg,png');

/* ------------------------------------------------------------------ *
 * Sessions and security.
 * ------------------------------------------------------------------ */
define('SESSION_NAME', 'ITS_SESSION');
define('PASSWORD_MIN_LENGTH', 10);
define('PASSWORD_RESET_TTL', 3600);                     // 1 hour

/**
 * Build an absolute application URL for a project-relative path.
 *
 * Works whether the project sits at the web root or inside a subfolder
 * such as /Internship-Tracking-System/. Pages in the role subfolders call
 * url('coordinator/students.php') and always receive a correct path.
 */
function url(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        $docRoot = str_replace('\\', '/', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
        $appRoot = str_replace('\\', '/', APP_ROOT);

        if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
            $base = rtrim(substr($appRoot, strlen($docRoot)), '/');
        } else {
            // Fallback for non-standard layouts: reuse the request directory.
            $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
            $base = rtrim(dirname($script), '/');
            if (basename($base) === 'student' || basename($base) === 'coordinator' || basename($base) === 'supervisor') {
                $base = dirname($base);
            }
        }
    }

    return $base . '/' . ltrim($path, '/');
}

/** Absolute filesystem path inside the application. */
function app_path(string $path = ''): string
{
    return APP_ROOT . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

/** Random URL-safe token used for QR attendance and password resets. */
function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}
