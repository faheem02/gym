<?php
$activePage = 'members';
$pageTitle = 'Edit Member';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    echo '<div class="alert alert-warning">Member not found. <a href="index.php">Back to members</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$error = '';
$trainers = $pdo->query('SELECT id, name, specialty FROM trainers ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $join_date = trim($_POST['join_date'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $trainer_id = (int)($_POST['trainer_id'] ?? 0);

    if ($name === '' || $phone === '' || $join_date === '') {
        $error = 'Name, phone and join date are required.';
    } else {
        $stmt = $pdo->prepare('UPDATE members SET name = ?, phone = ?, email = ?, join_date = ?, status = ?, trainer_id = ? WHERE id = ?');
        $stmt->execute([$name, $phone, $email ?: null, $join_date, $status, $trainer_id > 0 ? $trainer_id : null, $id]);
        header('Location: /gym/members/index.php?msg=updated');
        exit;
    }
}
?>

<div class="card form-card">
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Full Name *</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($member['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-phone me-1 text-muted"></i>Phone *</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($member['phone']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-envelope me-1 text-muted"></i>Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Join Date *</label>
                    <input type="date" name="join_date" class="form-control" value="<?php echo $member['join_date']; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-toggle-on me-1 text-muted"></i>Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo $member['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $member['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user-tie me-1 text-muted"></i>Assign Trainer</label>
                <select name="trainer_id" class="form-select">
                    <option value="0">-- No Trainer --</option>
                    <?php foreach ($trainers as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo ($member['trainer_id'] ?? null) == $t['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['specialty'] ?? 'General'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Update Member</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
