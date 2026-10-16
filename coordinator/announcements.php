<?php
/**
 * Coordinator: publish announcements and notifications by audience.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$db = database_or_null();
if (!$db instanceof PDO) {
    render_db_setup_page();
}

$user = require_role(['coordinator']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (($_POST['op'] ?? '') !== 'create_announcement') {
            throw new InvalidArgumentException('Unknown action.');
        }
        $audience = post_string('audience', 20);
        $title = post_string('title', 180);
        $body = post_string('body', 5000);
        if (!in_array($audience, ['all', 'student', 'supervisor', 'coordinator'], true)) {
            throw new InvalidArgumentException('Choose a valid audience.');
        }
        if ($title === '' || $body === '') {
            throw new InvalidArgumentException('Enter a title and a message.');
        }

        $db->prepare('INSERT INTO announcements (title, body, audience, created_by) VALUES (?, ?, ?, ?)')
            ->execute([$title, $body, $audience, (int)$user['id']]);

        $sql = 'SELECT id FROM users WHERE status = "active"';
        $params = [];
        if ($audience !== 'all') {
            $sql .= ' AND role = ?';
            $params[] = $audience;
        }
        foreach (query_all($db, $sql, $params) as $recipient) {
            notify_user($db, (int)$recipient['id'], $title, $body);
        }
        record_activity($db, (int)$user['id'], 'create_announcement', $title);
        flash_redirect('coordinator/announcements.php', 'Announcement published.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        error_log('ITS coordinator announcements failed: ' . $error->getMessage());
        flash('error', 'The announcement could not be published.');
    }
    redirect('coordinator/announcements.php');
}

$title = 'Announcements';
$active = 'announcements';
require __DIR__ . '/../includes/header.php';

echo panel('Publish an announcement', 'Recipients also receive an in-app notification.',
    form_start('coordinator/announcements.php')
    . '<input type="hidden" name="op" value="create_announcement">'
    . '<div class="form-grid">'
    . '<label>Title<input name="title" maxlength="180" required></label>'
    . '<label>Audience<select name="audience">'
    . '<option value="all">Everyone</option><option value="student">Students</option>'
    . '<option value="supervisor">Supervisors</option><option value="coordinator">Coordinators</option>'
    . '</select></label></div>'
    . '<label>Message<textarea name="body" rows="4" maxlength="5000" required></textarea></label>'
    . '<button class="button primary">Publish announcement</button></form>');

$announcements = query_all(
    $db,
    'SELECT a.title, a.body, a.audience, a.created_at, u.full_name FROM announcements a
     JOIN users u ON u.id = a.created_by ORDER BY a.created_at DESC LIMIT 100'
);
$rows = array_map(fn($a) => [
    e($a['title']),
    e($a['body']),
    e(ucfirst($a['audience'])),
    e($a['full_name']),
    e(format_datetime($a['created_at'])),
], $announcements);

echo panel('Published announcements', 'Most recent first.', 
    table_html(['Title', 'Message', 'Audience', 'Published by', 'Date'], $rows, 'No announcements yet.'));

require __DIR__ . '/../includes/footer.php';
