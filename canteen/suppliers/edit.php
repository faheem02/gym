<?php
$activePage = 'canteen_suppliers';
$pageTitle = 'Edit Supplier';
include __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM canteen_suppliers WHERE id = ?');
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    echo '<div class="alert alert-warning">Supplier not found. <a href="index.php">Back</a></div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $balance = (float)($_POST['balance'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    if ($name === '') {
        $error = 'Supplier name is required.';
    } else {
        $stmt = $pdo->prepare('UPDATE canteen_suppliers SET name=?, phone=?, email=?, address=?, balance=?, status=? WHERE id=?');
        $stmt->execute([$name, $phone ?: null, $email ?: null, $address ?: null, $balance, $status, $id]);
        header('Location: /gym/canteen/suppliers/index.php?msg=updated');
        exit;
    }
}
?>

<div class="card form-card" style="max-width: 640px;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-edit text-warning me-2"></i>Edit Supplier</h5>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Supplier Name *</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($supplier['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-phone me-1 text-muted"></i>Phone</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-envelope me-1 text-muted"></i>Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-map-marker-alt me-1 text-muted"></i>Address</label>
                <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-coins me-1 text-muted"></i>Balance (Rs.)</label>
                <input type="number" step="1" name="balance" class="form-control" value="<?php echo $supplier['balance']; ?>" placeholder="0">
                <small class="text-muted">Due amount (positive) or advance paid (negative)</small>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-toggle-on me-1 text-muted"></i>Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?php echo $supplier['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $supplier['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Update</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
