<?php
$activePage = 'staff';
$pageTitle = 'Edit Staff';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ?');
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) {
    echo '<div class="alert alert-warning">Staff member not found. <a href="index.php">Back to staff</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'receptionist';
    $salary = (float)($_POST['salary'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $emergency_phone = trim($_POST['emergency_phone'] ?? '');
    $join_date = trim($_POST['join_date'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '' || $phone === '' || $join_date === '') {
        $error = 'Name, phone and join date are required.';
    } else {
        $stmt = $pdo->prepare('UPDATE staff SET name = ?, phone = ?, email = ?, role = ?, salary = ?, address = ?, emergency_contact = ?, emergency_phone = ?, join_date = ?, status = ?, notes = ? WHERE id = ?');
        $stmt->execute([$name, $phone, $email ?: null, $role, $salary, $address ?: null, $emergency_contact ?: null, $emergency_phone ?: null, $join_date, $status, $notes ?: null, $id]);
        header('Location: /gym/staff/index.php?msg=updated');
        exit;
    }
}
?>

<div class="card form-card" style="max-width: 720px;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-user-edit text-warning me-2"></i>Edit Staff — <?php echo htmlspecialchars($staff['name']); ?></h5>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="section-label mb-3">
                <h6 class="fw-bold text-muted"><i class="fas fa-user me-1"></i> Personal Information</h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user me-1 text-muted"></i>Full Name *</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($staff['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-phone me-1 text-muted"></i>Phone *</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($staff['phone']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-envelope me-1 text-muted"></i>Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($staff['email'] ?? ''); ?>">
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted"><i class="fas fa-briefcase me-1"></i> Job Details</h6>
                <hr class="mt-1">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-briefcase me-1 text-muted"></i>Role *</label>
                    <select name="role" class="form-select" required>
                        <?php $roles = ['receptionist','trainer','helper','cleaner','manager','accountant','other']; foreach ($roles as $r): ?>
                            <option value="<?php echo $r; ?>" <?php echo $staff['role'] === $r ? 'selected' : ''; ?>><?php echo ucfirst($r); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-money-bill me-1 text-muted"></i>Monthly Salary (Rs.)</label>
                    <input type="number" step="1" name="salary" class="form-control" value="<?php echo $staff['salary']; ?>" min="0">
                </div>
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted"><i class="fas fa-info-circle me-1"></i> Additional Details</h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-map-marker-alt me-1 text-muted"></i>Address</label>
                <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($staff['address'] ?? ''); ?>">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-user-friends me-1 text-muted"></i>Emergency Contact Name</label>
                    <input type="text" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($staff['emergency_contact'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-phone-alt me-1 text-muted"></i>Emergency Phone</label>
                    <input type="text" name="emergency_phone" class="form-control" value="<?php echo htmlspecialchars($staff['emergency_phone'] ?? ''); ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Join Date *</label>
                    <input type="date" name="join_date" class="form-control" value="<?php echo $staff['join_date']; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-toggle-on me-1 text-muted"></i>Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo $staff['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $staff['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-sticky-note me-1 text-muted"></i>Notes</label>
                <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($staff['notes'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Update Staff</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
