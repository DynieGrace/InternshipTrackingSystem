<?php
/**
 * Complete a password reset using the token from the reset link.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$reset = $token !== '' ? find_password_reset($db, $token) : null;
$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        complete_password_reset($db, $token, (string)($_POST['password'] ?? ''), (string)($_POST['confirm_password'] ?? ''));
        flash('success', 'Your password has been updated. Please sign in.');
        redirect('login.php');
    } catch (InvalidArgumentException $error) {
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        error_log('ITS reset password failed: ' . $error->getMessage());
        $errors[] = 'The password could not be updated. Please try again.';
    }
}

$authTitle = 'Reset password';
$authSubtitle = 'Choose a new password';
require __DIR__ . '/includes/auth_header.php';
?>
    <h2>Choose a new password</h2>

    <?php foreach ($errors as $message): ?>
      <div class="notice error" role="alert"><?= e($message) ?></div>
    <?php endforeach; ?>

    <?php if (!$reset): ?>
      <div class="notice error" role="alert">This reset link is invalid or has expired.</div>
      <p class="muted"><a class="text-link" href="<?= e(url('forgot_password.php')) ?>">Request a new reset link</a></p>
    <?php else: ?>
      <form method="post" action="<?= e(url('reset_password.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label for="password">New password</label>
        <span class="password-field">
          <input id="password" name="password" type="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required autofocus>
          <?= password_toggle_button() ?>
        </span>
        <label for="confirm_password">Confirm new password</label>
        <span class="password-field">
          <input id="confirm_password" name="confirm_password" type="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required>
          <?= password_toggle_button() ?>
        </span>
        <button class="button primary full" type="submit">Update password</button>
      </form>
    <?php endif; ?>

    <div class="auth-footnote"><a class="text-link" href="<?= e(url('login.php')) ?>">Back to sign in</a></div>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
