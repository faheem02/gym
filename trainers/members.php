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

$search = trim($_GET['search'] ?? '');
$sql = 'SELECT * FROM members WHERE trainer_id = ?';
$params = [$trainer_id];
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY name ASC';
$membersStmt = $pdo->prepare($sql);
$membersStmt->execute($params);
$members = $membersStmt->fetchAll();
?>

<div class="mb-4">
    <a href="index.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back to Trainers</a>
</div>

<div class="card mb-4 shadow-sm" style="border-top:3px solid #f7b731;">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div style="width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#f7b731,#f5a623);display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-user-tie text-white" style="font-size:1.3rem;"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($trainer['name']); ?></h5>
                <small class="text-muted"><i class="fas fa-star me-1"></i><?php echo htmlspecialchars($trainer['specialty'] ?? 'General'); ?> &nbsp;|&nbsp; <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($trainer['phone']); ?></small>
            </div>
            <div class="ms-auto">
                <span class="badge text-bg-success fs-6"><?php echo count($members); ?> Assigned Member<?php echo count($members) !== 1 ? 's' : ''; ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Search Members Bar -->
<div class="card mb-4 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="trainer_id" value="<?php echo $trainer_id; ?>">
            <div class="col-md-9">
                <label class="form-label small fw-bold mb-1">Search Assigned Members</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by member name, phone..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-warning btn-sm flex-fill fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="members.php?trainer_id=<?php echo $trainer_id; ?>" class="btn btn-outline-secondary btn-sm" title="Clear"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
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
                    <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-users-slash me-1"></i>No matching members found for this trainer.</td></tr>
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
