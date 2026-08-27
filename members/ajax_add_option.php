<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$category = $_POST['category'] ?? '';
$value = trim($_POST['value'] ?? '');

if (!in_array($category, ['membership_type', 'area_of_interest']) || $value === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid category or empty value.']);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT IGNORE INTO member_options (category, value) VALUES (?, ?)');
    $stmt->execute([$category, $value]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Could not add option.']);
}
