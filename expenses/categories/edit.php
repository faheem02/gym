<?php
$activePage = 'expense_categories';
$pageTitle = 'Edit Category';
include __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM expense_categories WHERE id = ?');
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) { echo '<div class="alert alert-warning">Not found. <a href="index.php">Back</a></div>'; include __DIR__ . '/../../includes/footer.php'; exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    if ($name === '') {
        $error = 'Category name is required.';
    } else {
        $stmt = $pdo->prepare('UPDATE expense_categories SET name=?, description=?, status=? WHERE id=?');
        $stmt->execute([$name, $description ?: null, $status, $id]);
        header('Location: /gym/expenses/categories/index.php?msg=updated');
        exit;
    }
}
?>

<div class="mb-4">
    <a href="index.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="card form-card" style="max-width:540px;border-top:3px solid #f7b731;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-edit me-2" style="color:#f7b731;"></i>Edit Category</h5>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-tag me-1 text-muted"></i>Category Name *</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($category['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-info-circle me-1 text-muted"></i>Description</label>
                <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-toggle-on me-1 text-muted"></i>Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?php echo $category['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $category['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-lg fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-save me-1"></i>Update</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
