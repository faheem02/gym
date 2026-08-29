<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../auth.php';

$id = (int)($_GET['id'] ?? 0);
$date = trim($_GET['date'] ?? date('Y-m-d'));

if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM day_passes WHERE id = ?');
    $stmt->execute([$id]);
}

header('Location: /gym/day_passes/index.php?msg=deleted&date=' . urlencode($date));
exit;
