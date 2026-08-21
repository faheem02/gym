<?php
$activePage = 'subscriptions';
$pageTitle = 'Subscriptions';
include __DIR__ . '/../includes/header.php';

$pdo->exec("UPDATE subscriptions SET status = 'expired' WHERE end_date < CURDATE() AND status = 'active'");

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Subscription added successfully.</div>';
if ($msg === 'renewed') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Subscription renewed.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Subscription deleted.</div>';

$rows = $pdo->query(
    "SELECT s.id, s.start_date, s.end_date, s.status,
            m.name AS member_name, m.phone, p.name AS plan_name, p.duration_days
     FROM subscriptions s
     JOIN members m ON m.id = s.member_id
     JOIN plans p ON p.id = s.plan_id
     ORDER BY s.id DESC"
)->fetchAll();
?>

<div class="page-header">
    <div></div>
    <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>Assign Plan</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Plan</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days Left</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-id-card me-1"></i>No subscriptions found.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $s): ?>
                    <?php
                    $daysLeft = (strtotime($s['end_date']) - strtotime(date('Y-m-d'))) / 86400;
                    $daysLeft = (int)ceil($daysLeft);
                    ?>
                    <tr>
                        <td><?php echo $s['id']; ?></td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($s['member_name']); ?></div>
                            <small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($s['phone']); ?></small>
                        </td>
                        <td><span class="badge text-bg-dark"><?php echo htmlspecialchars($s['plan_name']); ?></span></td>
                        <td><?php echo date('d M Y', strtotime($s['start_date'])); ?></td>
                        <td><?php echo date('d M Y', strtotime($s['end_date'])); ?></td>
                        <td>
                            <?php if ($s['status'] === 'active'): ?>
                                <?php if ($daysLeft <= 7): ?>
                                    <span class="badge text-bg-warning"><i class="fas fa-clock me-1"></i><?php echo $daysLeft; ?> days</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success"><i class="fas fa-clock me-1"></i><?php echo $daysLeft; ?> days</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $s['status'] === 'active' ? 'badge-active' : 'badge-expired'; ?>">
                                <?php echo ucfirst($s['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if ($s['status'] === 'active'): ?>
                                <a href="renew.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-success" title="Renew"><i class="fas fa-sync-alt"></i></a>
                            <?php endif; ?>
                            <a href="delete.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this subscription?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
