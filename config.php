<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'gym_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Business / Gym Profile Info
define('GYM_NAME', 'The Compound');
define('GYM_OWNER', 'Ali Muqadas');
define('GYM_PHONE', '03438033322');
define('GYM_ADDRESS', 'PLOT 1 PASCO HOUSING SOCIETY NEAR EME SOCIETY CANAL ROAD');
define('GYM_LOGO', '/gym/logo/The Compound Logo-01.png');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
