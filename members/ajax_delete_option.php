<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$category = $_POST['category'] ?? '';
$value = trim($_POST['value'] ?? '');

if ($value === '') {
    echo json_encode(['success' => false, 'error' => 'Empty value.']);
    exit;
}

try {
    if ($category === 'fitness_goal' || $category === 'area_of_interest') {
        $stmt = $pdo->prepare("DELETE FROM member_options WHERE category IN ('fitness_goal', 'area_of_interest') AND value = ?");
        $stmt->execute([$value]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM member_options WHERE category = ? AND value = ?');
        $stmt->execute([$category, $value]);
    }
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Could not delete option.']);
}
