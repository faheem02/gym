<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM staff_salaries WHERE id = ?');
    $stmt->execute([$id]);
}
header('Location: /gym/staff/salaries.php?msg=deleted');
exit;
