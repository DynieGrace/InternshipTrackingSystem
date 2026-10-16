<?php
/**
 * Coordinator: manage partner companies.
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
        $op = (string)($_POST['op'] ?? '');

        if ($op === 'create_company' || $op === 'update_company') {
            $name = post_string('name', 180);
            $email = post_string('contact_email', 190);
            if ($name === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                throw new InvalidArgumentException('Enter a company name and a valid contact email.');
            }
            $values = [$name, post_string('address', 255), post_string('contact_person', 120), $email, post_string('contact_phone', 40)];

            if ($op === 'create_company') {
                $db->prepare('INSERT INTO companies (name, address, contact_person, contact_email, contact_phone) VALUES (?, ?, ?, ?, ?)')
                    ->execute($values);
                record_activity($db, (int)$user['id'], 'create_company', $name);
                flash_redirect('coordinator/companies.php', 'Company saved.');
            }
            $id = post_id('company_id');
            $values[] = $id;
            $db->prepare('UPDATE companies SET name = ?, address = ?, contact_person = ?, contact_email = ?, contact_phone = ? WHERE id = ?')
                ->execute($values);
            record_activity($db, (int)$user['id'], 'update_company', $name);
            flash_redirect('coordinator/companies.php', 'Company updated.');
        }

        if ($op === 'set_status') {
            $id = post_id('company_id');
            $status = post_string('status', 10);
            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException('Invalid status.');
            }
            $db->prepare('UPDATE companies SET status = ? WHERE id = ?')->execute([$status, $id]);
            record_activity($db, (int)$user['id'], 'company_status', $status . ' #' . $id);
            flash_redirect('coordinator/companies.php', 'Company status updated.');
        }

        throw new InvalidArgumentException('Unknown action.');
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        error_log('ITS coordinator companies failed: ' . $error->getMessage());
        flash('error', 'The company could not be saved.');
    }
    redirect('coordinator/companies.php');
}

$title = 'Companies';
$active = 'companies';
require __DIR__ . '/../includes/header.php';

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? query_one($db, 'SELECT * FROM companies WHERE id = ?', [$editId]) : null;

if ($editId && $edit) {
    echo panel('Edit company', 'Update ' . e($edit['name']) . '.',
        form_start('coordinator/companies.php')
        . '<input type="hidden" name="op" value="update_company">'
        . '<input type="hidden" name="company_id" value="' . (int)$edit['id'] . '">'
        . '<div class="form-grid">'
        . '<label>Company name<input name="name" maxlength="180" value="' . e($edit['name']) . '" required></label>'
        . '<label>Address<input name="address" maxlength="255" value="' . e($edit['address']) . '"></label>'
        . '<label>Contact person<input name="contact_person" maxlength="120" value="' . e($edit['contact_person']) . '"></label>'
        . '<label>Contact email<input name="contact_email" type="email" maxlength="190" value="' . e($edit['contact_email']) . '"></label>'
        . '<label>Contact phone<input name="contact_phone" maxlength="40" value="' . e($edit['contact_phone']) . '"></label>'
        . '</div><div class="stack-actions"><button class="button primary">Save changes</button>'
        . '<a class="button secondary" href="' . e(url('coordinator/companies.php')) . '">Cancel</a></div></form>');
} else {
    echo panel('Register a company', 'Add an internship partner.',
        form_start('coordinator/companies.php')
        . '<input type="hidden" name="op" value="create_company">'
        . '<div class="form-grid">'
        . '<label>Company name<input name="name" maxlength="180" required></label>'
        . '<label>Address<input name="address" maxlength="255"></label>'
        . '<label>Contact person<input name="contact_person" maxlength="120"></label>'
        . '<label>Contact email<input name="contact_email" type="email" maxlength="190"></label>'
        . '<label>Contact phone<input name="contact_phone" maxlength="40"></label>'
        . '</div><button class="button primary">Save company</button></form>');
}

$companies = query_all(
    $db,
    'SELECT c.*, (SELECT COUNT(*) FROM internship_placements i WHERE i.company_id = c.id AND i.status = "active") AS interns
    FROM companies c ORDER BY c.name'
);
$rows = [];
foreach ($companies as $row) {
    $toggle = $row['status'] === 'active'
        ? '<input type="hidden" name="status" value="inactive"><button class="button small secondary">Deactivate</button>'
        : '<input type="hidden" name="status" value="active"><button class="button small">Activate</button>';
    $actions = '<a class="button small" href="' . e(url('coordinator/companies.php?edit=' . (int)$row['id'])) . '">Edit</a> '
        . '<form method="post" action="' . e(url('coordinator/companies.php')) . '" class="inline-review">' . csrf_field()
        . '<input type="hidden" name="op" value="set_status"><input type="hidden" name="company_id" value="' . (int)$row['id'] . '">'
        . $toggle . '</form>';
    $rows[] = [
        e($row['name']),
        e($row['address'] ?: '—'),
        e($row['contact_person'] ?: '—') . ($row['contact_email'] ? '<div class="help-text">' . e($row['contact_email']) . '</div>' : ''),
        e($row['contact_phone'] ?: '—'),
        (string)$row['interns'],
        status_badge($row['status']),
        $actions,
    ];
}

echo '<div class="toolbar"><input type="search" class="grow" placeholder="Search companies…" data-table-filter="#companies-table"></div>';
echo '<div id="companies-table">' . table_html(['Company', 'Address', 'Contact', 'Phone', 'Interns', 'Status', 'Actions'], $rows, 'No companies have been added.') . '</div>';

require __DIR__ . '/../includes/footer.php';
