<?php
// Employees breakdown: staff accounts with their title, branch and this month's activity.
// Needs the "employees.view" permission. Staff see their own branch; admins see every branch.
//   GET employees.php?month=YYYY-MM[&branch=id] -> { month, employees, byTitle }
require __DIR__ . '/config.php';

try {
    $user = require_permission('employees.view');
    $month = (string)($_GET['month'] ?? date('Y-m'));
    if (!is_month($month)) json_response(422, ['error' => 'Choose a valid month.']);
    [$where, $params] = branch_condition($user, 'u.branch_id');
    $stmt = db()->prepare("SELECT u.id, u.name, u.email, u.job_title, br.name AS branch, u.created_at,
            (SELECT COUNT(*) FROM payments p WHERE p.received_by = u.id AND DATE_FORMAT(p.paid_at, '%Y-%m') = ?) AS payments_taken,
            (SELECT COALESCE(SUM(p.commission_amount), 0) FROM payments p WHERE p.service_staff_id = u.id AND DATE_FORMAT(p.paid_at, '%Y-%m') = ?) AS commission_earned,
            (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.service_staff_id = u.id AND DATE_FORMAT(p.paid_at, '%Y-%m') = ?) AS sales_generated,
            (SELECT COUNT(*) FROM stock_movements m WHERE m.user_id = u.id AND DATE_FORMAT(m.created_at, '%Y-%m') = ?) AS stock_entries,
            (SELECT COUNT(*) FROM expenses e WHERE e.recorded_by = u.id AND DATE_FORMAT(e.expense_date, '%Y-%m') = ?) AS expenses_logged
        FROM users u LEFT JOIN branches br ON br.id = u.branch_id
        WHERE u.role = 'staff' AND u.active = 1 $where
        ORDER BY br.name, COALESCE(u.job_title, 'zzz'), u.name");
    $stmt->execute(array_merge([$month, $month, $month, $month, $month], $params));
    $byTitle = [];
    $employees = array_map(function ($r) use (&$byTitle) {
        $title = $r['job_title'] ?: 'Staff';
        $byTitle[$title] = ($byTitle[$title] ?? 0) + 1;
        return ['name' => $r['name'], 'email' => $r['email'], 'title' => $title, 'branch' => $r['branch'],
            'since' => substr($r['created_at'], 0, 10), 'paymentsTaken' => (int)$r['payments_taken'],
            'commissionEarned' => (float)$r['commission_earned'], 'salesGenerated' => (float)$r['sales_generated'],
            'stockEntries' => (int)$r['stock_entries'],
            'expensesLogged' => (int)$r['expenses_logged']];
    }, $stmt->fetchAll());
    ksort($byTitle);
    json_response(200, ['month' => $month, 'employees' => $employees, 'byTitle' => $byTitle]);
} catch (PDOException $e) {
    error_log('employees.php: ' . $e->getMessage());
    json_response(500, ['error' => 'Employee data is unavailable right now. Please try again shortly.']);
}
