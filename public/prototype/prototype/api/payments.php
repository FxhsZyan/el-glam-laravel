<?php
// Payments and the daily sales summary. Needs the "sales" permission (admins have it for every branch).
//   GET    payments.php?action=daily&date=YYYY-MM-DD[&branch=id]  -> { date, total, count, byMethod, byCategory, payments }
//   POST   payments.php { bookingRef, amount, method, note? }      -> 201 { payment }  (also marks the booking Completed)
//   DELETE payments.php?id=N                                       admin only, to correct a mistaken entry
require __DIR__ . '/config.php';

function daily_summary(): never {
    $user = require_permission('sales');
    $date = (string)($_GET['date'] ?? date('Y-m-d'));
    if (!is_ymd($date)) json_response(422, ['error' => 'Choose a valid date.']);
    [$where, $params] = branch_condition($user, 'p.branch_id');
    $stmt = db()->prepare("SELECT p.id, p.amount, p.method, p.note, p.card_fee, p.commission_amount, p.paid_at,
            bk.reference, bk.customer_name, ss.name AS service_staff,
            COALESCE(s.name, bk.service_category) AS service, bk.service_category AS category, br.name AS branch, u.name AS received_by
        FROM payments p
        JOIN bookings bk ON bk.id = p.booking_id
        JOIN branches br ON br.id = p.branch_id
        LEFT JOIN services s ON s.id = bk.service_id
        LEFT JOIN users u ON u.id = p.received_by
        LEFT JOIN users ss ON ss.id = p.service_staff_id
        WHERE DATE(p.paid_at) = ? $where ORDER BY p.paid_at");
    $stmt->execute(array_merge([$date], $params));
    $rows = $stmt->fetchAll();

    $total = 0.0; $byMethod = []; $byCategory = []; $byService = [];
    foreach ($rows as &$r) {
        $amount = (float)$r['amount'];
        $total += $amount;
        $byMethod[$r['method']] = ($byMethod[$r['method']] ?? 0) + $amount;
        $byCategory[$r['category']] = ($byCategory[$r['category']] ?? 0) + $amount;
        $byService[$r['service']] = ($byService[$r['service']] ?? 0) + $amount;
        $r = [
            'id' => (int)$r['id'], 'amount' => $amount, 'method' => $r['method'], 'note' => $r['note'],
            'cardFee' => (float)$r['card_fee'], 'commission' => (float)$r['commission_amount'],
            'paidAt' => date(DATE_ATOM, strtotime($r['paid_at'])), 'bookingRef' => $r['reference'],
            'customer' => $r['customer_name'], 'service' => $r['service'], 'category' => $r['category'],
            'branch' => $r['branch'], 'receivedBy' => $r['received_by'], 'serviceStaff' => $r['service_staff'],
        ];
    }
    unset($r);
    [$packageWhere, $packageParams] = branch_condition($user, 'cp.branch_id');
    $packageStmt = db()->prepare("SELECT cp.id, cp.customer_name, cp.branch_id, b.name AS branch, p.name AS package,
            cp.amount_paid, cp.purchased_at, u.name AS sold_by
        FROM customer_packages cp
        JOIN service_packages p ON p.id = cp.package_id
        JOIN branches b ON b.id = cp.branch_id
        LEFT JOIN users u ON u.id = cp.sold_by
        WHERE DATE(cp.purchased_at) = ? $packageWhere ORDER BY cp.purchased_at");
    $packageStmt->execute(array_merge([$date], $packageParams));
    $packageSales = array_map(static fn(array $r): array => [
        'id' => (int)$r['id'], 'customer' => $r['customer_name'], 'branch' => $r['branch'],
        'package' => $r['package'], 'amount' => (float)$r['amount_paid'],
        'purchasedAt' => date(DATE_ATOM, strtotime($r['purchased_at'])), 'soldBy' => $r['sold_by'],
    ], $packageStmt->fetchAll());
    $paymentTotal = round($total, 2);
    $packageSalesTotal = round(array_sum(array_column($packageSales, 'amount')), 2);
    if ($packageSalesTotal > 0) $byService['Service packages'] = $packageSalesTotal;
    arsort($byMethod); arsort($byCategory); arsort($byService);
    $commissionTotal = array_sum(array_column($rows, 'commission'));
    $cardFeeTotal = array_sum(array_column($rows, 'cardFee'));
    $byEmployee = [];
    foreach ($rows as $r) {
        if ($r['serviceStaff']) $byEmployee[$r['serviceStaff']] = ($byEmployee[$r['serviceStaff']] ?? 0) + $r['amount'];
    }
    arsort($byEmployee);
    json_response(200, ['date' => $date, 'total' => round($paymentTotal + $packageSalesTotal, 2),
        'paymentTotal' => $paymentTotal, 'packageSalesTotal' => $packageSalesTotal,
        'packageSalesCount' => count($packageSales), 'packageSales' => $packageSales, 'count' => count($rows),
        'commissionTotal' => round($commissionTotal, 2), 'cardFeeTotal' => round($cardFeeTotal, 2),
        'byMethod' => $byMethod, 'byCategory' => $byCategory, 'byService' => $byService,
        'byEmployee' => $byEmployee, 'payments' => $rows]);
}

function payment_staff(): never {
    $user = require_permission('sales');
    $branchId = $user['role'] === 'admin' ? trim((string)($_GET['branch'] ?? '')) : $user['branchId'];
    if ($branchId === '') json_response(422, ['error' => 'Choose a branch to list its service staff.']);
    $stmt = db()->prepare("SELECT id, name, job_title FROM users WHERE role = 'staff' AND active = 1 AND branch_id = ? ORDER BY name");
    $stmt->execute([$branchId]);
    json_response(200, ['staff' => array_map(static fn(array $r): array => [
        'id' => (int)$r['id'], 'name' => $r['name'], 'title' => $r['job_title'] ?: 'Staff',
    ], $stmt->fetchAll())]);
}

function record_payment(): never {
    $user = require_permission('sales');
    $in = json_body();
    $amount = parse_amount($in['amount'] ?? '');
    $method = (string)($in['method'] ?? '');
    $note = mb_substr(trim((string)($in['note'] ?? '')), 0, 255) ?: null;
    $staffId = filter_var($in['serviceStaffId'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $commission = parse_nonnegative_amount($in['commissionAmount'] ?? '0');
    $cardFee = parse_nonnegative_amount($in['cardFee'] ?? '0');
    if ($amount === null) json_response(422, ['error' => 'Enter the amount paid (up to ₱1,000,000, at most 2 decimals).']);
    if (!in_array($method, PAYMENT_METHODS, true)) json_response(422, ['error' => 'Choose how the customer paid.']);
    if ($staffId === false) $staffId = null;
    if ($commission === null || $cardFee === null) json_response(422, ['error' => 'Commission and card fee must be valid amounts from ₱0.00 to ₱1,000,000.']);
    if ($method !== 'Card' && (float)$cardFee > 0) json_response(422, ['error' => 'A card fee can only be recorded for a card payment.']);

    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id, reference, branch_id, status FROM bookings WHERE reference = ? FOR UPDATE');
    $stmt->execute([(string)($in['bookingRef'] ?? '')]);
    $booking = $stmt->fetch();
    // Staff get "not found" for other branches' bookings, so references can't be probed.
    if (!$booking || ($user['role'] !== 'admin' && $booking['branch_id'] !== $user['branchId'])) {
        $pdo->rollBack();
        json_response(404, ['error' => 'Booking not found.']);
    }
    if (in_array($booking['status'], ['Cancelled', 'No-show'], true)) {
        $pdo->rollBack();
        json_response(409, ['error' => "This booking is {$booking['status']}, so a payment can't be recorded."]);
    }
    if ($staffId !== null) {
        $staff = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'staff' AND active = 1 AND branch_id = ?");
        $staff->execute([$staffId, $booking['branch_id']]);
        if (!$staff->fetchColumn()) {
            $pdo->rollBack();
            json_response(422, ['error' => 'Choose an active staff member assigned to this booking’s branch.']);
        }
    } elseif ((float)$commission > 0) {
        $pdo->rollBack();
        json_response(422, ['error' => 'Choose the staff member earning the commission.']);
    }
    try {
        $pdo->prepare('INSERT INTO payments (booking_id, branch_id, amount, method, service_staff_id, commission_amount, card_fee, note, received_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$booking['id'], $booking['branch_id'], $amount, $method, $staffId, $commission, $cardFee, $note, $user['id']]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        if (($e->errorInfo[1] ?? null) === 1062) json_response(409, ['error' => 'A payment has already been recorded for this booking.']);
        throw $e;
    }
    $paymentId = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE bookings SET status = 'Completed' WHERE id = ?")->execute([$booking['id']]);
    $pdo->commit();
    json_response(201, ['payment' => ['id' => $paymentId, 'bookingRef' => $booking['reference'], 'amount' => (float)$amount, 'method' => $method,
        'commission' => (float)$commission, 'cardFee' => (float)$cardFee]]);
}

function parse_nonnegative_amount($value): ?string {
    $text = trim((string)$value);
    if (!preg_match('/^(?:0|[1-9]\d{0,6})(?:\.\d{1,2})?$/', $text)) return null;
    if ((float)$text > 1000000) return null;
    return number_format((float)$text, 2, '.', '');
}

function delete_payment(): never {
    require_role('admin');
    $stmt = db()->prepare('DELETE FROM payments WHERE id = ?');
    $stmt->execute([(int)($_GET['id'] ?? 0)]);
    $stmt->rowCount() ? json_response(200, ['ok' => true]) : json_response(404, ['error' => 'Payment not found.']);
}

// Dispatch last, so every function above is defined first.
try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET' && ($_GET['action'] ?? '') === 'daily') daily_summary();
    if ($method === 'GET' && ($_GET['action'] ?? '') === 'staff') payment_staff();
    if ($method === 'POST') record_payment();
    if ($method === 'DELETE') delete_payment();
    json_response(405, ['error' => 'Unsupported request.']);
} catch (PDOException $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log('payments.php: ' . $e->getMessage());
    json_response(500, ['error' => 'Sales are unavailable right now. Please try again shortly.']);
}
