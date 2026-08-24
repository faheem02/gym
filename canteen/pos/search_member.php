<?php
require __DIR__ . '/../../config.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo '[]';
    exit;
}

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo '[]';
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, phone FROM members WHERE status = 'active' AND (name LIKE ? OR phone LIKE ?) ORDER BY name LIMIT 8");
$like = '%' . $q . '%';
$stmt->execute([$like, $like]);
echo json_encode($stmt->fetchAll());
