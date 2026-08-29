<?php
$activePage = 'trainers';
$pageTitle = 'Trainers';
include __DIR__ . '/../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Trainer added successfully.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Trainer updated successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Trainer deleted.</div>';

$search = trim($_GET['search'] ?? '');
$specialty = trim($_GET['specialty'] ?? '');

$sql = 'SELECT t.*, (SELECT COUNT(*) FROM members m WHERE m.trainer_id = t.id AND m.status = "active") AS member_count FROM trainers t WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (t.name LIKE ? OR t.phone LIKE ? OR t.email LIKE ? OR t.specialty LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($specialty !== '') {
    $sql .= ' AND t.specialty = ?';
    $params[] = $specialty;
}

$sql .= ' ORDER BY t.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$trainers = $stmt->fetchAll();

$allSpecialties = $pdo->query("SELECT DISTINCT specialty FROM trainers WHERE specialty IS NOT NULL AND specialty != '' ORDER BY specialty")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0"><i class="fas fa-user-tie text-warning me-2"></i>Gym Trainers</h5>
        <small class="text-muted">Manage gym trainers, specialties, and assigned members</small>
    </div>
    <a href="add.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-plus me-1"></i>Add Trainer</a>
</div>

<!-- Search & Filter Bar -->
<div class="card mb-4 shadow-sm" style="border-top:3px solid #f7b731;">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-bold mb-1">Search Trainer</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, phone, specialty..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold mb-1">Specialty</label>
                <select name="specialty" class="form-select form-select-sm">
                    <option value="">All Specialties</option>
                    <?php foreach ($allSpecialties as $spec): ?>
                        <option value="<?php echo htmlspecialchars($spec); ?>" <?php echo $specialty === $spec ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($spec); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-warning btn-sm flex-fill fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="fas fa-list text-muted me-2"></i>Trainers List (<?php echo count($trainers); ?>)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Specialty</th>
                    <th class="text-center">Active Members</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($trainers)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-chalkboard-teacher fa-2x mb-2 text-warning"></i><br>No trainers found.</td></tr>
                <?php endif; ?>
                <?php foreach ($trainers as $t): ?>
                    <tr>
                        <td><?php echo $t['id']; ?></td>
                        <td class="fw-bold text-dark">
                            <i class="fas fa-user-tie text-muted me-1"></i><?php echo htmlspecialchars($t['name']); ?>
                        </td>
                        <td><i class="fas fa-phone small text-muted me-1"></i><?php echo htmlspecialchars($t['phone']); ?></td>
                        <td><?php echo htmlspecialchars($t['email'] ?? '-'); ?></td>
                        <td><span class="badge text-bg-dark px-2 py-1"><i class="fas fa-star me-1 text-warning"></i><?php echo htmlspecialchars($t['specialty'] ?? 'General'); ?></span></td>
                        <td class="text-center">
                            <?php if ($t['member_count'] > 0): ?>
                                <a href="members.php?trainer_id=<?php echo $t['id']; ?>" class="badge text-bg-success text-decoration-none px-2 py-1">
                                    <i class="fas fa-users me-1"></i><?php echo $t['member_count']; ?> member<?php echo $t['member_count'] !== 1 ? 's' : ''; ?>
                                </a>
                            <?php else: ?>
                                <span class="badge text-bg-secondary px-2 py-1">0 members</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <?php if ($t['member_count'] > 0): ?>
                                    <a href="members.php?trainer_id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-info" title="View Members"><i class="fas fa-eye"></i></a>
                                <?php endif; ?>
                                <a href="edit.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                                <a href="delete.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this trainer?');"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
