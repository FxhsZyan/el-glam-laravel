<?php
// Bookings API.
// Public availability:
//   GET   bookings.php?action=availability&branch=Canduman&date=2026-10-05 -> { taken: ["11:00 AM", ...], past: [...] }
// Signed-in customers and team:
//   POST  bookings.php  { branch, service, date, time, name, mobile }        -> 201 { booking } (customer role required for online requests)
// Staff & admin (signed in):
//   GET   bookings.php?action=list   -> { bookings: [...] }  (staff see their own branch only)
//   POST  bookings.php  { ..., serviceId?, source: "Walk-in" | "Online" }   (staff are locked to their branch)
//   PATCH bookings.php?ref=EG-00001  { status }                             -> { booking }
require __DIR__ . '/config.php';

const STATUSES = ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'No-show'];
const FINAL_STATUSES = ['Completed', 'Cancelled', 'No-show'];

function find_branch(string $name): ?array {
    $stmt = db()->prepare('SELECT id, name FROM branches WHERE name = ? AND active = 1');
    $stmt->execute([$name]);
    return $stmt->fetch() ?: null;
}

function valid_date(string $date): bool {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date && $date >= date('Y-m-d');
}

// Shape shared by the staff and admin pages.
const BOOKING_SELECT = "SELECT bk.reference, bk.customer_name, bk.mobile, br.name AS branch, bk.branch_id,
        COALESCE(s.name, bk.service_category) AS service, bk.service_category AS category, bk.service_id,
        bk.booking_date, bk.booking_time, bk.status, bk.source, bk.created_at, p.amount AS paid_amount, p.method AS paid_method
    FROM bookings bk
    JOIN branches br ON br.id = bk.branch_id
    LEFT JOIN services s ON s.id = bk.service_id
    LEFT JOIN payments p ON p.booking_id = bk.id";

function format_booking(array $r): array {
    return [
        'id' => $r['reference'],
        'name' => $r['customer_name'],
        'mobile' => $r['mobile'],
        'branch' => $r['branch'],
        'branchId' => $r['branch_id'],
        'service' => $r['service'],
        'category' => $r['category'],
        'serviceId' => $r['service_id'],
        'date' => $r['booking_date'],
        'time' => sql_time_to_slot($r['booking_time']),
        'status' => $r['status'],
        'source' => $r['source'],
        'createdAt' => $r['created_at'],
        'paidAmount' => $r['paid_amount'] !== null ? (float)$r['paid_amount'] : null,
        'paidMethod' => $r['paid_method'],
    ];
}

// Payment details are sales data, so they're only shown to accounts that can see sales.
function for_viewer(array $booking, array $user): array {
    if (!can($user, 'sales')) unset($booking['paidAmount'], $booking['paidMethod']);
    return $booking;
}

// Today's slots whose start time has already passed (Philippine time).
function past_slots(string $date): array {
    if ($date !== date('Y-m-d')) return [];
    $now = date('H:i:s');
    return array_values(array_filter(TIME_SLOTS, fn($slot) => slot_to_sql_time($slot) <= $now));
}

function fetch_booking(string $reference): ?array {
    $stmt = db()->prepare(BOOKING_SELECT . ' WHERE bk.reference = ?');
    $stmt->execute([$reference]);
    return $stmt->fetch() ?: null;
}

function availability(): never {
    $branch = find_branch(trim($_GET['branch'] ?? ''));
    $date = trim($_GET['date'] ?? '');
    if (!$branch || !valid_date($date)) {
        json_response(422, ['error' => 'Choose a valid branch and date.']);
    }
    $stmt = db()->prepare('SELECT booking_time FROM bookings WHERE branch_id = ? AND booking_date = ? AND active_slot = 1');
    $stmt->execute([$branch['id'], $date]);
    $taken = array_map('sql_time_to_slot', $stmt->fetchAll(PDO::FETCH_COLUMN));
    json_response(200, ['taken' => $taken, 'past' => past_slots($date)]);
}

function list_bookings(): never {
    $user = require_role('staff', 'admin');
    $sql = BOOKING_SELECT;
    $params = [];
    if ($user['role'] === 'staff') {
        $sql .= ' WHERE bk.branch_id = ?';
        $params[] = $user['branchId'];
    }
    $sql .= ' ORDER BY bk.booking_date, bk.booking_time';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_response(200, ['bookings' => array_map(fn($r) => for_viewer(format_booking($r), $user), $stmt->fetchAll())]);
}

function create_booking(): never {
    $user = require_role('customer', 'staff', 'admin');
    $in = json_body();
    $isTeam = $user && in_array($user['role'], ['staff', 'admin'], true);

    $category  = trim((string)($in['service'] ?? ''));
    $serviceId = trim((string)($in['serviceId'] ?? ''));
    $date      = trim((string)($in['date'] ?? ''));
    $slot      = trim((string)($in['time'] ?? ''));
    $name      = trim((string)($in['name'] ?? ''));
    $mobile    = trim((string)($in['mobile'] ?? ''));
    $digits    = preg_replace('/\D/', '', $mobile);
    if ($user['role'] === 'customer') {
        $stmt = db()->prepare('SELECT name, mobile FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();
        if (!$profile) json_response(401, ['error' => 'Please sign in to continue.']);
        $name = (string)$profile['name'];
        if ($profile['mobile'] !== null && trim((string)$profile['mobile']) !== '') {
            $mobile = (string)$profile['mobile'];
            $digits = preg_replace('/\D/', '', $mobile);
        }
    }
    // Only the team can record walk-ins; staff can only book into their own branch.
    $source    = $isTeam && ($in['source'] ?? '') === 'Walk-in' ? 'Walk-in' : 'Online';
    $branch    = $user && $user['role'] === 'staff'
        ? ['id' => $user['branchId'], 'name' => $user['branchName']]
        : find_branch(trim((string)($in['branch'] ?? '')));

    $errors = [];
    if ($serviceId !== '') {
        $stmt = db()->prepare('SELECT category FROM services WHERE id = ? AND active = 1');
        $stmt->execute([$serviceId]);
        $category = (string)$stmt->fetchColumn();
        if ($category === '') $errors[] = 'Choose a treatment.';
    } elseif (!in_array($category, SERVICE_CATEGORIES, true)) {
        $errors[] = 'Choose a service.';
    }
    $time = slot_to_sql_time($slot);
    if (!$branch) $errors[] = 'Choose a branch.';
    if (!valid_date($date)) $errors[] = 'Choose a date from today onward.';
    // Online requests can't take a slot that has already started; the team may still record a walk-in after the fact.
    if (!$time) $errors[] = 'Choose an available time.';
    elseif (!$isTeam && in_array($slot, past_slots($date), true)) $errors[] = 'That time has already passed today. Please choose a later time.';
    if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Enter the customer’s full name.';
    if (strlen($digits) < 10 || strlen($digits) > 13) $errors[] = 'Enter a valid mobile number.';
    if ($errors) json_response(422, ['error' => implode(' ', $errors)]);

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO bookings (reference, user_id, customer_name, mobile, branch_id, service_category, service_id, booking_date, booking_time, source)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $customerId = $user['role'] === 'customer' ? $user['id'] : null;
        $stmt->execute(['TMP-' . bin2hex(random_bytes(6)), $customerId, $name, $mobile, $branch['id'], $category, $serviceId ?: null, $date, $time, $source]);
        $id = (int)$pdo->lastInsertId();
        $reference = sprintf('EG-%05d', $id);
        $pdo->prepare('UPDATE bookings SET reference = ? WHERE id = ?')->execute([$reference, $id]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // 1062 = duplicate key: the unique slot index caught a double booking.
        if (($e->errorInfo[1] ?? null) === 1062) {
            json_response(409, ['error' => "That time is already booked at {$branch['name']}. Please choose another time."]);
        }
        throw $e;
    }

    $booking = $isTeam ? for_viewer(format_booking(fetch_booking($reference)), $user) : format_booking(fetch_booking($reference));
    // Customers only receive the confirmation details, not internal fields.
    if (!$isTeam) {
        $booking = array_intersect_key($booking, array_flip(['id', 'branch', 'category', 'date', 'time', 'status']));
        $booking['service'] = $booking['category'];
    }
    // The customer form shows this, including when a staff member is signed in on the same browser.
    $booking['reference'] = $booking['id'];
    json_response(201, ['booking' => $booking]);
}

function update_status(): never {
    $user = require_role('staff', 'admin');
    $in = json_body();
    $status = (string)($in['status'] ?? '');
    if (!in_array($status, STATUSES, true)) json_response(422, ['error' => 'Choose a valid status.']);

    $row = fetch_booking((string)($_GET['ref'] ?? ''));
    // Staff get the same "not found" for other branches' bookings, so references can't be probed.
    if (!$row || ($user['role'] === 'staff' && $row['branch_id'] !== $user['branchId'])) {
        json_response(404, ['error' => 'Booking not found.']);
    }
    if ($user['role'] === 'staff' && in_array($row['status'], FINAL_STATUSES, true)) {
        json_response(409, ['error' => "This booking is already {$row['status']} and can only be changed by an administrator."]);
    }

    try {
        db()->prepare('UPDATE bookings SET status = ? WHERE reference = ?')->execute([$status, $row['reference']]);
    } catch (PDOException $e) {
        // Re-opening a cancelled booking whose slot has since been taken.
        if (($e->errorInfo[1] ?? null) === 1062) {
            json_response(409, ['error' => 'That time slot has been booked by someone else, so this booking can’t be reopened.']);
        }
        throw $e;
    }
    json_response(200, ['booking' => for_viewer(format_booking(fetch_booking($row['reference'])), $user)]);
}

// Dispatch last, so every constant and function above is defined first.
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    if ($method === 'GET' && $action === 'availability') availability();
    if ($method === 'GET' && $action === 'list') list_bookings();
    if ($method === 'POST') create_booking();
    if ($method === 'PATCH') update_status();
    json_response(405, ['error' => 'Unsupported request.']);
} catch (PDOException $e) {
    error_log('bookings.php: ' . $e->getMessage());
    json_response(500, ['error' => 'The booking service is unavailable right now. Please try again shortly.']);
}
