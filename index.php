<?php
/**
 * Entry point. Signed-in users are sent to their role dashboard; everyone
 * else lands on the login page.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect(dashboard_path_for_role(current_user()['role']));
}

redirect('login.php');
