<?php
/**
 * Public registration. This form creates Student Intern accounts only;
 * coordinator and supervisor accounts are provisioned by a coordinator.
 *
 * This system is for one programme only: all students are
 * BS Information Technology, 4th Year.
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
$form = [
    'full_name' => '', 'student_id' => '', 'email' => '', 'contact_number' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        foreach ($form as $key => $_) {
            $form[$key] = post_string($key, 190);
        }
        $form['password'] = (string)($_POST['password'] ?? '');
        $form['confirm_password'] = (string)($_POST['confirm_password'] ?? '');

        register_student($db, [
            'full_name'        => $form['full_name'],
            'student_id'       => $form['student_id'],
            'email'            => $form['email'],
            'password'         => $form['password'],
            'confirm_password' => $form['confirm_password'],
            'contact_number'   => $form['contact_number'],
        ]);

        flash('success', 'Account created. You can now sign in with your email address.');
        redirect('login.php');
    } catch (InvalidArgumentException $error) {
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        error_log('ITS registration failed: ' . $error->getMessage());
        $errors[] = 'Registration could not be completed. Please try again.';
    }
}

$authTitle = 'Create account';
$authSubtitle = 'Student registration';
require __DIR__ . '/includes/auth_header.php';
?>
    <h2>Create your account</h2>
    <p class="muted">Student interns register here. All students are <strong>BS Information Technology, 4th Year</strong>.</p>

    <?php foreach ($errors as $message): ?>
      <div class="notice error" role="alert"><?= e($message) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= e(url('register.php')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="form-grid">
        <label>Full name<input name="full_name" maxlength="120" value="<?= e($form['full_name']) ?>" required></label>
        <label>Student ID<input name="student_id" maxlength="50" value="<?= e($form['student_id']) ?>" required></label>
        <label>Email<input name="email" type="email" maxlength="190" value="<?= e($form['email']) ?>" required></label>
        <label>Contact number<input name="contact_number" maxlength="40" value="<?= e($form['contact_number']) ?>"></label>
      </div>
      <div class="form-grid">
        <label>Password
          <span class="password-field">
            <input name="password" type="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required>
            <button class="password-toggle" type="button" data-password-toggle aria-label="Show password">Show</button>
          </span>
        </label>
        <label>Confirm password
          <span class="password-field">
            <input name="confirm_password" type="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required>
            <button class="password-toggle" type="button" data-password-toggle aria-label="Show password">Show</button>
          </span>
        </label>
      </div>
      <p class="help-text">Use at least <?= PASSWORD_MIN_LENGTH ?> characters for your password.</p>
      <button class="button primary full" type="submit">Create account</button>
    </form>

    <div class="auth-footnote">Already registered? <a class="text-link" href="<?= e(url('login.php')) ?>">Sign in</a>.</div>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
