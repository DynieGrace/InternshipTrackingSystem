<?php
/**
 * Sign-in page. Verifies credentials with password_verify(), regenerates
 * the session id, and redirects to the dashboard for the account's role.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

if (is_logged_in()) {
    redirect(dashboard_path_for_role(current_user()['role']));
}

$errors = [];
$identifier = $_COOKIE['its_last_user'] ?? '';
$flash = take_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $identifier = post_string('identifier', 190);
        $password = (string)($_POST['password'] ?? '');

        if ($identifier === '' || $password === '') {
            throw new InvalidArgumentException('Enter your email/username and password.');
        }

        $account = attempt_login($db, $identifier, $password);

        // "Remember me": extend the session cookie so the browser stays
        // signed in for 30 days, and remember the identifier for prefill.
        if (!empty($_POST['remember'])) {
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires'  => time() + (60 * 60 * 24 * 30),
                'path'     => $params['path'] ?: '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => (bool)($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        setcookie('its_last_user', $identifier, [
            'expires' => time() + (60 * 60 * 24 * 30),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        redirect(dashboard_path_for_role($account['role']));
    } catch (InvalidArgumentException $error) {
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        error_log('ITS login failed: ' . $error->getMessage());
        $errors[] = 'Sign in could not be completed. Please try again.';
    }
}

$authTitle = 'Sign in';
$authSubtitle = 'Sign in to your workspace';
require __DIR__ . '/includes/auth_header.php';
?>
    <h2>Welcome back</h2>
    <p class="muted">Use the account created for your internship role.</p>

    <?php foreach ($errors as $message): ?>
      <div class="notice error" role="alert"><?= e($message) ?></div>
    <?php endforeach; ?>
    <?php if ($flash): ?>
      <div class="notice <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login.php')) ?>" novalidate>
      <?= csrf_field() ?>
      <label for="identifier">Email or username</label>
      <input id="identifier" name="identifier" type="text" autocomplete="username"
             value="<?= e($identifier) ?>" required autofocus>

      <label for="password">Password</label>
      <span class="password-field">
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="password-toggle" type="button" data-password-toggle aria-label="Show password">Show</button>
      </span>

      <div class="auth-options">
        <label class="checkbox"><input type="checkbox" name="remember" value="1"> Remember me</label>
        <a class="text-link" href="<?= e(url('forgot_password.php')) ?>">Forgot password?</a>
      </div>

      <button class="button primary full" type="submit">Sign in <span aria-hidden="true">→</span></button>
    </form>

    <div class="auth-footnote">
      New student? <a class="text-link" href="<?= e(url('register.php')) ?>">Create an account</a>.<br>
      Coordinator and supervisor accounts are created by an authorized coordinator.
    </div>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
