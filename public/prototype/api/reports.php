<?php
// Month-end report: sales, expenses, purchase orders and bookings for one month.
// Needs the "reports.month_end" permission. Staff see their own branch; admins see every branch or one.
//   GET reports.php?action=month_end&month=YYYY-MM[&branch=id]
require __DIR__ . '/config.php';

function sum_by(string $sql, array $params): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $out = [];
    foreach ($stmt->fetchAll() as $r) $out[$r['k']] = round((float)$r['v'], 2);
    return $out;
}

function month_end(): never {
    $user = require_permission('reports.month_end');
    $month = (string)($_GET['month'] ?? date('Y-m'));
    if (!is_month($month)) json_response(422, ['error' => 'Choose a valid month.']);

    [$pw, $pp] = branch_condition($user, 'p.branch_id');
    [$cpw, $cpp] = branch_condition($user, 'cp.branch_id');
    [$ew, $ep] = branch_condition($user, 'e.branch_id');
    [$ow, $op] = branch_condition($user, 'po.branch_id');
    [$bw, $bp] = branch_condition($user, 'bk.branch_id');
    $inMonth = fn(string $col) => "DATE_FORMAT($col, '%Y-%m') = ?";
    $paymentsFrom = "FROM payments p JOIN bookings bk ON bk.id = p.booking_id WHERE {$inMonth('p.paid_at')} $pw";

    $salesByDay = sum_by("SELECT DATE(p.paid_at) AS k, SUM(p.amount) AS v $paymentsFrom GROUP BY k", array_merge([$month], $pp));
    $packageSalesByDay = sum_by("SELECT DATE(cp.purchased_at) AS k, SUM(cp.amount_paid) AS v
        FROM customer_packages cp WHERE {$inMonth('cp.purchased_at')} $cpw GROUP BY k", array_merge([$month], $cpp));
    $commissionsByDay = sum_by("SELECT DATE(p.paid_at) AS k, SUM(p.commission_amount) AS v $paymentsFrom GROUP BY k", array_merge([$month], $pp));
    $cardFeesByDay = sum_by("SELECT DATE(p.paid_at) AS k, SUM(p.card_fee) AS v $paymentsFrom GROUP BY k", array_merge([$month], $pp));
    $salesByMethod = sum_by("SELECT p.method AS k, SUM(p.amount) AS v $paymentsFrom GROUP BY k ORDER BY v DESC", array_merge([$month], $pp));
    $salesByCategory = sum_by("SELECT bk.service_category AS k, SUM(p.amount) AS v $paymentsFrom GROUP BY k ORDER BY v DESC", array_merge([$month], $pp));
    $salesByService = sum_by("SELECT COALESCE(s.name, bk.service_category) AS k, SUM(p.amount) AS v
        FROM payments p JOIN bookings bk ON bk.id = p.booking_id
        LEFT JOIN services s ON s.id = bk.service_id
        WHERE {$inMonth('p.paid_at')} $pw GROUP BY k ORDER BY v DESC", array_merge([$month], $pp));
    $packageSalesTotal = array_sum($packageSalesByDay);
    if ($packageSalesTotal > 0) $salesByService['Service packages'] = round($packageSalesTotal, 2);
    arsort($salesByService);
    $commissionsByEmployee = sum_by("SELECT COALESCE(u.name, 'Former staff') AS k, SUM(p.commission_amount) AS v
        FROM payments p LEFT JOIN users u ON u.id = p.service_staff_id WHERE {$inMonth('p.paid_at')} $pw
        GROUP BY k ORDER BY v DESC", array_merge([$month], $pp));
    $commissionTotal = array_sum($commissionsByEmployee);
    $cardFeeTotal = array_sum(sum_by("SELECT 'Card fees' AS k, SUM(p.card_fee) AS v $paymentsFrom", array_merge([$month], $pp)));
    $expensesByDay = sum_by("SELECT e.expense_date AS k, SUM(e.amount) AS v FROM expenses e WHERE {$inMonth('e.expense_date')} $ew GROUP BY k", array_merge([$month], $ep));
    $expensesByCategory = sum_by("SELECT e.category AS k, SUM(e.amount) AS v FROM expenses e WHERE {$inMonth('e.expense_date')} $ew GROUP BY k ORDER BY v DESC", array_merge([$month], $ep));
    // Cancelled purchase orders don't count as spending.
    $posByDay = sum_by("SELECT po.order_date AS k, SUM(po.amount) AS v FROM purchase_orders po WHERE {$inMonth('po.order_date')} AND po.status <> 'Cancelled' $ow GROUP BY k", array_merge([$month], $op));
    $posByStatus = sum_by("SELECT po.status AS k, COUNT(*) AS v FROM purchase_orders po WHERE {$inMonth('po.order_date')} $ow GROUP BY k", array_merge([$month], $op));
    $bookingsByStatus = sum_by("SELECT bk.status AS k, COUNT(*) AS v FROM bookings bk WHERE {$inMonth('bk.booking_date')} $bw GROUP BY k", array_merge([$month], $bp));
    $bookingsBySource = sum_by("SELECT bk.source AS k, COUNT(*) AS v FROM bookings bk WHERE {$inMonth('bk.booking_date')} $bw GROUP BY k", array_merge([$month], $bp));
    $paymentCount = array_sum(sum_by("SELECT 'n' AS k, COUNT(*) AS v $paymentsFrom", array_merge([$month], $pp)));
    $packageSalesCount = array_sum(sum_by("SELECT 'n' AS k, COUNT(*) AS v FROM customer_packages cp
        WHERE {$inMonth('cp.purchased_at')} $cpw", array_merge([$month], $cpp)));

    $days = [];
    $start = new DateTime("$month-01");
    for ($d = clone $start; $d->format('Y-m') === $month; $d->modify('+1 day')) {
        $key = $d->format('Y-m-d');
        $days[] = ['date' => $key, 'sales' => ($salesByDay[$key] ?? 0) + ($packageSalesByDay[$key] ?? 0),
            'packageSales' => $packageSalesByDay[$key] ?? 0, 'expenses' => $expensesByDay[$key] ?? 0,
            'purchaseOrders' => $posByDay[$key] ?? 0, 'commissions' => $commissionsByDay[$key] ?? 0, 'cardFees' => $cardFeesByDay[$key] ?? 0];
    }
    $sales = round(array_sum($salesByDay) + $packageSalesTotal, 2);
    $expenses = round(array_sum($expensesByDay), 2);
    $pos = round(array_sum($posByDay), 2);

    json_response(200, [
        'month' => $month,
        'totals' => ['sales' => $sales, 'expenses' => $expenses, 'purchaseOrders' => $pos,
            'commissions' => round($commissionTotal, 2), 'cardFees' => round($cardFeeTotal, 2),
            'net' => round($sales - $expenses - $pos - $commissionTotal - $cardFeeTotal, 2),
            'payments' => (int)$paymentCount, 'packageSales' => (int)$packageSalesCount,
            'packageSalesTotal' => round($packageSalesTotal, 2)],
        'days' => $days,
        'salesByMethod' => $salesByMethod,
        'salesByCategory' => $salesByCategory,
        'salesByService' => $salesByService,
        'commissionsByEmployee' => $commissionsByEmployee,
        'expensesByCategory' => $expensesByCategory,
        'purchaseOrdersByStatus' => array_map('intval', $posByStatus),
        'bookingsByStatus' => array_map('intval', $bookingsByStatus),
        'bookingsBySource' => array_map('intval', $bookingsBySource),
    ]);
}

// Dispatch last, so every function above is defined first.
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'month_end') month_end();
    json_response(405, ['error' => 'Unsupported request.']);
} catch (PDOException $e) {
    error_log('reports.php: ' . $e->getMessage());
    json_response(500, ['error' => 'The report is unavailable right now. Please try again shortly.']);
}
