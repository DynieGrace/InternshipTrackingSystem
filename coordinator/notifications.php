<?php
/**
 * Coordinator notifications.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/notifications.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['coordinator']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        notifications_handle_post($db, $user, 'coordinator/notifications.php');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    }
    redirect('coordinator/notifications.php');
}

$title = 'Notifications';
$active = 'notifications';
require __DIR__ . '/../includes/header.php';

echo notifications_render($db, $user);

require __DIR__ . '/../includes/footer.php';
