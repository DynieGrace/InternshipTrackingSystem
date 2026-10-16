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
 * Render a student's personal attendance QR. The student displays it; the
 * instructor or authorized personnel scans it.
 */
function attendance_qr_panel(array $student, string $elementId = 'my-qr'): string
{
    $token = (string)($student['attendance_token'] ?? '');
    return '<div class="qr-created"><div id="' . e($elementId) . '" data-token="' . e($token) . '"></div>'
        . '<div><p><strong>Show this QR code</strong> to your <strong>instructor (coordinator)</strong> or to your '
        . '<strong>company supervisor / authorized personnel</strong>. They scan it to record your time in and time out. '
        . 'You do not scan this yourself. If the image does not load, give them the token below.</p>'
        . '<code>' . e($token) . '</code></div></div>'
        . '<script src="' . e(url('assets/js/qrcode.min.js')) . '"></script>'
        . '<script>document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("' . e($elementId) . '");'
        . 'if(b&&window.QRCode){new QRCode(b,{text:b.dataset.token,width:190,height:190});}});</script>';
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
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>"></head>
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
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>"></head>
<body class="setup-page"><main class="setup-card"><p class="eyebrow"><?= e(strtoupper(APP_SHORT_NAME)) ?></p>
<h1><?= e($title) ?></h1><p><?= e($message) ?></p>
<p><a class="button secondary" href="<?= e(url('index.php')) ?>">Return to sign in</a></p></main></body></html><?php
    exit;
}
