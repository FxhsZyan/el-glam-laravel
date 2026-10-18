<?php
// Inventory API (staff & admin, signed in). Staff only ever see and use their own branch's stock.
//   GET  inventory.php?action=list        -> { items: [...] }
//   GET  inventory.php?action=movements   -> { movements: [...] }  (newest first)
//   POST inventory.php { action: "use", itemId, qty, note?, category? }               staff & admin
//   POST inventory.php { action: "create", name, category, branch, qty, reorder, unit } admin only
//   POST inventory.php { action: "adjust", itemId, mode: "set"|"add"|"remove", qty }   admin only
require __DIR__ . '/config.php';

const ITEM_SELECT = "SELECT i.id, i.name, i.category, i.unit, i.qty, i.reorder_level, i.branch_id, b.name AS branch
    FROM inventory_items i JOIN branches b ON b.id = i.branch_id";

function format_item(array $r): array {
    return [
        'id' => (int)$r['id'],
        'code' => sprintf('ST-%03d', $r['id']),
        'name' => $r['name'],
        'category' => $r['category'],
        'unit' => $r['unit'],
        'qty' => (int)$r['qty'],
        'reorder' => (int)$r['reorder_level'],
        'branchId' => $r['branch_id'],
        'branch' => $r['branch'],
    ];
}

// Staff only see their own branch: other branches' items look like they don't exist.
function branch_scope(array $user, string $alias): array {
    return $user['role'] === 'staff' ? [" WHERE $alias.branch_id = ?", [$user['branchId']]] : ['', []];
}

function list_items(): never {
    $user = require_role('staff', 'admin');
    [$where, $params] = branch_scope($user, 'i');
    $stmt = db()->prepare(ITEM_SELECT . $where . ' ORDER BY b.name, i.name');
    $stmt->execute($params);
    json_response(200, ['items' => array_map('format_item', $stmt->fetchAll())]);
}

function list_movements(): never {
    $user = require_role('staff', 'admin');
    [$where, $params] = branch_scope($user, 'm');
    $stmt = db()->prepare("SELECT m.id, m.item_id, i.name AS item_name, i.unit, b.name AS branch, m.type, m.qty, m.note, m.created_at, u.name AS by_name
        FROM stock_movements m JOIN inventory_items i ON i.id = m.item_id JOIN branches b ON b.id = m.branch_id LEFT JOIN users u ON u.id = m.user_id
        $where ORDER BY m.created_at DESC, m.id DESC LIMIT 100");
    $stmt->execute($params);
    $rows = array_map(fn($r) => [
        'id' => (int)$r['id'],
        'itemId' => (int)$r['item_id'],
        'itemName' => $r['item_name'],
        'unit' => $r['unit'],
        'branch' => $r['branch'],
        'type' => $r['type'],
        'qty' => abs((int)$r['qty']),
        'note' => $r['note'],
        'date' => date(DATE_ATOM, strtotime($r['created_at'])),
        'by' => $r['by_name'],
    ], $stmt->fetchAll());
    json_response(200, ['movements' => $rows]);
}

// Locks the item row for the rest of the transaction so two people can't overdraw the same stock.
function lock_item(int $id, array $user): array {
    $stmt = db()->prepare(ITEM_SELECT . ' WHERE i.id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item || ($user['role'] === 'staff' && $item['branch_id'] !== $user['branchId'])) {
        db()->rollBack();
        json_response(404, ['error' => 'Inventory item not found.']);
    }
    return $item;
}

function record_movement(array $item, string $type, int $signedQty, ?string $note, array $user): void {
    db()->prepare('INSERT INTO stock_movements (item_id, branch_id, type, qty, note, user_id) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$item['id'], $item['branch_id'], $type, $signedQty, $note, $user['id']]);
}

function whole_number($value): ?int {
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]) === false ? null : (int)$value;
}

function fail_in_transaction(int $status, string $message): never {
    if (db()->inTransaction()) db()->rollBack();
    json_response($status, ['error' => $message]);
}

function use_stock(array $in): never {
    $user = require_role('staff', 'admin');
    $qty = whole_number($in['qty'] ?? null);
    if (!$qty) json_response(422, ['error' => 'Enter a quantity greater than zero.']);
    $note = trim((string)($in['note'] ?? ''));
    $category = trim((string)($in['category'] ?? ''));
    if ($note === '') $note = $category !== '' ? "Used for $category" : 'Supplies used';
    $note = mb_substr($note, 0, 255);

    db()->beginTransaction();
    $item = lock_item((int)($in['itemId'] ?? 0), $user);
    if ($qty > (int)$item['qty']) fail_in_transaction(409, "Only {$item['qty']} {$item['unit']} available.");
    db()->prepare('UPDATE inventory_items SET qty = qty - ? WHERE id = ?')->execute([$qty, $item['id']]);
    record_movement($item, 'Stock Out', -$qty, $note, $user);
    db()->commit();
    respond_with_item((int)$item['id']);
}

function create_item(array $in): never {
    $user = require_role('admin');
    $name = trim((string)($in['name'] ?? ''));
    $category = trim((string)($in['category'] ?? ''));
    $unit = trim((string)($in['unit'] ?? '')) ?: 'units';
    $qty = whole_number($in['qty'] ?? null);
    $reorder = whole_number($in['reorder'] ?? null);
    $stmt = db()->prepare('SELECT id FROM branches WHERE name = ? AND active = 1');
    $stmt->execute([trim((string)($in['branch'] ?? ''))]);
    $branchId = $stmt->fetchColumn();

    $errors = [];
    if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Enter an item name.';
    if ($category === '' || mb_strlen($category) > 60) $errors[] = 'Choose a category.';
    if (mb_strlen($unit) > 30) $errors[] = 'Use a shorter unit name.';
    if (!$branchId) $errors[] = 'Choose a branch.';
    if ($qty === null || $reorder === null) $errors[] = 'Quantities must be whole numbers of 0 or more.';
    if ($errors) json_response(422, ['error' => implode(' ', $errors)]);

    try {
        db()->beginTransaction();
        db()->prepare('INSERT INTO inventory_items (branch_id, name, category, unit, qty, reorder_level) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$branchId, $name, $category, $unit, $qty, $reorder]);
        $id = (int)db()->lastInsertId();
        if ($qty > 0) record_movement(['id' => $id, 'branch_id' => $branchId], 'Stock In', $qty, 'Item added', $user);
        db()->commit();
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) fail_in_transaction(409, 'That branch already has an item with this name.');
        throw $e;
    }
    respond_with_item($id, 201);
}

function adjust_item(array $in): never {
    $user = require_role('admin');
    $mode = $in['mode'] ?? '';
    $qty = whole_number($in['qty'] ?? null);
    $note = mb_substr(trim((string)($in['note'] ?? '')), 0, 255);
    if (!in_array($mode, ['set', 'add', 'remove'], true) || $qty === null) {
        json_response(422, ['error' => 'Enter a whole-number quantity of 0 or more.']);
    }
    if ($note === '') json_response(422, ['error' => 'Add a reason for the stock adjustment.']);

    db()->beginTransaction();
    $item = lock_item((int)($in['itemId'] ?? 0), $user);
    $current = (int)$item['qty'];
    $new = match ($mode) { 'set' => $qty, 'add' => $current + $qty, 'remove' => max(0, $current - $qty) };
    $change = $new - $current;
    if ($change !== 0) {
        db()->prepare('UPDATE inventory_items SET qty = ? WHERE id = ?')->execute([$new, $item['id']]);
        $type = $mode === 'set' ? 'Adjustment' : ($change > 0 ? 'Stock In' : 'Stock Out');
        record_movement($item, $type, $change, $note, $user);
    }
    db()->commit();
    respond_with_item((int)$item['id']);
}

function respond_with_item(int $id, int $status = 200): never {
    $stmt = db()->prepare(ITEM_SELECT . ' WHERE i.id = ?');
    $stmt->execute([$id]);
    json_response($status, ['item' => format_item($stmt->fetch())]);
}

// Dispatch last, so every constant and function above is defined first.
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    if ($method === 'GET' && $action === 'list') list_items();
    if ($method === 'GET' && $action === 'movements') list_movements();
    if ($method === 'POST') {
        $in = json_body();
        match ($in['action'] ?? '') {
            'use' => use_stock($in),
            'create' => create_item($in),
            'adjust' => adjust_item($in),
            default => json_response(400, ['error' => 'Unknown action.']),
        };
    }
    json_response(405, ['error' => 'Unsupported request.']);
} catch (PDOException $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log('inventory.php: ' . $e->getMessage());
    json_response(500, ['error' => 'Inventory is unavailable right now. Please try again shortly.']);
}
