<?php
$activePage = 'trainers';
$pageTitle = 'Edit Trainer';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM trainers WHERE id = ?');
$stmt->execute([$id]);
$trainer = $stmt->fetch();

if (!$trainer) {
    echo '<div class="alert alert-warning">Trainer not found. <a href="index.php">Back to trainers</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');

    if ($name === '' || $phone === '') {
        $error = 'Name and phone are required.';
    } else {
        $stmt = $pdo->prepare('UPDATE trainers SET name = ?, phone = ?, email = ?, specialty = ? WHERE id = ?');
        $stmt->execute([$name, $phone, $email ?: null, $specialty ?: null, $id]);
        header('Location: /gym/trainers/index.php?msg=updated');
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
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($trainer['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-phone me-1 text-muted"></i>Phone *</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($trainer['phone']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-envelope me-1 text-muted"></i>Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($trainer['email'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-star me-1 text-muted"></i>Specialty</label>
                <input type="text" name="specialty" class="form-control" value="<?php echo htmlspecialchars($trainer['specialty'] ?? ''); ?>">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Update Trainer</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
