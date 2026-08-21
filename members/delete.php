<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM members WHERE id = ?');
    $stmt->execute([$id]);
}
header('Location: /gym/members/index.php?msg=deleted');
exit;
