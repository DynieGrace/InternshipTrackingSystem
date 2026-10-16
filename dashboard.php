<?php
/**
 * Role router. Sends the signed-in user to the dashboard that matches
 * their role.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$user = require_login();
redirect(dashboard_path_for_role($user['role']));
