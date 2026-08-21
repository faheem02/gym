<?php
$activePage = 'staff';
$pageTitle = 'Add Staff';
include __DIR__ . '/../includes/header.php';

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
        $stmt = $pdo->prepare('INSERT INTO staff (name, phone, email, role, salary, address, emergency_contact, emergency_phone, join_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $phone, $email ?: null, $role, $salary, $address ?: null, $emergency_contact ?: null, $emergency_phone ?: null, $join_date, $status, $notes ?: null]);
        header('Location: /gym/staff/index.php?msg=added');
        exit;
    }
}
?>

<div class="card form-card" style="max-width: 720px;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-user-plus text-warning me-2"></i>Add New Staff</h5>

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
                <input type="text" name="name" class="form-control" placeholder="Enter full name" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-phone me-1 text-muted"></i>Phone *</label>
                <input type="text" name="phone" class="form-control" placeholder="03XX-XXXXXXX" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-envelope me-1 text-muted"></i>Email</label>
                <input type="email" name="email" class="form-control" placeholder="Enter email address">
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted"><i class="fas fa-briefcase me-1"></i> Job Details</h6>
                <hr class="mt-1">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-briefcase me-1 text-muted"></i>Role *</label>
                    <select name="role" class="form-select" required>
                        <option value="receptionist">Receptionist</option>
                        <option value="trainer">Trainer</option>
                        <option value="helper">Helper</option>
                        <option value="cleaner">Cleaner</option>
                        <option value="manager">Manager</option>
                        <option value="accountant">Accountant</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-money-bill me-1 text-muted"></i>Monthly Salary (Rs.)</label>
                    <input type="number" step="1" name="salary" class="form-control" value="0" min="0">
                </div>
            </div>

            <div class="section-label mb-3 mt-4">
                <h6 class="fw-bold text-muted"><i class="fas fa-info-circle me-1"></i> Additional Details</h6>
                <hr class="mt-1">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-map-marker-alt me-1 text-muted"></i>Address</label>
                <input type="text" name="address" class="form-control" placeholder="Enter address">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-user-friends me-1 text-muted"></i>Emergency Contact Name</label>
                    <input type="text" name="emergency_contact" class="form-control" placeholder="Contact person name">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-phone-alt me-1 text-muted"></i>Emergency Phone</label>
                    <input type="text" name="emergency_phone" class="form-control" placeholder="03XX-XXXXXXX">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Join Date *</label>
                    <input type="date" name="join_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-toggle-on me-1 text-muted"></i>Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-sticky-note me-1 text-muted"></i>Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Save Staff</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
