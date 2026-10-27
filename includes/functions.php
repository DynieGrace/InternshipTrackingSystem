<?php
/**
 * Shared helper functions used across every page.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/* ------------------------------------------------------------------ *
 * Output escaping
 * ------------------------------------------------------------------ */

/** Escape a value for safe HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function password_toggle_button(): string
{
    return '<button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false">'
        . '<svg data-password-icon="show" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        . '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle>'
        . '</svg><svg data-password-icon="hide" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" hidden>'
        . '<path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>'
        . '<path d="M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a13.6 13.6 0 0 1-3.1 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.4 0 2.7-.3 3.8-.8"></path>'
        . '</svg></button>';
}

/* ------------------------------------------------------------------ *
 * CSRF protection
 * ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_token(32);
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Abort the request when the posted CSRF token is missing or wrong. */
function verify_csrf(): void
{
    $posted = (string)($_POST['csrf'] ?? '');
    if ($posted === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $posted)) {
        http_response_code(419);
        render_error_page('This form has expired', 'For your security, please go back, reload the page, and submit the form again.');
    }
}

/* ------------------------------------------------------------------ *
 * Flash messages
 * ------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/* ------------------------------------------------------------------ *
 * Input helpers
 * ------------------------------------------------------------------ */

/** Read a trimmed, length-checked string from $_POST. */
function post_string(string $key, int $maxLength = 255): string
{
    $raw = $_POST[$key] ?? '';
    if (!is_string($raw) && !is_numeric($raw)) {
        throw new InvalidArgumentException('The submitted value is invalid.');
    }
    $value = trim((string)$raw);
    if (mb_strlen($value) > $maxLength) {
        throw new InvalidArgumentException('One of the submitted values is too long.');
    }
    return $value;
}

/** Require a positive integer id from $_POST. */
function post_id(string $key): int
{
    $id = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        throw new InvalidArgumentException('Please choose a valid record.');
    }
    return (int)$id;
}

/** Optional positive integer id, or null when empty. */
function post_optional_id(string $key): ?int
{
    $value = $_POST[$key] ?? '';
    if ($value === '' || $value === null) {
        return null;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id && $id > 0 ? (int)$id : null;
}

/** Validate a date/time string against an exact format. */
function valid_datetime(string $value, string $format = 'Y-m-d'): bool
{
    $date = DateTime::createFromFormat('!' . $format, $value);
    $errors = DateTime::getLastErrors();
    return $date !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format($format) === $value;
}

/* ------------------------------------------------------------------ *
 * Database convenience
 * ------------------------------------------------------------------ */

function query_all(PDO $db, string $sql, array $params = []): array
{
    $query = $db->prepare($sql);
    $query->execute($params);
    return $query->fetchAll();
}

function query_one(PDO $db, string $sql, array $params = []): ?array
{
    $query = $db->prepare($sql);
    $query->execute($params);
    $row = $query->fetch();
    return $row === false ? null : $row;
}

function query_value(PDO $db, string $sql, array $params = []): mixed
{
    $query = $db->prepare($sql);
    $query->execute($params);
    return $query->fetchColumn();
}

/* ------------------------------------------------------------------ *
 * Data-shaping helpers (render as HTML strings)
 * ------------------------------------------------------------------ */

function status_badge(string $status): string
{
    $known = ['pending', 'approved', 'rejected', 'submitted', 'active', 'completed', 'inactive', 'archived'];
    $safe = in_array(strtolower($status), $known, true) ? strtolower($status) : 'pending';
    return '<span class="badge ' . e($safe) . '">' . e(ucfirst($status)) . '</span>';
}

function table_html(array $headers, array $rows, string $emptyMessage = 'No records found.'): string
{
    if (!$rows) {
        return '<div class="empty-state">' . e($emptyMessage) . '</div>';
    }
    $html = '<div class="table-wrap"><table><thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . e($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . $cell . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

function form_start(string $action, array $attrs = []): string
{
    $htmlAttrs = '';
    foreach ($attrs as $key => $value) {
        $htmlAttrs .= ' ' . e($key) . '="' . e($value) . '"';
    }
    return '<form method="post" action="' . e(url($action)) . '"' . $htmlAttrs . '>' . csrf_field();
}

function select_options(array $rows, string $valueKey, string $labelKey, string $selected = ''): string
{
    $html = '';
    foreach ($rows as $row) {
        $value = (string)$row[$valueKey];
        $isSelected = $selected !== '' && $value === $selected ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $isSelected . '>' . e($row[$labelKey]) . '</option>';
    }
    return $html;
}

function stat_card(string $label, string $value, string $caption, string $icon = '•'): string
{
    return '<article class="stat-card"><div class="stat-head"><span>' . e($label)
        . '</span><span class="stat-icon" aria-hidden="true">' . e($icon) . '</span></div><strong>'
        . e($value) . '</strong><p>' . e($caption) . '</p></article>';
}

function panel(string $title, string $description, string $body, string $extraClass = ''): string
{
    return '<section class="panel ' . e($extraClass) . '"><div class="panel-title"><div><h2>'
        . e($title) . '</h2><p>' . e($description) . '</p></div></div>' . $body . '</section>';
}

/** Neutralise spreadsheet formula injection before writing CSV cells. */
function csv_cell(mixed $value): string
{
    $cell = (string)$value;
    return preg_match('/^[\t\r ]*[=+\-@]/', $cell) ? "'" . $cell : $cell;
}

function format_hours(float|string|null $hours): string
{
    return number_format((float)$hours, 2);
}

function format_datetime(?string $value, string $format = 'M j, Y g:i A'): string
{
    if (!$value) {
        return '—';
    }
    $time = strtotime($value);
    return $time ? date($format, $time) : '—';
}

/* ------------------------------------------------------------------ *
 * Domain helpers
 * ------------------------------------------------------------------ */

function role_label(string $role): string
{
    return [
        'student'     => 'Student Intern',
        'supervisor'  => 'Company Supervisor',
        'coordinator' => 'Internship Coordinator',
    ][$role] ?? 'User';
}

/** Sum approved and pending rendered hours for one student. */
function attendance_totals(PDO $db, int $studentId): array
{
    $row = query_one(
        $db,
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'approved' THEN rendered_hours ELSE 0 END), 0) AS approved,
            COALESCE(SUM(CASE WHEN status = 'pending'  THEN rendered_hours ELSE 0 END), 0) AS pending
         FROM attendance_records
         WHERE student_id = ? AND time_out IS NOT NULL",
        [$studentId]
    );
    return [
        'approved' => (float)($row['approved'] ?? 0),
        'pending'  => (float)($row['pending'] ?? 0),
    ];
}

/** Remaining and percentage progress given approved and required hours. */
function progress_values(float $approvedHours, float $requiredHours): array
{
    $remaining = max(0.0, $requiredHours - $approvedHours);
    $percent = $requiredHours > 0 ? min(100.0, ($approvedHours / $requiredHours) * 100) : 0.0;
    return [$remaining, $percent];
}

/** Make sure a student has a personal attendance QR token, generating one if needed. */
function ensure_attendance_token(PDO $db, array $student): array
{
    if (!empty($student['attendance_token'])) {
        return $student;
    }
    $token = random_token(32);
    $db->prepare('UPDATE student_profiles SET attendance_token = ? WHERE id = ?')
        ->execute([$token, (int)$student['id']]);
    $student['attendance_token'] = $token;
    return $student;
}

/**
 * Load everything the ID card needs: the student profile, the owning user
 * account and the active placement (company + supervisor).
 */
function student_identity(PDO $db, int $studentId): ?array
{
    $student = query_one(
        $db,
        'SELECT sp.*, u.id AS user_id, u.full_name, u.email, u.avatar
         FROM student_profiles sp
         JOIN users u ON u.id = sp.user_id
         WHERE sp.id = ?',
        [$studentId]
    );
    if (!$student) {
        return null;
    }

    $student = ensure_attendance_token($db, $student);

    return [
        'student'   => $student,
        'placement' => active_placement($db, (int)$student['id']),
    ];
}

/** Human readable validity range printed on the ID card. */
function id_validity_text(?array $placement): string
{
    if (!$placement) {
        return 'No active placement';
    }
    $start = format_datetime((string)$placement['start_date'], 'M j, Y');
    if (empty($placement['end_date'])) {
        return 'Since ' . $start;
    }
    return $start . ' - ' . format_datetime((string)$placement['end_date'], 'M j, Y');
}

/**
 * Student intern ID card. The QR encodes the attendance token; the name and
 * details sit beside it so the card reads like a real school ID. The same
 * markup is used on the student's MyQR page and in the scan popup, and the
 * data-* attributes feed the PNG export in assets/js/app.js.
 */
function student_id_card(array $identity, array $options = []): string
{
    $student    = $identity['student'];
    $placement  = $identity['placement'] ?? null;
    $name       = trim((string)($student['full_name'] ?? '')) ?: 'Student Intern';
    $number     = (string)($student['student_number'] ?? '-');
    $course     = (string)($student['course'] ?? 'BS Information Technology');
    $year       = (string)($student['year_level'] ?? '4th Year');
    $company    = (string)($placement['company'] ?? 'Not yet assigned');
    $supervisor = (string)($placement['supervisor_name'] ?? 'To be assigned');
    $valid      = id_validity_text($placement);
    $token      = (string)($student['attendance_token'] ?? '');
    $avatar     = !empty($student['avatar'])
        ? url('avatar.php?id=' . (int)$student['user_id'])
        : '';
    $qrId       = (string)($options['qr_id'] ?? ('id-qr-' . (int)$student['id']));
    $extraClass = (string)($options['class'] ?? '');
    $initials   = strtoupper(mb_substr($name, 0, 2));

    static $qrScriptLoaded = false;
    $qrScript = '';
    if (!$qrScriptLoaded) {
        $qrScriptLoaded = true;
        $qrScript = '<script src="' . e(url('assets/js/qrcode.min.js')) . '"></script>';
    }

    $photo = $avatar !== ''
        ? '<img class="student-id-photo-img" src="' . e($avatar) . '" alt="' . e($name) . '">'
        : '<span class="student-id-initials">' . e($initials) . '</span>';

    return $qrScript
        . '<article class="student-id ' . e($extraClass) . '" data-id-card'
        . ' data-name="' . e($name) . '"'
        . ' data-number="' . e($number) . '"'
        . ' data-course="' . e($course) . '"'
        . ' data-year="' . e($year) . '"'
        . ' data-company="' . e($company) . '"'
        . ' data-supervisor="' . e($supervisor) . '"'
        . ' data-valid="' . e($valid) . '"'
        . ' data-token="' . e($token) . '"'
        . ' data-avatar="' . e($avatar) . '"'
        . ' data-logo="' . e(url('assets/images/logo.png')) . '">'

        . '<div class="student-id-band">'
        . '<span class="student-id-mark">ITS</span>'
        . '<span class="student-id-brand"><strong>InternTrack</strong>'
        . '<small>Student Intern Identification</small></span>'
        . '<span class="student-id-tag">' . e($course) . ' &middot; ' . e($year) . '</span>'
        . '</div>'

        . '<div class="student-id-body">'
        . '<div class="student-id-photo">' . $photo . '</div>'
        . '<div class="student-id-info">'
        . '<p class="student-id-kicker">Student Intern</p>'
        . '<h3>' . e($name) . '</h3>'
        . '<ul class="student-id-facts">'
        . '<li><span>Student No.</span><strong>' . e($number) . '</strong></li>'
        . '<li><span>Program</span><strong>' . e($course) . ' &middot; ' . e($year) . '</strong></li>'
        . '<li><span>Company</span><strong>' . e($company) . '</strong></li>'
        . '<li><span>Supervisor</span><strong>' . e($supervisor) . '</strong></li>'
        . '</ul></div>'
        . '<div class="student-id-qr">'
        . '<div class="student-id-qr-tile"><div id="' . e($qrId) . '" data-qr-token="' . e($token) . '" data-qr-size="160"></div></div>'
        . '<span class="student-id-qr-copy"><strong>' . e($name) . '</strong>'
        . '<small>Scan for attendance</small></span>'
        . '</div></div>'

        . '<div class="student-id-foot">'
        . '<span>Valid: ' . e($valid) . '</span>'
        . '<code>' . ($token !== '' ? 'TOKEN ' . e($token) : '') . '</code>'
        . '</div></article>';
}

/** Remember the scanned student so the ID card pops up after the redirect. */
function remember_scan_result(int $studentId, string $message, string $type = 'success'): void
{
    $_SESSION['scan_result'] = [
        'student_id' => $studentId,
        'message'    => $message,
        'type'       => $type,
    ];
}

/** Take (and clear) the pending scan result. */
function take_scan_result(): ?array
{
    $result = $_SESSION['scan_result'] ?? null;
    unset($_SESSION['scan_result']);

    return is_array($result) ? $result : null;
}

/** ID-card popup shown right after a coordinator or supervisor scans a QR. */
function scan_result_modal(PDO $db, array $result): string
{
    $identity = student_identity($db, (int)($result['student_id'] ?? 0));
    if (!$identity) {
        return '';
    }

    $type = in_array($result['type'] ?? '', ['success', 'error'], true) ? $result['type'] : 'success';

    return '<div class="id-modal" data-modal role="dialog" aria-modal="true" aria-label="Student identification">'
        . '<div class="id-modal-card">'
        . '<button class="id-modal-close" type="button" data-modal-close aria-label="Close">&times;</button>'
        . '<div class="notice ' . e($type) . '">' . e((string)($result['message'] ?? '')) . '</div>'
        . student_id_card($identity, [
            'qr_id' => 'scan-qr-' . (int)$identity['student']['id'],
            'class' => 'in-modal',
        ])
        . '<div class="id-modal-actions">'
        . '<button class="button primary" type="button" data-download-id>Download ID card (PNG)</button>'
        . '<button class="button secondary" type="button" data-modal-close>Close</button>'
        . '</div></div></div>';
}

/* ------------------------------------------------------------------ *
 * Profile photos
 * ------------------------------------------------------------------ */

/** Store an uploaded profile photo for a user and return the stored name. */
function save_avatar(PDO $db, int $userId, array $file): string
{
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Choose an image file to upload.');
    }
    if ((int)$file['size'] > 2 * 1048576) {
        throw new InvalidArgumentException('The profile photo must be 2 MB or smaller.');
    }
    $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$extension])) {
        throw new InvalidArgumentException('The profile photo must be a JPG or PNG image.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime !== $allowed[$extension]) {
        throw new InvalidArgumentException('That file is not a valid JPG or PNG image.');
    }

    $dir = UPLOAD_PATH . '/avatars';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('The avatar folder could not be created.');
    }

    $stored = 'u' . $userId . '-' . random_token(12) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        throw new RuntimeException('The profile photo could not be stored.');
    }

    $old = query_value($db, 'SELECT avatar FROM users WHERE id = ?', [$userId]);
    $db->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$stored, $userId]);
    if ($old && is_file($dir . '/' . basename((string)$old))) {
        @unlink($dir . '/' . basename((string)$old));
    }
    return $stored;
}

/** Remove a user's profile photo. */
function remove_avatar(PDO $db, int $userId): void
{
    $old = query_value($db, 'SELECT avatar FROM users WHERE id = ?', [$userId]);
    if ($old) {
        $path = UPLOAD_PATH . '/avatars/' . basename((string)$old);
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $db->prepare('UPDATE users SET avatar = NULL WHERE id = ?')->execute([$userId]);
}

/** Avatar markup: the uploaded photo, or initials when none is set. */
function avatar_html(array $user, string $class = 'avatar'): string
{
    $name = ($user['full_name'] ?? '') !== '' ? $user['full_name'] : ($user['username'] ?? '?');
    $initials = strtoupper(mb_substr($name, 0, 2));
    if (!empty($user['avatar'])) {
        return '<img class="' . e($class) . ' avatar-img" src="'
            . e(url('avatar.php?id=' . (int)$user['id'])) . '" alt="' . e($name) . '">';
    }
    return '<span class="' . e($class) . '">' . e($initials) . '</span>';
}

/** Profile photo upload panel used by every profile page. */
function profile_photo_panel(array $user, string $selfPath): string
{
    $name = ($user['full_name'] ?? '') !== '' ? $user['full_name'] : ($user['username'] ?? '?');
    $preview = !empty($user['avatar'])
        ? '<img class="avatar-preview" src="' . e(url('avatar.php?id=' . (int)$user['id'])) . '" alt="Profile photo">'
        : '<span class="avatar-preview initials">' . e(strtoupper(mb_substr($name, 0, 2))) . '</span>';

    $form = form_start($selfPath, ['enctype' => 'multipart/form-data'])
        . '<input type="hidden" name="op" value="upload_avatar">'
        . '<label>Profile photo (JPG or PNG, max 2 MB)'
        . '<input type="file" name="avatar" accept=".jpg,.jpeg,.png" required></label>'
        . '<button class="button primary">Upload photo</button></form>';
    if (!empty($user['avatar'])) {
        $form .= form_start($selfPath)
            . '<input type="hidden" name="op" value="remove_avatar">'
            . '<button class="button secondary" data-confirm="Remove your profile photo?">Remove photo</button></form>';
    }

    return '<div class="profile-photo">' . $preview . '<div class="profile-photo-form">' . $form . '</div></div>';
}

/** Active placement for a student record id. */
function active_placement(PDO $db, int $studentId): ?array
{
    return query_one(
        $db,
        "SELECT i.*, c.name AS company, c.address AS company_address,
                c.contact_person, c.contact_email,
                u.full_name AS supervisor_name, u.email AS supervisor_email,
                sup.job_title AS supervisor_title
         FROM internship_placements i
         JOIN companies c ON c.id = i.company_id
         LEFT JOIN supervisors sup ON sup.id = i.supervisor_id
         LEFT JOIN users u ON u.id = sup.user_id
         WHERE i.student_id = ? AND i.status = 'active'
         ORDER BY i.id DESC LIMIT 1",
        [$studentId]
    );
}

/* ------------------------------------------------------------------ *
 * Notifications and audit trail
 * ------------------------------------------------------------------ */

function notify_user(PDO $db, int $userId, string $title, string $body = ''): void
{
    try {
        $db->prepare('INSERT INTO notifications (user_id, title, body) VALUES (?, ?, ?)')
            ->execute([$userId, substr($title, 0, 180), substr($body, 0, 1000)]);
    } catch (PDOException $error) {
        // Skip notifications for accounts that no longer exist.
        error_log('ITS notification skipped: ' . $error->getMessage());
    }
}

function record_activity(PDO $db, ?int $userId, string $action, string $details = ''): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $action = substr($action, 0, 120);
    $details = substr($details, 0, 1000);

    try {
        $db->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $action, $details, $ip]);
    } catch (PDOException $error) {
        // A stale session (for example after a database reset or account
        // deletion) can reference a user that no longer exists. Audit
        // logging must never break the request, so retry without the user.
        try {
            $db->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (NULL, ?, ?, ?)')
                ->execute([$action, $details, $ip]);
        } catch (Throwable $ignored) {
            error_log('ITS audit log failed: ' . $error->getMessage());
        }
    }
}

/* ------------------------------------------------------------------ *
 * Error rendering
 * ------------------------------------------------------------------ */

/** Full-page notice shown when MySQL is unreachable or not yet imported. */
function render_db_setup_page(): never
{
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Database setup required | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= e(APP_VERSION) ?>"></head>
<body class="setup-page"><main class="setup-card"><p class="eyebrow"><?= e(strtoupper(APP_SHORT_NAME)) ?></p>
<h1>Database connection needed</h1>
<p>Start <strong>MySQL</strong> in the XAMPP Control Panel, then import <code>database.sql</code> using phpMyAdmin.
The script creates the <code><?= e(DB_NAME) ?></code> database and sample records.</p>
<p>If your credentials differ from the XAMPP defaults, set the <code>ITS_DB_HOST</code>, <code>ITS_DB_PORT</code>,
<code>ITS_DB_NAME</code>, <code>ITS_DB_USER</code> and <code>ITS_DB_PASSWORD</code> environment variables, or edit
<code>config/config.php</code>.</p>
<p><a class="button primary" href="<?= e(url('index.php')) ?>">Try again</a></p></main></body></html><?php
    exit;
}

function render_error_page(string $title, string $message): never
{
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= e(APP_VERSION) ?>"></head>
<body class="setup-page"><main class="setup-card"><p class="eyebrow"><?= e(strtoupper(APP_SHORT_NAME)) ?></p>
<h1><?= e($title) ?></h1><p><?= e($message) ?></p>
<p><a class="button secondary" href="<?= e(url('index.php')) ?>">Return to sign in</a></p></main></body></html><?php
    exit;
}
