<?php
// Public list of active branches.
//   GET branches.php -> { branches: [{ id, name, area, address, phones: [...], hours }] }
require __DIR__ . '/config.php';

try {
    $rows = db()->query('SELECT id, name, area, address, phones, hours FROM branches WHERE active = 1
                         ORDER BY FIELD(id, \'canduman\', \'pajac\', \'cebu-city\', \'pusok\'), name')->fetchAll();
    foreach ($rows as &$row) {
        $row['phones'] = array_values(array_filter(array_map('trim', explode(',', $row['phones']))));
    }
    json_response(200, ['branches' => $rows]);
} catch (PDOException $e) {
    error_log('branches.php: ' . $e->getMessage());
    json_response(500, ['error' => 'Branches are unavailable right now.']);
}
