<?php
// Admin package catalogue and customer session balances.
require __DIR__ . '/config.php';

function list_packages(): never {
    require_role('admin');
    $plans = db()->query("SELECT p.id, p.name, p.service_id, s.name AS service, p.session_count, p.price, p.active
        FROM service_packages p LEFT JOIN services s ON s.id = p.service_id ORDER BY p.name")->fetchAll();
    $stmt = db()->prepare("SELECT cp.id, cp.package_id, cp.branch_id, b.name AS branch, cp.customer_name, cp.mobile,
            cp.sessions_total, cp.sessions_remaining, cp.amount_paid, cp.purchased_at, cp.active,
            p.name AS package, u.name AS sold_by
        FROM customer_packages cp JOIN service_packages p ON p.id = cp.package_id
        JOIN branches b ON b.id = cp.branch_id LEFT JOIN users u ON u.id = cp.sold_by
        ORDER BY cp.active DESC, cp.purchased_at DESC LIMIT 500");
    $stmt->execute();
    $customers = array_map(static fn(array $r): array => [
        'id' => (int)$r['id'], 'packageId' => (int)$r['package_id'], 'branchId' => $r['branch_id'],
        'branch' => $r['branch'], 'customer' => $r['customer_name'], 'mobile' => $r['mobile'],
        'sessionsTotal' => (int)$r['sessions_total'], 'sessionsRemaining' => (int)$r['sessions_remaining'],
        'amountPaid' => (float)$r['amount_paid'], 'purchasedAt' => date(DATE_ATOM, strtotime($r['purchased_at'])),
        'active' => (bool)$r['active'], 'package' => $r['package'], 'soldBy' => $r['sold_by'],
    ], $stmt->fetchAll());
    $redemptions = db()->query("SELECT r.id, r.redeemed_at, r.note, cp.customer_name, p.name AS package, b.name AS branch,
            u.name AS redeemed_by, bk.reference
        FROM package_redemptions r
        JOIN customer_packages cp ON cp.id = r.customer_package_id
        JOIN service_packages p ON p.id = cp.package_id
        JOIN branches b ON b.id = cp.branch_id
        LEFT JOIN users u ON u.id = r.redeemed_by
        LEFT JOIN bookings bk ON bk.id = r.booking_id
        ORDER BY r.redeemed_at DESC, r.id DESC LIMIT 100")->fetchAll();
    json_response(200, ['plans' => array_map(static fn(array $r): array => [
        'id' => (int)$r['id'], 'name' => $r['name'], 'serviceId' => $r['service_id'],
        'service' => $r['service'], 'sessions' => (int)$r['session_count'],
        'price' => (float)$r['price'], 'active' => (bool)$r['active'],
    ], $plans), 'customers' => $customers, 'redemptions' => array_map(static fn(array $r): array => [
        'id' => (int)$r['id'], 'date' => date(DATE_ATOM, strtotime($r['redeemed_at'])),
        'customer' => $r['customer_name'], 'package' => $r['package'], 'branch' => $r['branch'],
        'redeemedBy' => $r['redeemed_by'] ?? 'Former user', 'bookingRef' => $r['reference'], 'note' => $r['note'],
    ], $redemptions)]);
}

function create_package_plan(array $in): never {
    $name = trim((string)($in['name'] ?? ''));
    $serviceId = trim((string)($in['serviceId'] ?? '')) ?: null;
    $sessions = filter_var($in['sessions'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    $price = parse_amount($in['price'] ?? '');
    if ($name === '' || mb_strlen($name) > 120) json_response(422, ['error' => 'Enter a package name up to 120 characters.']);
    if ($sessions === false) json_response(422, ['error' => 'Enter between 1 and 100 sessions.']);
    if ($price === null) json_response(422, ['error' => 'Enter a valid package price.']);
    if ($serviceId !== null) {
        $stmt = db()->prepare('SELECT id FROM services WHERE id = ?');
        $stmt->execute([$serviceId]);
        if (!$stmt->fetchColumn()) json_response(422, ['error' => 'Choose a valid treatment.']);
    }
    try {
        db()->prepare('INSERT INTO service_packages (name, service_id, session_count, price) VALUES (?, ?, ?, ?)')
            ->execute([$name, $serviceId, $sessions, $price]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) json_response(409, ['error' => 'A package with that name already exists.']);
        throw $e;
    }
    json_response(201, ['ok' => true]);
}

function sell_package(array $in, array $user): never {
    $packageId = filter_var($in['packageId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $branchId = trim((string)($in['branchId'] ?? ''));
    $name = trim((string)($in['customer'] ?? ''));
    $mobile = trim((string)($in['mobile'] ?? ''));
    $amount = parse_amount($in['amountPaid'] ?? '');
    $digits = preg_replace('/\D/', '', $mobile);
    if ($packageId === false) json_response(422, ['error' => 'Choose a package.']);
    if ($name === '' || mb_strlen($name) > 120) json_response(422, ['error' => 'Enter the customer’s full name.']);
    if (strlen($digits) < 10 || strlen($digits) > 13) json_response(422, ['error' => 'Enter a valid mobile number.']);
    if ($amount === null) json_response(422, ['error' => 'Enter the amount paid.']);
    $branch = branch_for_new_record($user, $branchId);
    if (!$branch) json_response(422, ['error' => 'Choose a branch.']);
    $stmt = db()->prepare('SELECT id, session_count FROM service_packages WHERE id = ? AND active = 1');
    $stmt->execute([$packageId]);
    $plan = $stmt->fetch();
    if (!$plan) json_response(404, ['error' => 'Package not found or inactive.']);
    db()->prepare('INSERT INTO customer_packages (package_id, branch_id, customer_name, mobile, sessions_total, sessions_remaining, amount_paid, sold_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$packageId, $branch, $name, $mobile, $plan['session_count'], $plan['session_count'], $amount, $user['id']]);
    json_response(201, ['id' => (int)db()->lastInsertId()]);
}

function redeem_package(array $in, array $user): never {
    $id = filter_var($in['customerPackageId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) json_response(422, ['error' => 'Choose a customer package.']);
    $note = mb_substr(trim((string)($in['note'] ?? '')), 0, 255) ?: null;
    $bookingRef = trim((string)($in['bookingRef'] ?? ''));
    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT cp.id, cp.branch_id, cp.sessions_remaining FROM customer_packages cp WHERE cp.id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $package = $stmt->fetch();
    if (!$package) {
        $pdo->rollBack();
        json_response(404, ['error' => 'Customer package not found.']);
    }
    if ($package['sessions_remaining'] < 1) {
        $pdo->rollBack();
        json_response(409, ['error' => 'This package has no sessions remaining.']);
    }
    $bookingId = null;
    if ($bookingRef !== '') {
        $stmt = $pdo->prepare('SELECT id, branch_id FROM bookings WHERE reference = ?');
        $stmt->execute([$bookingRef]);
        $booking = $stmt->fetch();
        if (!$booking || $booking['branch_id'] !== $package['branch_id']) {
            $pdo->rollBack();
            json_response(422, ['error' => 'Choose a booking at the same branch as this package.']);
        }
        $bookingId = (int)$booking['id'];
    }
    $remaining = (int)$package['sessions_remaining'] - 1;
    $pdo->prepare('UPDATE customer_packages SET sessions_remaining = ?, active = ? WHERE id = ?')
        ->execute([$remaining, $remaining > 0 ? 1 : 0, $id]);
    $pdo->prepare('INSERT INTO package_redemptions (customer_package_id, booking_id, redeemed_by, note) VALUES (?, ?, ?, ?)')
        ->execute([$id, $bookingId, $user['id'], $note]);
    $pdo->commit();
    json_response(200, ['ok' => true]);
}

try {
    $user = require_role('admin');
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') list_packages();
    if ($method === 'POST') {
        $in = json_body();
        match ($in['action'] ?? '') {
            'create-plan' => create_package_plan($in),
            'sell' => sell_package($in, $user),
            'redeem' => redeem_package($in, $user),
            default => json_response(400, ['error' => 'Unknown package action.']),
        };
    }
    json_response(405, ['error' => 'Unsupported request.']);
} catch (PDOException $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log('packages.php: ' . $e->getMessage());
    json_response(500, ['error' => 'Packages are unavailable right now. Please try again shortly.']);
}
