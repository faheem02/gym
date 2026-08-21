<?php
$activePage = 'trainers';
$pageTitle = 'Trainer Members';
include __DIR__ . '/../includes/header.php';

$trainer_id = (int)($_GET['trainer_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM trainers WHERE id = ?');
$stmt->execute([$trainer_id]);
$trainer = $stmt->fetch();

if (!$trainer) {
    echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-1"></i>Trainer not found. <a href="index.php">Back to Trainers</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$members = $pdo->prepare('SELECT * FROM members WHERE trainer_id = ? ORDER BY name ASC');
$members->execute([$trainer_id]);
$members = $members->fetchAll();
?>

<div class="mb-4">
    <a href="index.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back to Trainers</a>
</div>

<div class="card mb-4" style="border-top:3px solid #f7b731;">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3">
            <div style="width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#f7b731,#f5a623);display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-user-tie text-white" style="font-size:1.3rem;"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($trainer['name']); ?></h5>
                <small class="text-muted"><i class="fas fa-star me-1"></i><?php echo htmlspecialchars($trainer['specialty'] ?? 'General'); ?> &nbsp;|&nbsp; <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($trainer['phone']); ?></small>
            </div>
            <div class="ms-auto">
                <span class="badge text-bg-success fs-6"><?php echo count($members); ?> Member<?php echo count($members) !== 1 ? 's' : ''; ?></span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Join Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-users-slash me-1"></i>No members assigned to this trainer yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?php echo $m['id']; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($m['name']); ?></td>
                        <td><?php echo htmlspecialchars($m['phone']); ?></td>
                        <td><?php echo htmlspecialchars($m['email'] ?? '-'); ?></td>
                        <td><?php echo date('d M Y', strtotime($m['join_date'])); ?></td>
                        <td>
                            <span class="badge <?php echo $m['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo ucfirst($m['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
