<?php
// Daily expenses. Needs the "expenses.daily" permission (admins have it for every branch).
// Staff can add and view; only admins can delete, so past entries can't be quietly changed.
//   GET    expenses.php?date=YYYY-MM-DD[&branch=id]                      -> { date, total, byCategory, expenses, categories }
//   POST   expenses.php { date?, category, description, amount, branch? } -> 201 { expense }   (branch: admins only)
//   DELETE expenses.php?id=N                                             admin only
require __DIR__ . '/config.php';

function list_expenses(): never {
    $user = require_permission('expenses.daily');
    $date = (string)($_GET['date'] ?? date('Y-m-d'));
    if (!is_ymd($date)) json_response(422, ['error' => 'Choose a valid date.']);
    [$where, $params] = branch_condition($user, 'e.branch_id');
    $stmt = db()->prepare("SELECT e.id, e.branch_id, e.expense_date, e.category, e.description, e.amount, e.created_at, br.name AS branch, u.name AS recorded_by
        FROM expenses e JOIN branches br ON br.id = e.branch_id LEFT JOIN users u ON u.id = e.recorded_by
        WHERE e.expense_date = ? $where ORDER BY e.created_at, e.id");
    $stmt->execute(array_merge([$date], $params));
    $total = 0.0; $byCategory = [];
    $rows = array_map(function ($r) use (&$total, &$byCategory) {
        $amount = (float)$r['amount'];
        $total += $amount;
        $byCategory[$r['category']] = ($byCategory[$r['category']] ?? 0) + $amount;
        return ['id' => (int)$r['id'], 'branchId' => $r['branch_id'], 'date' => $r['expense_date'], 'category' => $r['category'], 'description' => $r['description'],
            'amount' => $amount, 'branch' => $r['branch'], 'recordedBy' => $r['recorded_by'], 'createdAt' => date(DATE_ATOM, strtotime($r['created_at']))];
    }, $stmt->fetchAll());
    arsort($byCategory);
    json_response(200, ['date' => $date, 'total' => round($total, 2), 'byCategory' => $byCategory, 'expenses' => $rows, 'categories' => EXPENSE_CATEGORIES]);
}

function expense_audit(): never {
    require_role('admin');
    $date = (string)($_GET['date'] ?? date('Y-m-d'));
    if (!is_ymd($date)) json_response(422, ['error' => 'Choose a valid date.']);
    $branch = trim((string)($_GET['branch'] ?? ''));
    $branchFilter = $branch !== '' ? " AND (JSON_UNQUOTE(JSON_EXTRACT(a.old_values, '$.branchId')) = ? OR JSON_UNQUOTE(JSON_EXTRACT(a.new_values, '$.branchId')) = ?)" : '';
    $stmt = db()->prepare("SELECT a.id, a.expense_id, a.action, a.old_values, a.new_values, a.changed_at, u.name AS changed_by
        FROM expense_audit a LEFT JOIN users u ON u.id = a.changed_by
        WHERE DATE(a.changed_at) = ? $branchFilter ORDER BY a.changed_at DESC, a.id DESC LIMIT 100");
    $stmt->execute($branch !== '' ? [$date, $branch, $branch] : [$date]);
    $rows = array_map(static function (array $r): array {
        return [
            'id' => (int)$r['id'],
            'expenseId' => (int)$r['expense_id'],
            'action' => $r['action'],
            'old' => json_decode($r['old_values'], true),
            'new' => $r['new_values'] !== null ? json_decode($r['new_values'], true) : null,
            'changedAt' => date(DATE_ATOM, strtotime($r['changed_at'])),
            'changedBy' => $r['changed_by'] ?? 'Former user',
        ];
    }, $stmt->fetchAll());
    json_response(200, ['audit' => $rows]);
}

function expense_values(int $id): ?array {
    $stmt = db()->prepare('SELECT id, branch_id, expense_date, category, description, amount FROM expenses WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'id' => (int)$row['id'], 'branchId' => $row['branch_id'], 'date' => $row['expense_date'],
        'category' => $row['category'], 'description' => $row['description'], 'amount' => (float)$row['amount'],
    ];
}

function validate_expense_input(array $in): array {
    $date = (string)($in['date'] ?? '');
    $category = (string)($in['category'] ?? '');
    $description = trim((string)($in['description'] ?? ''));
    $amount = parse_amount($in['amount'] ?? '');
    $branchId = trim((string)($in['branch'] ?? ''));
    $errors = [];
    if (!is_ymd($date) || $date > date('Y-m-d')) $errors[] = 'Choose a date up to today.';
    if (!in_array($category, EXPENSE_CATEGORIES, true)) $errors[] = 'Choose a category.';
    if ($description === '' || mb_strlen($description) > 255) $errors[] = 'Describe the expense (up to 255 characters).';
    if ($amount === null) $errors[] = 'Enter an amount (up to ₱1,000,000, at most 2 decimals).';
    $stmt = db()->prepare('SELECT id FROM branches WHERE id = ? AND active = 1');
    $stmt->execute([$branchId]);
    if (!$stmt->fetchColumn()) $errors[] = 'Choose a branch.';
    if ($errors) json_response(422, ['error' => implode(' ', $errors)]);
    return ['branchId' => $branchId, 'date' => $date, 'category' => $category, 'description' => $description, 'amount' => $amount];
}

function add_expense(): never {
    $user = require_permission('expenses.daily');
    $in = json_body();
    $date = (string)($in['date'] ?? date('Y-m-d'));
    $category = (string)($in['category'] ?? '');
    $description = trim((string)($in['description'] ?? ''));
    $amount = parse_amount($in['amount'] ?? '');
    $branchId = branch_for_new_record($user, $in['branch'] ?? '');

    $errors = [];
    if (!is_ymd($date) || $date > date('Y-m-d')) $errors[] = 'Choose a date up to today.';
    if (!in_array($category, EXPENSE_CATEGORIES, true)) $errors[] = 'Choose a category.';
    if ($description === '' || mb_strlen($description) > 255) $errors[] = 'Describe the expense (up to 255 characters).';
    if ($amount === null) $errors[] = 'Enter an amount (up to ₱1,000,000, at most 2 decimals).';
    if (!$branchId) $errors[] = 'Choose a branch.';
    if ($errors) json_response(422, ['error' => implode(' ', $errors)]);

    db()->prepare('INSERT INTO expenses (branch_id, expense_date, category, description, amount, recorded_by) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$branchId, $date, $category, $description, $amount, $user['id']]);
    json_response(201, ['expense' => ['id' => (int)db()->lastInsertId(), 'date' => $date, 'category' => $category, 'amount' => (float)$amount]]);
}

function update_expense(): never {
    $user = require_role('admin');
    $id = (int)($_GET['id'] ?? 0);
    $old = expense_values($id);
    if (!$old) json_response(404, ['error' => 'Expense not found.']);
    $in = json_body();
    $values = validate_expense_input($in);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE expenses SET branch_id = ?, expense_date = ?, category = ?, description = ?, amount = ? WHERE id = ?')
            ->execute([$values['branchId'], $values['date'], $values['category'], $values['description'], $values['amount'], $id]);
        $new = expense_values($id);
        $pdo->prepare("INSERT INTO expense_audit (expense_id, action, old_values, new_values, changed_by) VALUES (?, 'Updated', ?, ?, ?)")
            ->execute([$id, json_encode($old, JSON_THROW_ON_ERROR), json_encode($new, JSON_THROW_ON_ERROR), $user['id']]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    json_response(200, ['expense' => $new]);
}

function delete_expense(): never {
    $user = require_role('admin');
    $id = (int)($_GET['id'] ?? 0);
    $old = expense_values($id);
    if (!$old) json_response(404, ['error' => 'Expense not found.']);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO expense_audit (expense_id, action, old_values, changed_by) VALUES (?, 'Deleted', ?, ?)")
            ->execute([$id, json_encode($old, JSON_THROW_ON_ERROR), $user['id']]);
        $pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    json_response(200, ['ok' => true]);
}

// Dispatch last, so every function above is defined first.
try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET' && ($_GET['action'] ?? '') === 'audit') expense_audit();
    if ($method === 'GET') list_expenses();
    if ($method === 'POST') add_expense();
    if ($method === 'PATCH') update_expense();
    if ($method === 'DELETE') delete_expense();
    json_response(405, ['error' => 'Unsupported request.']);
} catch (PDOException $e) {
    error_log('expenses.php: ' . $e->getMessage());
    json_response(500, ['error' => 'Expenses are unavailable right now. Please try again shortly.']);
}
