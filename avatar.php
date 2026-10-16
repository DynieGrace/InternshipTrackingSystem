<?php
/**
 * Stream a user's profile photo. Uploads live outside the public listing,
 * so images are served through this authenticated endpoint.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

require_login();

$id = (int)($_GET['id'] ?? current_user()['id']);
$stored = query_value($db, 'SELECT avatar FROM users WHERE id = ?', [$id]);

$path = $stored ? UPLOAD_PATH . '/avatars/' . basename((string)$stored) : '';
if (!$stored || !is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
