<?php
$activePage = 'members';
$pageTitle = 'Members';
include __DIR__ . '/../includes/header.php';

$search = trim($_GET['q'] ?? '');
$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Member added successfully.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Member updated successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Member deleted.</div>';

$sql = 'SELECT m.*, t.name AS trainer_name FROM members m LEFT JOIN trainers t ON m.trainer_id = t.id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE m.name LIKE ? OR m.phone LIKE ? OR m.email LIKE ? OR t.name LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY m.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();
?>

<div class="search-bar">
    <div class="row g-2 align-items-center">
        <div class="col-md-7 col-lg-8">
            <form method="GET" action="" class="d-flex">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control me-2" placeholder="Search by name, phone or email...">
                <button class="btn btn-dark" type="submit"><i class="fas fa-search me-1"></i>Search</button>
            </form>
        </div>
        <div class="col-md-5 col-lg-4 text-md-end">
            <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>Add Member</a>
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
                    <th>Trainer</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-users-slash me-1"></i>No members found.</td></tr>
                <?php endif; ?>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?php echo $m['id']; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($m['name']); ?></td>
                        <td><?php echo htmlspecialchars($m['phone']); ?></td>
                        <td><?php echo htmlspecialchars($m['email'] ?? '-'); ?></td>
                        <td><?php echo date('d M Y', strtotime($m['join_date'])); ?></td>
                        <td>
                            <?php if (!empty($m['trainer_name'])): ?>
                                <span class="badge text-bg-dark"><i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($m['trainer_name']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $m['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo ucfirst($m['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="edit.php?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                            <a href="delete.php?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this member? This also deletes their subscriptions.');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
