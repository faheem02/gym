<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /gym/canteen/sales/'); exit; }

$stmt = $pdo->prepare("SELECT id, receipt_no FROM canteen_sales WHERE id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale) { header('Location: /gym/canteen/sales/'); exit; }

// Delete sale items first (FK), then the sale
// Note: stock is NOT restored — deleting a sale doesn't un-consume inventory
$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM canteen_sale_items WHERE sale_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM canteen_stock_log WHERE reference_id = ? AND type = 'sale'")->execute([$id]);
    $pdo->prepare("DELETE FROM canteen_sales WHERE id = ?")->execute([$id]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: /gym/canteen/sales/?error=delete_failed');
    exit;
}

header('Location: /gym/canteen/sales/?deleted=1');
exit;
