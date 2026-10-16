<?php
/**
 * Forgot-password request. Generates a single-use, time-limited reset
 * token. There is no mail server in this local setup, so the reset link
 * is shown on screen; wire it to mail() for a real deployment.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$errors = [];
$sent = false;
$resetLink = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $email = post_string('email', 190);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Please enter a valid email address.');
        }

        $token = create_password_reset($db, $email);
        $sent = true;
        if ($token !== null) {
            $resetLink = url('reset_password.php?token=' . urlencode($token));
            error_log('ITS password reset link for ' . $email . ': ' . $resetLink);
        }
    } catch (InvalidArgumentException $error) {
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        error_log('ITS forgot password failed: ' . $error->getMessage());
        $errors[] = 'The request could not be completed. Please try again.';
    }
}

$authTitle = 'Forgot password';
$authSubtitle = 'Reset your password';
require __DIR__ . '/includes/auth_header.php';
?>
    <h2>Forgot password</h2>
    <p class="muted">Enter the email address on your account and we will send a reset link.</p>

    <?php foreach ($errors as $message): ?>
      <div class="notice error" role="alert"><?= e($message) ?></div>
    <?php endforeach; ?>

    <?php if ($sent): ?>
      <div class="notice success" role="status">
        If that email address belongs to an account, a reset link has been generated. It expires in one hour.
      </div>
      <?php if ($resetLink): ?>
        <div class="notice" role="status">
          <strong>Local development:</strong> email delivery is not configured, so use this link:<br>
          <a class="text-link" href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <form method="post" action="<?= e(url('forgot_password.php')) ?>" novalidate>
      <?= csrf_field() ?>
      <label for="email">Email address</label>
      <input id="email" name="email" type="email" maxlength="190" value="<?= e($email) ?>" required autofocus>
      <button class="button primary full" type="submit">Send reset link</button>
    </form>

    <div class="auth-footnote">Remembered it? <a class="text-link" href="<?= e(url('login.php')) ?>">Back to sign in</a>.</div>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
