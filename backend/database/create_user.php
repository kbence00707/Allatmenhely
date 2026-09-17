<?php
/**
 * Creates a staff account, or changes the password if the e-mail already exists.
 * Runs only from the terminal, never from the browser.
 *
 * Usage (from the Allatmenhely folder):
 *   E:\xampp\php\php.exe backend\database\create_user.php "Kiss Réka" reka@allatmenhely.hu "ErosJelszo123"
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Only from the command line.');
}

require __DIR__ . '/../config/Database.php';
require __DIR__ . '/../models/User.php';

if ($argc !== 4) {
    echo "Usage: php create_user.php \"Name\" email password\n";
    exit(1);
}

[, $name, $email, $password] = $argv;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Error: invalid e-mail address.\n";
    exit(1);
}
if (strlen($password) < 8) {
    echo "Error: the password must be at least 8 characters long.\n";
    exit(1);
}

$config = require __DIR__ . '/../config/config.php';
Database::connect($config['db']);
$users = new User(Database::get());

$existing = $users->findByEmail($email);
if ($existing) {
    $users->updatePassword((int) $existing['id'], $password);
    echo "Password updated for $email\n";
} else {
    $id = $users->create($name, $email, $password);
    echo "Staff user created (id: $id): $email\n";
}
