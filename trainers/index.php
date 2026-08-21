<?php
$activePage = 'trainers';
$pageTitle = 'Trainers';
include __DIR__ . '/../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Trainer added successfully.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Trainer updated successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Trainer deleted.</div>';

$trainers = $pdo->query('SELECT t.*, (SELECT COUNT(*) FROM members m WHERE m.trainer_id = t.id AND m.status = "active") AS member_count FROM trainers t ORDER BY t.id DESC')->fetchAll();
?>

<div class="page-header">
    <div></div>
    <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>Add Trainer</a>
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
                    <th>Specialty</th>
                    <th class="text-center">Members</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($trainers)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-chalkboard-teacher me-1"></i>No trainers found.</td></tr>
                <?php endif; ?>
                <?php foreach ($trainers as $t): ?>
                    <tr>
                        <td><?php echo $t['id']; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($t['name']); ?></td>
                        <td><?php echo htmlspecialchars($t['phone']); ?></td>
                        <td><?php echo htmlspecialchars($t['email'] ?? '-'); ?></td>
                        <td><span class="badge text-bg-dark"><i class="fas fa-star me-1"></i><?php echo htmlspecialchars($t['specialty'] ?? '-'); ?></span></td>
                        <td class="text-center">
                            <span class="badge <?php echo $t['member_count'] > 0 ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                <?php echo $t['member_count']; ?> member<?php echo $t['member_count'] !== 1 ? 's' : ''; ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if ($t['member_count'] > 0): ?>
                                <a href="members.php?trainer_id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-info" title="View Members"><i class="fas fa-eye"></i></a>
                            <?php endif; ?>
                            <a href="edit.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                            <a href="delete.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this trainer?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
