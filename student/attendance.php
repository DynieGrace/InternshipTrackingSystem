<?php
/**
 * Student attendance: the student displays a personal QR for the
 * instructor / authorized personnel to scan, a manual time in/time out
 * fallback, and a running list of rendered hours.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['student']);
$student = student_profile($db, (int)$user['id']);

// Ensure the student has a personal attendance QR token to display.
$student = $student ? ensure_attendance_token($db, $student) : null;

/**
 * Record a time-in or time-out for the signed-in student. All validation
 * happens here on the server; students can never edit their own hours.
 */
function student_attendance_action(PDO $db, array $student, string $mode): string
{
    $placement = active_placement($db, (int)$student['id']);
    if (!$placement) {
        throw new InvalidArgumentException('You have no active internship placement.');
    }

    $open = query_one($db, 'SELECT id FROM attendance_records WHERE student_id = ? AND time_out IS NULL ORDER BY id DESC LIMIT 1',
        [(int)$student['id']]);

    if ($mode === 'time_in') {
        if ($open) {
            throw new InvalidArgumentException('You already have an open time-in. Please time out first.');
        }
        $db->prepare("INSERT INTO attendance_records (placement_id, student_id, time_in, status) VALUES (?, ?, NOW(), 'pending')")
            ->execute([(int)$placement['id'], (int)$student['id']]);
        return 'Time-in recorded by the server.';
    }

    if ($mode === 'time_out') {
        if (!$open) {
            throw new InvalidArgumentException('No open time-in was found to time out.');
        }
        $db->prepare(
            "UPDATE attendance_records
             SET time_out = NOW(),
                 rendered_hours = ROUND(GREATEST(0, TIMESTAMPDIFF(SECOND, time_in, NOW()) / 3600), 2),
                 status = 'pending'
             WHERE id = ? AND time_out IS NULL"
        )->execute([(int)$open['id']]);
        return 'Time-out recorded. Rendered hours await verification.';
    }

    throw new InvalidArgumentException('Invalid attendance action.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $op = (string)($_POST['op'] ?? '');
        if (!$student) {
            throw new InvalidArgumentException('Your student profile is not set up yet.');
        }

        if ($op === 'attendance_start' || $op === 'attendance_stop') {
            $message = student_attendance_action($db, $student, $op === 'attendance_start' ? 'time_in' : 'time_out');
            record_activity($db, (int)$user['id'], $op, 'manual');
            flash_redirect('student/attendance.php', $message);
        }

        throw new InvalidArgumentException('Unknown action.');
    } catch (InvalidArgumentException $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('ITS student attendance failed: ' . $error->getMessage());
        flash('error', 'The attendance request could not be completed.');
    }
    redirect('student/attendance.php');
}

$title = 'Attendance & Hours';
$active = 'attendance';
require __DIR__ . '/../includes/header.php';

if (!$student) {
    echo panel('Attendance unavailable', 'Student profile missing.',
        '<div class="empty-state">Your student profile is not set up yet.</div>');
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$studentId = (int)$student['id'];
$placement = active_placement($db, $studentId);
$totals = attendance_totals($db, $studentId);
$required = (float)($placement['required_hours'] ?? 0);
[$remaining, $percent] = progress_values($totals['approved'], $required);
$isOpen = (bool)query_one($db, 'SELECT id FROM attendance_records WHERE student_id = ? AND time_out IS NULL', [$studentId]);

echo '<div class="stats-grid">';
echo stat_card('Approved hours', format_hours($totals['approved']), 'Verified sessions', '✓');
echo stat_card('Pending hours', format_hours($totals['pending']), 'Awaiting verification', '◷');
echo stat_card('Hours remaining', format_hours($remaining), format_hours($required) . ' required', '↗');
echo stat_card('Progress', number_format($percent, 1) . '%', 'Of required hours', '▤');
echo '</div>';

// The student displays this QR; the instructor (coordinator) or the
// company supervisor / authorized personnel scans it to record attendance.
echo panel('My attendance QR code', 'Present this to authorized personnel or your instructor to record your attendance.',
    attendance_qr_panel($student));

$clockForm = $placement
    ? form_start('student/attendance.php')
        . '<input type="hidden" name="op" value="' . ($isOpen ? 'attendance_stop' : 'attendance_start') . '">'
        . '<button class="button secondary">' . ($isOpen ? 'Time out now' : 'Time in now') . '</button>'
        . ($isOpen ? '<p class="help-text">You have an open time-in. Timing out records your rendered hours.</p>' : '')
        . '</form>'
    : '<div class="empty-state">You need an active placement before recording attendance.</div>';

echo panel('Manual fallback', 'Use this only when an instructor or supervisor cannot scan your QR. The server records the timestamp and it still needs verification.', $clockForm);

$records = query_all(
    $db,
    'SELECT time_in, time_out, rendered_hours, status, correction_reason
     FROM attendance_records WHERE student_id = ? ORDER BY time_in DESC LIMIT 200',
    [$studentId]
);
$rows = [];
foreach ($records as $row) {
    $rows[] = [
        e(format_datetime($row['time_in'])),
        e($row['time_out'] ? format_datetime($row['time_out']) : 'In progress'),
        e(format_hours($row['rendered_hours'])),
        status_badge($row['status']),
        e($row['correction_reason'] ?: '—'),
    ];
}
echo panel('My attendance records', 'Only approved sessions count toward your progress.',
    table_html(['Time in', 'Time out', 'Hours', 'Status', 'Coordinator note'], $rows, 'No attendance records yet.'));

require __DIR__ . '/../includes/footer.php';
