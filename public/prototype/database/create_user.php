<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../api/config.php';

$opt = getopt('', ['name:', 'email:', 'role:', 'branch::', 'title::', 'password::']);
$name = trim($opt['name'] ?? '');
$email = strtolower(trim($opt['email'] ?? ''));
$role = $opt['role'] ?? '';
$branch = $opt['branch'] ?? null;
$title = trim($opt['title'] ?? '') ?: null;
$password = $opt['password'] ?? bin2hex(random_bytes(6));

$fail = fn(string $msg) => fwrite(STDERR, "Error: $msg\n") && exit(1);
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $fail('--name and a valid --email are required.');
if (!in_array($role, ['customer', 'staff', 'admin'], true)) $fail('--role must be customer, staff or admin.');
if ($role === 'staff' && !$branch) $fail('Staff accounts need --branch (canduman, pajac, cebu-city or pusok).');
if ($title !== null && mb_strlen($title) > 80) $fail('--title must be 80 characters or fewer.');
if (strlen($password) < 8) $fail('Password must be at least 8 characters.');

try {
    db()->prepare('INSERT INTO users (name, email, password_hash, role, job_title, branch_id) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $title, $role === 'staff' ? $branch : null]);
} catch (PDOException $e) {
    $fail($e->errorInfo[1] === 1062 ? 'That email already has an account.' : ($e->errorInfo[1] === 1452 ? 'Unknown branch id.' : $e->getMessage()));
}
echo "Created $role account for $email\nPassword: $password\n";
