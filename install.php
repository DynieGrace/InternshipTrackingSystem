<?php
/**
 * One-time setup: create the first Internship Coordinator account.
 *
 * For safety this page only works while no active coordinator exists.
 * Once one is created the page refuses to create more and you should
 * delete install.php from the server.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$coordinatorExists = (int)query_value($db, "SELECT COUNT(*) FROM users WHERE role = 'coordinator' AND status = 'active'") > 0;

$errors = [];
$form = ['full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$coordinatorExists) {
    verify_csrf();
    try {
        $fullName = post_string('full_name', 120);
        $email = post_string('email', 190);
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if (mb_strlen($fullName) < 2) {
            throw new InvalidArgumentException('Enter the coordinator\'s full name.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new InvalidArgumentException('The password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.');
        }
        if ($password !== $confirm) {
            throw new InvalidArgumentException('The two passwords do not match.');
        }
        if (query_value($db, 'SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
            throw new InvalidArgumentException('That email address is already in use.');
        }

        $username = unique_username($db, $email);
        $db->prepare('INSERT INTO users (username, full_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)')
            ->execute([$username, $fullName, $email, password_hash($password, PASSWORD_DEFAULT), 'coordinator']);
        record_activity($db, (int)$db->lastInsertId(), 'install', 'First coordinator account created');

        flash('success', 'Coordinator account created. Please sign in, then delete install.php.');
        redirect('login.php');
    } catch (InvalidArgumentException $error) {
        $errors[] = $error->getMessage();
        $form['full_name'] = $_POST['full_name'] ?? '';
        $form['email'] = $_POST['email'] ?? '';
    } catch (Throwable $error) {
        error_log('ITS install failed: ' . $error->getMessage());
        $errors[] = 'The account could not be created. Please try again.';
    }
}

$authTitle = 'Setup';
$authSubtitle = 'First-time setup';
require __DIR__ . '/includes/auth_header.php';
?>
    <h2>Create the first coordinator</h2>

    <?php if ($coordinatorExists): ?>
      <div class="notice success" role="status">
        A coordinator account already exists. Setup is complete.
      </div>
      <p class="muted">For security, additional coordinator accounts can be created from the coordinator's
        <strong>User Accounts</strong> page. You may now delete <code>install.php</code>.</p>
      <a class="button primary full" href="<?= e(url('login.php')) ?>">Go to sign in</a>
    <?php else: ?>
      <p class="muted">This page is shown only once, while no coordinator exists. Create the account that will
        administer the system, then delete <code>install.php</code>.</p>

      <?php foreach ($errors as $message): ?>
        <div class="notice error" role="alert"><?= e($message) ?></div>
      <?php endforeach; ?>

      <form method="post" action="<?= e(url('install.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" maxlength="120" value="<?= e($form['full_name']) ?>" required autofocus>
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" maxlength="190" value="<?= e($form['email']) ?>" required>
        <label for="password">Password</label>
        <span class="password-field">
          <input id="password" name="password" type="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" autoComplete="new-password" required>
          <button class="password-toggle" type="button" data-password-toggle aria-label="Show password">Show</button>
        </span>
        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" autoComplete="new-password" required>
        <button class="button primary full" type="submit">Create coordinator account</button>
      </form>
    <?php endif; ?>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
