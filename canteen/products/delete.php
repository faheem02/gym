<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM canteen_products WHERE id = ?');
    $stmt->execute([$id]);
}
header('Location: /gym/canteen/products/index.php?msg=deleted');
exit;
