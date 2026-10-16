<?php
/**
 * Student notifications.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/notifications.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['student']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        notifications_handle_post($db, $user, 'student/notifications.php');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
        redirect('student/notifications.php');
    }
    redirect('student/notifications.php');
}

$title = 'Notifications';
$active = 'notifications';
require __DIR__ . '/../includes/header.php';

echo notifications_render($db, $user);

require __DIR__ . '/../includes/footer.php';
