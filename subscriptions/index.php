<?php
$activePage = 'subscriptions';
$pageTitle = 'Subscriptions';
include __DIR__ . '/../includes/header.php';

$pdo->exec("UPDATE subscriptions SET status = 'expired' WHERE end_date < CURDATE() AND status = 'active'");

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Subscription added successfully.</div>';
if ($msg === 'renewed') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Subscription renewed.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Subscription deleted.</div>';
if ($msg === 'trainer') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Trainer assigned successfully.</div>';

$search = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM (
    SELECT s.id AS sub_id, s.start_date, s.end_date, s.status,
           m.id AS member_id, m.name AS member_name, m.phone,
           p.name AS plan_name,
           t.name AS trainer_name
    FROM subscriptions s
    JOIN members m ON m.id = s.member_id
    JOIN plans p ON p.id = s.plan_id
    LEFT JOIN trainers t ON t.id = m.trainer_id

    UNION ALL

    SELECT NULL, NULL, NULL, 'no_plan',
           m.id, m.name, m.phone, NULL, t.name
    FROM members m
    JOIN trainers t ON t.id = m.trainer_id
    WHERE NOT EXISTS (SELECT 1 FROM subscriptions s2 WHERE s2.member_id = m.id)
) x";
$params = [];
if ($search !== '') {
    $sql .= " WHERE x.member_name LIKE ? OR x.phone LIKE ? OR x.plan_name LIKE ? OR x.trainer_name LIKE ?";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= " ORDER BY x.sub_id DESC, x.member_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<div class="page-header">
    <div></div>
    <div class="d-flex gap-2">
        <a href="trainer.php" class="btn btn-dark fw-bold"><i class="fas fa-user-tie me-1"></i>Assign Trainer</a>
        <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>Assign Plan</a>
    </div>
</div>

<div class="search-bar">
    <form method="GET" action="" class="d-flex align-items-center gap-2">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search by member name, phone, plan or trainer...">
        <button class="btn btn-dark btn-sm text-nowrap px-3" type="submit"><i class="fas fa-search me-1"></i>Search</button>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Plan</th>
                    <th>Trainer</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days Left</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-id-card me-1"></i>No records found.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $s): ?>
                    <?php
                    $isNoPlan = ($s['status'] === 'no_plan');
                    $daysLeft = $s['end_date'] ? (int)ceil((strtotime($s['end_date']) - strtotime(date('Y-m-d'))) / 86400) : null;
                    ?>
                    <tr <?php if ($isNoPlan) echo 'class="table-light"'; ?>>
                        <td><?php echo $s['sub_id'] ?? '-'; ?></td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($s['member_name']); ?></div>
                            <small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($s['phone']); ?></small>
                        </td>
                        <td>
                            <?php if ($isNoPlan): ?>
                                <span class="badge text-bg-secondary">No Plan</span>
                            <?php else: ?>
                                <span class="badge text-bg-dark"><?php echo htmlspecialchars($s['plan_name']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($s['trainer_name'])): ?>
                                <span class="badge text-bg-info"><i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($s['trainer_name']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $s['start_date'] ? date('d M Y', strtotime($s['start_date'])) : '-'; ?></td>
                        <td><?php echo $s['end_date'] ? date('d M Y', strtotime($s['end_date'])) : '-'; ?></td>
                        <td>
                            <?php if ($isNoPlan): ?>
                                <span class="text-muted">-</span>
                            <?php elseif ($s['status'] === 'active'): ?>
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
                            <?php if ($isNoPlan): ?>
                                <span class="badge text-bg-secondary">No Plan</span>
                            <?php else: ?>
                                <span class="badge <?php echo $s['status'] === 'active' ? 'badge-active' : 'badge-expired'; ?>">
                                    <?php echo ucfirst($s['status']); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($isNoPlan): ?>
                                <a href="add.php" class="btn btn-sm btn-outline-warning" title="Assign Plan"><i class="fas fa-plus"></i></a>
                            <?php else: ?>
                                <?php if ($s['status'] === 'active'): ?>
                                    <a href="renew.php?id=<?php echo $s['sub_id']; ?>" class="btn btn-sm btn-outline-success" title="Renew"><i class="fas fa-sync-alt"></i></a>
                                <?php endif; ?>
                                <a href="delete.php?id=<?php echo $s['sub_id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this subscription?');"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
