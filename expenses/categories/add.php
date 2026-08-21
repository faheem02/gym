<?php
$activePage = 'expense_categories';
$pageTitle = 'Add Category';
include __DIR__ . '/../../includes/header.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($name === '') {
        $error = 'Category name is required.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO expense_categories (name, description) VALUES (?, ?)');
        $stmt->execute([$name, $description ?: null]);
        header('Location: /gym/expenses/categories/index.php?msg=added');
        exit;
    }
}
?>

<div class="mb-4">
    <a href="index.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="card form-card" style="max-width:540px;border-top:3px solid #f7b731;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-folder-plus me-2" style="color:#f7b731;"></i>Add Expense Category</h5>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-tag me-1 text-muted"></i>Category Name *</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Rent, Utilities, Salaries" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-info-circle me-1 text-muted"></i>Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea>
            </div>
            <button type="submit" class="btn btn-lg fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-save me-1"></i>Save Category</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
