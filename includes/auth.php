<?php
/**
 * Authentication, session state and role guards.
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']['id']);
}

/** Send the browser to a project-relative page and stop. */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash_redirect(string $path, string $message, string $type = 'success'): never
{
    flash($type, $message);
    redirect($path);
}

/** Home dashboard path for a role. */
function dashboard_path_for_role(string $role): string
{
    return match ($role) {
        'student'     => 'student/dashboard.php',
        'supervisor'  => 'supervisor/dashboard.php',
        'coordinator' => 'coordinator/dashboard.php',
        default       => 'login.php',
    };
}

/**
 * Verify credentials, establish the session, and return the account row
 * (without the password hash). Throws InvalidArgumentException on failure.
 */
function attempt_login(PDO $db, string $identifier, string $password): array
{
    $account = query_one(
        $db,
        "SELECT id, username, full_name, email, avatar, password_hash, role
         FROM users WHERE (email = ? OR username = ?) AND status = 'active' LIMIT 1",
        [$identifier, $identifier]
    );

    if (!$account || !password_verify($password, $account['password_hash'])) {
        // Constant-ish response time to avoid a trivial timing oracle.
        password_verify($password, '$2y$12$usesomesillystringfortestingusesomesillystringfor');
        throw new InvalidArgumentException('The email/username or password is incorrect.');
    }

    session_regenerate_id(true);
    unset($account['password_hash']);
    $_SESSION['user'] = $account;
    record_activity($db, (int)$account['id'], 'login', $account['role']);
    return $account;
}

function logout_user(PDO $db): void
{
    $user = current_user();
    if ($user) {
        record_activity($db, (int)$user['id'], 'logout');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

/** Require a signed-in user, otherwise bounce to the login page. */
function require_login(): array
{
    static $validated = false;

    $user = current_user();
    if (!$user) {
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }

    // Confirm the account still exists and is active. This clears stale
    // sessions left behind by a database reset or a deactivated account.
    if (!$validated) {
        $db = database_or_null();
        if ($db instanceof PDO) {
            $fresh = query_one(
                $db,
                "SELECT id, username, full_name, email, avatar, role FROM users WHERE id = ? AND status = 'active'",
                [(int)$user['id']]
            );
            if (!$fresh) {
                $_SESSION = [];
                session_regenerate_id(true);
                flash('error', 'Your session has ended because that account is no longer available. Please sign in again.');
                redirect('login.php');
            }
            $_SESSION['user'] = $fresh;
        }
        $validated = true;
    }

    return $_SESSION['user'];
}

/** Require one of the given roles, otherwise show a 403 page. */
function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        render_error_page('Access denied', 'Your account does not have permission to view that page.');
    }
    return $user;
}

/** Student profile row for a user id, or null. */
function student_profile(PDO $db, int $userId): ?array
{
    return query_one($db, 'SELECT * FROM student_profiles WHERE user_id = ?', [$userId]);
}

/** Supervisor profile row for a user id, or null. */
function supervisor_profile(PDO $db, int $userId): ?array
{
    return query_one($db, 'SELECT * FROM supervisors WHERE user_id = ?', [$userId]);
}

/* ------------------------------------------------------------------ *
 * Registration
 * ------------------------------------------------------------------ */

/**
 * Create a public student account. Public registration may only ever
 * create Student Intern accounts.
 */
function register_student(PDO $db, array $input): int
{
    $fullName = trim((string)($input['full_name'] ?? ''));
    $studentId = trim((string)($input['student_id'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $confirm = (string)($input['confirm_password'] ?? '');
    // This system serves one programme only.
    $course = 'BS Information Technology';
    $yearLevel = '4th Year';
    $phone = trim((string)($input['contact_number'] ?? ''));

    if (mb_strlen($fullName) < 2) {
        throw new InvalidArgumentException('Please enter your full name.');
    }
    if ($studentId === '' || mb_strlen($studentId) > 50) {
        throw new InvalidArgumentException('Please enter a valid student ID.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Please enter a valid email address.');
    }
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        throw new InvalidArgumentException('Your password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.');
    }
    if ($password !== $confirm) {
        throw new InvalidArgumentException('The two passwords do not match.');
    }

    if (query_value($db, 'SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
        throw new InvalidArgumentException('That email address is already registered.');
    }
    if (query_value($db, 'SELECT id FROM student_profiles WHERE student_number = ? LIMIT 1', [$studentId])) {
        throw new InvalidArgumentException('That student ID is already registered.');
    }

    $username = unique_username($db, $email);

    $db->beginTransaction();
    try {
        $db->prepare(
            'INSERT INTO users (username, full_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)'
        )->execute([$username, $fullName, $email, password_hash($password, PASSWORD_DEFAULT), 'student']);

        $userId = (int)$db->lastInsertId();

        $db->prepare(
            'INSERT INTO student_profiles (user_id, student_number, course, year_level, phone) VALUES (?, ?, ?, ?, ?)'
        )->execute([$userId, $studentId, $course, $yearLevel, $phone]);

        // Every coordinator is notified so the new student can be placed.
        foreach (query_all($db, "SELECT id FROM users WHERE role = 'coordinator' AND status = 'active'") as $coordinator) {
            notify_user($db, (int)$coordinator['id'], 'New student registration',
                $fullName . ' (' . $studentId . ') has registered and needs a placement.');
        }

        record_activity($db, $userId, 'register', 'student: ' . $email);
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }

    return $userId;
}

/** Derive a unique username from an email address. */
function unique_username(PDO $db, string $email): string
{
    $base = preg_replace('/[^a-z0-9._-]+/', '', strtolower((string)strstr($email, '@', true)));
    $base = $base !== '' ? substr($base, 0, 40) : 'student';
    $candidate = $base;
    $suffix = 1;
    while (query_value($db, 'SELECT id FROM users WHERE username = ? LIMIT 1', [$candidate])) {
        $candidate = $base . $suffix++;
        if ($suffix > 9999) {
            $candidate = $base . random_int(10000, 99999);
            break;
        }
    }
    return $candidate;
}

/* ------------------------------------------------------------------ *
 * Password reset
 * ------------------------------------------------------------------ */

function create_password_reset(PDO $db, string $email): ?string
{
    $user = query_one($db, "SELECT id FROM users WHERE email = ? AND status = 'active' LIMIT 1", [$email]);
    if (!$user) {
        return null;
    }

    $token = random_token(32);
    $db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int)$user['id']]);
    $db->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
    )->execute([(int)$user['id'], hash('sha256', $token), PASSWORD_RESET_TTL]);

    return $token;
}

function find_password_reset(PDO $db, string $token): ?array
{
    return query_one(
        $db,
        'SELECT id, user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1',
        [hash('sha256', $token)]
    );
}

function complete_password_reset(PDO $db, string $token, string $password, string $confirm): void
{
    $reset = find_password_reset($db, $token);
    if (!$reset) {
        throw new InvalidArgumentException('This password reset link is invalid or has expired.');
    }
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        throw new InvalidArgumentException('Your password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.');
    }
    if ($password !== $confirm) {
        throw new InvalidArgumentException('The two passwords do not match.');
    }

    $db->beginTransaction();
    try {
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$reset['user_id']]);
        $db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int)$reset['user_id']]);
        record_activity($db, (int)$reset['user_id'], 'reset_password');
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}
