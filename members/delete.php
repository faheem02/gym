<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$id = (int)($_GET['id'] ?? 0);
$msg = 'deleted';
if ($id > 0) {
    try {
        $stmt = $pdo->prepare('DELETE FROM members WHERE id = ?');
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        $msg = 'delete_failed';
    }
}
header('Location: /gym/members/index.php?msg=' . $msg);
exit;
