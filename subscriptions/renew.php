<?php
$activePage = 'subscriptions';
$pageTitle = 'Renew Subscription';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT s.id, s.member_id, s.plan_id, s.end_date,
            m.name AS member_name, p.name AS plan_name, p.duration_days
     FROM subscriptions s
     JOIN members m ON m.id = s.member_id
     JOIN plans p ON p.id = s.plan_id
     WHERE s.id = ?"
);
$stmt->execute([$id]);
$sub = $stmt->fetch();

if (!$sub) {
    echo '<div class="alert alert-warning">Subscription not found. <a href="index.php">Back</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_end = date('Y-m-d', strtotime($sub['end_date'] . ' + ' . $sub['duration_days'] . ' days'));
    $stmt = $pdo->prepare("UPDATE subscriptions SET end_date = ?, status = 'active' WHERE id = ?");
    $stmt->execute([$new_end, $id]);
    header('Location: /gym/subscriptions/index.php?msg=renewed');
    exit;
}
?>

<div class="card form-card">
    <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-sync-alt text-success me-2"></i>Renew subscription for <span class="fw-bold"><?php echo htmlspecialchars($sub['member_name']); ?></span></h5>
        <div class="alert alert-info">
            <div><strong><i class="fas fa-clipboard-list me-1"></i>Plan:</strong> <?php echo htmlspecialchars($sub['plan_name']); ?> (<?php echo $sub['duration_days']; ?> days)</div>
            <div><strong><i class="fas fa-calendar me-1"></i>Current End Date:</strong> <?php echo date('d M Y', strtotime($sub['end_date'])); ?></div>
            <div class="mt-1"><strong><i class="fas fa-calendar-check me-1"></i>New End Date:</strong> <span class="fw-bold text-success"><?php echo date('d M Y', strtotime($sub['end_date'] . ' + ' . $sub['duration_days'] . ' days')); ?></span></div>
        </div>
        <form method="POST" action="">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-check me-1"></i>Confirm Renewal</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
