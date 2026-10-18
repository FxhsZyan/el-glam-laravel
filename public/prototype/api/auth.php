<?php
// Sign-in API shared by every account type (used by prototype/login.php).
//   GET  auth.php                                       -> { user, redirect, secondsLeft } or 401   (counts as activity)
//   GET  auth.php?peek=1                                -> same, without counting as activity        (idle timer check)
//   POST auth.php  { action: "login", email, password } -> { user, redirect }  (redirect = that role's dashboard)
//                                                          429 while sign-in is paused after repeated wrong passwords
//   POST auth.php  { action: "logout" }                 -> { ok: true, redirect }  (redirect = login page)
require __DIR__ . '/config.php';

function client_ip(): string {
    return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
}

// Minutes until sign-in is allowed again for this email or address, or 0 if it isn't paused.
// Paused once there are too many failures within the window; it lifts LOGIN_LOCK_MINUTES after the latest one.
function lockout_minutes_left(string $email, string $ip): int {
    $sql = 'SELECT COUNT(*) AS failures, TIMESTAMPDIFF(SECOND, NOW(), MAX(attempted_at) + INTERVAL ' . LOGIN_LOCK_MINUTES . ' MINUTE) AS seconds_left
            FROM login_attempts WHERE %s = ? AND attempted_at > NOW() - INTERVAL ' . LOGIN_LOCK_MINUTES . ' MINUTE';
    $checks = [['email', $email, LOGIN_MAX_FAILURES], ['ip_address', $ip, LOGIN_MAX_IP_FAILURES]];
    $wait = 0;
    foreach ($checks as [$column, $value, $limit]) {
        $stmt = db()->prepare(sprintf($sql, $column));
        $stmt->execute([$value]);
        $row = $stmt->fetch();
        if ((int)$row['failures'] >= $limit) $wait = max($wait, (int)ceil(max(1, (int)$row['seconds_left']) / 60));
    }
    return $wait;
}

function paused_response(int $minutes): never {
    $unit = $minutes === 1 ? 'minute' : 'minutes';
    json_response(429, ['error' => "Too many unsuccessful sign-in attempts. For your security, please wait $minutes $unit and try again."]);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $user = current_user(empty($_GET['peek']));
        $user
            ? json_response(200, ['user' => $user, 'redirect' => dashboard_url($user), 'secondsLeft' => session_seconds_left()])
            : json_response(401, ['error' => 'Not signed in.']);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, ['error' => 'Unsupported request.']);

    $in = json_body();
    $action = $in['action'] ?? '';

    if ($action === 'logout') {
        end_session();
        json_response(200, ['ok' => true, 'redirect' => app_url(LOGIN_PAGE)]);
    }

    if ($action === 'register') {
        if (($in['audience'] ?? '') !== 'customer') json_response(400, ['error' => 'Customer registration is available from the customer sign-in page.']);
        $name = trim((string)($in['name'] ?? ''));
        $email = mb_substr(strtolower(trim((string)($in['email'] ?? ''))), 0, 190);
        $password = (string)($in['password'] ?? '');
        $mobile = trim((string)($in['mobile'] ?? '')) ?: null;
        if ($name === '' || mb_strlen($name) > 120) json_response(422, ['error' => 'Enter your name (up to 120 characters).']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(422, ['error' => 'Enter a valid email address.']);
        if (strlen($password) < 8 || strlen($password) > 72) json_response(422, ['error' => 'Choose a password between 8 and 72 characters.']);
        if ($mobile !== null && (mb_strlen($mobile) > 20 || strlen(preg_replace('/\D/', '', $mobile)) < 7)) {
            json_response(422, ['error' => 'Enter a valid mobile number or leave it blank.']);
        }
        try {
            db()->prepare("INSERT INTO users (name, email, mobile, password_hash, role) VALUES (?, ?, ?, ?, 'customer')")
                ->execute([$name, $email, $mobile, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) json_response(409, ['error' => 'An account with that email already exists. Sign in instead.']);
            throw $e;
        }
        $user = find_user('email', $email);
        begin_user_session(session_user($user));
        json_response(201, ['user' => $_SESSION['user'], 'redirect' => dashboard_url($_SESSION['user'])]);
    }

    if ($action === 'login') {
        $email = mb_substr(strtolower(trim((string)($in['email'] ?? ''))), 0, 190);
        $password = (string)($in['password'] ?? '');
        $customerOnly = ($in['audience'] ?? '') === 'customer';
        $ip = client_ip();

        db()->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
        // Checked before the password, so guesses made while paused are never tested.
        if ($wait = lockout_minutes_left($email, $ip)) paused_response($wait);

        $row = find_user('email', $email);
        // Same message for unknown email and wrong password, so accounts can't be discovered.
        // Unknown emails are counted too, so the pause doesn't reveal which emails exist.
        if (!$row || !password_verify($password, $row['password_hash']) || ($customerOnly && $row['role'] !== 'customer')) {
            db()->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)')->execute([$email, $ip]);
            usleep(300000); // slows down password guessing
            if ($wait = lockout_minutes_left($email, $ip)) paused_response($wait);
            json_response(401, ['error' => 'Email or password is incorrect.']);
        }

        db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
        // Checked only after the right password, so it doesn't reveal which emails have accounts.
        if (!$row['active']) json_response(403, ['error' => 'This account has been deactivated. Please contact an administrator.']);
        begin_user_session(session_user($row));
        json_response(200, ['user' => $_SESSION['user'], 'redirect' => dashboard_url($_SESSION['user'])]);
    }

    json_response(400, ['error' => 'Unknown action.']);
} catch (PDOException $e) {
    error_log('auth.php: ' . $e->getMessage());
    json_response(500, ['error' => 'Sign-in is unavailable right now. Please try again shortly.']);
}
