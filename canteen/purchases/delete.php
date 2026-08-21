<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /gym/canteen/purchases/index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM canteen_purchases WHERE id = ?');
$stmt->execute([$id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    header('Location: /gym/canteen/purchases/index.php');
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM canteen_purchase_items WHERE purchase_id = ?');
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();

    foreach ($items as $item) {
        $stmt = $pdo->prepare('UPDATE canteen_products SET stock_qty = stock_qty - ? WHERE id = ?');
        $stmt->execute([$item['qty'], $item['product_id']]);
    }

    $due = (float)$purchase['total_amount'] - (float)$purchase['paid_amount'];
    if ($due > 0 && $purchase['supplier_id']) {
        $stmt = $pdo->prepare('UPDATE canteen_suppliers SET balance = balance - ? WHERE id = ?');
        $stmt->execute([$due, $purchase['supplier_id']]);
    }

    $stmt = $pdo->prepare('DELETE FROM canteen_purchase_items WHERE purchase_id = ?');
    $stmt->execute([$id]);

    $stmt = $pdo->prepare('DELETE FROM canteen_purchases WHERE id = ?');
    $stmt->execute([$id]);

    $pdo->commit();
    header('Location: /gym/canteen/purchases/index.php?msg=deleted');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: /gym/canteen/purchases/index.php');
    exit;
}
