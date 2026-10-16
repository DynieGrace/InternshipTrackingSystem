<?php
/**
 * Sign out. Only accepts a POST with a valid CSRF token.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$db = database_or_null();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verify_csrf();

if ($db instanceof PDO) {
    logout_user($db);
} else {
    $_SESSION = [];
    session_destroy();
}

flash('success', 'You have been signed out.');
redirect('login.php');
