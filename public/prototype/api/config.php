<?php
// Database connection shared by every API endpoint.
// XAMPP's default MySQL login is root with no password; change these for any shared or live server.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'el_glam';
const DB_USER = 'root';
const DB_PASS = '';

// The clinic is in Cebu, so "today" and booking dates follow Philippine time.
date_default_timezone_set('Asia/Manila');

// Bookable times, matching the slots shown on the customer form.
const TIME_SLOTS = ['11:00 AM', '12:30 PM', '2:00 PM', '3:30 PM', '5:00 PM', '6:30 PM'];
const SERVICE_CATEGORIES = ['Face', 'Body', 'Hair', 'Nails'];
const PAYMENT_METHODS = ['Cash', 'GCash', 'Maya', 'Card', 'Bank transfer'];
const EXPENSE_CATEGORIES = ['Supplies', 'Utilities', 'Transportation', 'Meals', 'Repairs & maintenance', 'Rent', 'Salaries & wages', 'Miscellaneous'];

// Extra areas an account can be given (see user_permissions in database/schema.sql). Admins have all of them.
const PERMISSIONS = [
    'customers.view'    => 'Customer data',
    'sales'             => 'Daily sales summary (and recording payments)',
    'reports.month_end' => 'Month-end report',
    'employees.view'    => 'Employees breakdown',
    'expenses.daily'    => 'Daily expenses',
    'expenses.po'       => 'Purchase order expenses',
];

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        // Philippine time for NOW(), CURDATE() and timestamps, matching PHP's date() above.
        $pdo->exec("SET time_zone = '+08:00'");
    }
    return $pdo;
}

function json_response(int $status, array $body): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body);
    exit;
}

// "2:00 PM" -> "14:00:00"; returns null for anything that isn't a valid slot label.
function slot_to_sql_time(string $label): ?string {
    if (!in_array($label, TIME_SLOTS, true)) return null;
    $t = DateTime::createFromFormat('g:i A', $label);
    return $t ? $t->format('H:i:s') : null;
}

// "14:00:00" -> "2:00 PM"
function sql_time_to_slot(string $time): string {
    return DateTime::createFromFormat('H:i:s', $time)->format('g:i A');
}

// ---- Sessions & access control ----

const SESSION_IDLE_MINUTES = 30;   // signed out after this long with no activity
const SESSION_MAX_HOURS = 12;      // signed out this long after signing in, even if active
const LOGIN_MAX_FAILURES = 5;      // wrong passwords allowed per email...
const LOGIN_MAX_IP_FAILURES = 20;  // ...and per device/network address...
const LOGIN_LOCK_MINUTES = 15;     // ...within this window before sign-in is paused

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    // PHP's default clean-up (24 minutes) would end sessions before our own limits do.
    ini_set('session.gc_maxlifetime', (string)(SESSION_MAX_HOURS * 3600));
    session_name('elglam_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,           // not readable from page JavaScript
        'samesite' => 'Strict',       // not sent on requests started by other sites
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function begin_user_session(array $user): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    $_SESSION['signed_in_at'] = $_SESSION['last_activity'] = time();
}

function end_session(): void {
    start_session();
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
}

// Seconds until the session times out (idle or maximum length, whichever comes first).
function session_seconds_left(): int {
    $now = time();
    $idle = ($_SESSION['last_activity'] ?? 0) + SESSION_IDLE_MINUTES * 60 - $now;
    $max = ($_SESSION['signed_in_at'] ?? 0) + SESSION_MAX_HOURS * 3600 - $now;
    return min($idle, $max);
}

// An account row by email or id, including its password hash (for checking sign-ins).
function find_user(string $column, string|int $value): ?array {
    $column = $column === 'id' ? 'u.id' : 'u.email';
    $stmt = db()->prepare("SELECT u.id, u.name, u.email, u.role, u.job_title, u.active, u.password_hash, u.branch_id, b.name AS branch_name
                           FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE $column = ?");
    $stmt->execute([$value]);
    return $stmt->fetch() ?: null;
}

// The account details kept in the session and given to pages (never the password hash).
function session_user(array $row): array {
    return [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => $row['role'],
        'jobTitle' => $row['job_title'],
        'branchId' => $row['branch_id'],
        'branchName' => $row['branch_name'],
        'permissions' => $row['role'] === 'admin' ? array_keys(PERMISSIONS) : user_permission_list((int)$row['id']),
    ];
}

function user_permission_list(int $userId): array {
    $stmt = db()->prepare('SELECT permission FROM user_permissions WHERE user_id = ? ORDER BY permission');
    $stmt->execute([$userId]);
    return array_values(array_intersect($stmt->fetchAll(PDO::FETCH_COLUMN), array_keys(PERMISSIONS)));
}

function can(array $user, string $permission): bool {
    return in_array($permission, $user['permissions'] ?? [], true);
}

// Stops the request unless the signed-in staff/admin account has this permission.
function require_permission(string $permission): array {
    $user = require_role('staff', 'admin');
    if (!can($user, $permission)) json_response(403, ['error' => 'Your account does not have access to this.']);
    return $user;
}

// SQL condition limiting rows to the user's branch. Staff are always locked to their own branch;
// admins see every branch, or one branch when ?branch=<id> is given.
function branch_condition(array $user, string $column): array {
    if ($user['role'] !== 'admin') return [" AND $column = ?", [$user['branchId']]];
    $branch = trim((string)($_GET['branch'] ?? ''));
    return $branch !== '' ? [" AND $column = ?", [$branch]] : ['', []];
}

// The branch a new record belongs to: staff use their own; admins must name an existing one.
function branch_for_new_record(array $user, $requested): ?string {
    if ($user['role'] !== 'admin') return $user['branchId'];
    $stmt = db()->prepare('SELECT id FROM branches WHERE id = ? AND active = 1');
    $stmt->execute([(string)$requested]);
    return $stmt->fetchColumn() ?: null;
}

function is_ymd(string $date): bool {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function is_month(string $month): bool {
    $d = DateTime::createFromFormat('!Y-m', $month);
    return $d && $d->format('Y-m') === $month;
}

// A peso amount as a "1234.50" string, or null unless it's a number from 0.01 to 1,000,000 with at most 2 decimals.
function parse_amount($value): ?string {
    $text = trim((string)$value);
    if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $text)) return null;
    $amount = (float)$text;
    return $amount >= 0.01 && $amount <= 1000000 ? number_format($amount, 2, '.', '') : null;
}

// The signed-in user, or null. Timed-out sessions are ended here.
// $touch = false checks the session without counting it as activity.
function current_user(bool $touch = true): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE && empty($_COOKIE['elglam_session'])) return null; // don't create sessions for anonymous visitors
    start_session();
    if (empty($_SESSION['user'])) return null;
    if (session_seconds_left() <= 0) {
        end_session();
        return null;
    }
    // Re-read the account on every request, so renamed, moved, re-roled or deleted accounts take effect immediately.
    $row = find_user('id', $_SESSION['user']['id']);
    if (!$row || !$row['active']) {
        end_session();
        return null;
    }
    $_SESSION['user'] = session_user($row);
    if ($touch) $_SESSION['last_activity'] = time();
    return $_SESSION['user'];
}

// Stops the request with 401/403 unless the signed-in user has one of the given roles.
function require_role(string ...$roles): array {
    $user = current_user();
    if (!$user) json_response(401, ['error' => 'Please sign in to continue.']);
    if (!in_array($user['role'], $roles, true)) json_response(403, ['error' => 'Your account does not have access to this.']);
    return $user;
}

// ---- Pages & redirects ----

const LOGIN_PAGE = 'prototype/login.php';
const CUSTOMER_LOGIN_PAGE = 'prototype/login.php?audience=customer';
// Where each role lands after signing in.
const DASHBOARDS = [
    'admin' => 'prototype/admin-view/admin.php',
    'staff' => 'prototype/staff-view/staff.php',
    'customer' => 'prototype/customer-view/index.php',
];

// URL path for a file in this project, e.g. "/el-glam-booking-system/prototype/login.php".
function app_url(string $path): string {
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $base = str_replace('\\', '/', substr(realpath(__DIR__ . '/..'), strlen($docRoot)));
    return rtrim($base, '/') . '/' . $path;
}

function dashboard_url(array $user): string {
    return app_url(DASHBOARDS[$user['role']] ?? LOGIN_PAGE);
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// For dashboard pages: only sends the page to a signed-in user with this role.
// Everyone else is redirected before any of the page is sent.
function require_page_role(string $role): array {
    $user = current_user();
    if (!$user) redirect(app_url($role === 'customer' ? CUSTOMER_LOGIN_PAGE : LOGIN_PAGE));
    if ($user['role'] !== $role) redirect(dashboard_url($user));
    header('Cache-Control: no-store'); // so Back after signing out can't show a cached dashboard
    return $user;
}

// The user as JSON that is safe to print inside a <script> block.
function user_json(array $user): string {
    return json_encode($user, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

// Decoded JSON body for write requests. Requiring the JSON content type also blocks
// cross-site form posts, since browsers cannot send it cross-origin without permission.
function json_body(): array {
    if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        json_response(415, ['error' => 'Requests must be sent as JSON.']);
    }
    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) json_response(400, ['error' => 'Invalid request.']);
    return $in;
}
