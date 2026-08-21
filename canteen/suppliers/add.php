<?php
$activePage = 'canteen_suppliers';
$pageTitle = 'Add Supplier';
include __DIR__ . '/../../includes/header.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $balance = (float)($_POST['balance'] ?? 0);
    if ($name === '') {
        $error = 'Supplier name is required.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO canteen_suppliers (name, phone, email, address, balance) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $phone ?: null, $email ?: null, $address ?: null, $balance]);
        header('Location: /gym/canteen/suppliers/index.php?msg=added');
        exit;
    }
}
?>

<div class="card form-card" style="max-width: 640px;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-truck text-warning me-2"></i>Add Supplier</h5>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Supplier Name *</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Protein House, Fresh Dairy" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-phone me-1 text-muted"></i>Phone</label>
                <input type="text" name="phone" class="form-control" placeholder="03XX-XXXXXXX">
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-envelope me-1 text-muted"></i>Email</label>
                <input type="email" name="email" class="form-control" placeholder="supplier@example.com">
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-map-marker-alt me-1 text-muted"></i>Address</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Supplier address..."></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-coins me-1 text-muted"></i>Opening Balance (Rs.)</label>
                <input type="number" step="1" name="balance" class="form-control" value="0" placeholder="0">
                <small class="text-muted">Due amount (positive) or advance paid (negative)</small>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Save Supplier</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
