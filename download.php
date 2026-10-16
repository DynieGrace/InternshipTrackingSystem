<?php
/**
 * Securely stream an uploaded document to an authorised user.
 *
 * Access is limited to the coordinator, the owning student, and the
 * supervisor assigned to that student's placement. Files live outside the
 * public listing and are never executed.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_login();
$id = (int)($_GET['id'] ?? 0);

$document = query_one(
    $db,
    'SELECT d.stored_name, d.original_name, d.mime_type, d.student_id, s.user_id
    FROM document_submissions d
    JOIN student_profiles s ON s.id = d.student_id
    WHERE d.id = ?',
    [$id]
);

$allowed = false;
if ($document) {
    if ($user['role'] === 'coordinator') {
        $allowed = true;
    } elseif ($user['role'] === 'student') {
        $allowed = (int)$document['user_id'] === (int)$user['id'];
    } elseif ($user['role'] === 'supervisor') {
        $allowed = (bool)query_value(
            $db,
            'SELECT 1 FROM internship_placements i
            JOIN supervisors sup ON sup.id = i.supervisor_id
            WHERE i.student_id = ? AND sup.user_id = ? LIMIT 1',
            [(int)$document['student_id'], (int)$user['id']]
        );
    }
}

$path = $document ? UPLOAD_PATH . '/' . basename($document['stored_name']) : '';

if (!$document || !$allowed || !is_file($path)) {
    http_response_code(404);
    exit('Document not found.');
}

header('Content-Type: ' . $document['mime_type']);
header('Content-Disposition: attachment; filename="' . rawurlencode($document['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
