<?php
$activePage = 'expense_categories';
$pageTitle = 'Expense Categories';
include __DIR__ . '/../../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Category added.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Category updated.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Category deleted.</div>';

$categories = $pdo->query("
    SELECT ec.*, COALESCE(SUM(e.amount), 0) AS total_expenses,
    (SELECT COUNT(*) FROM expenses WHERE category_id = ec.id) AS expense_count
    FROM expense_categories ec
    LEFT JOIN expenses e ON e.category_id = ec.id
    GROUP BY ec.id
    ORDER BY ec.name ASC
")->fetchAll();
?>

<div class="page-header">
    <div></div>
    <a href="add.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-plus me-1"></i>Add Category</a>
</div>

<div class="card" style="border-top:3px solid #f7b731;">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Category</th><th>Description</th><th class="text-end">Total Expenses</th><th class="text-end">Entries</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-folder me-1"></i>No categories found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($categories as $i => $c): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td class="fw-semibold"><i class="fas fa-tag me-1" style="color:#f7b731;"></i><?php echo htmlspecialchars($c['name']); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($c['description'] ?? '-'); ?></td>
                            <td class="text-end fw-bold">Rs.<?php echo number_format($c['total_expenses'], 0); ?></td>
                            <td class="text-end"><?php echo $c['expense_count']; ?></td>
                            <td><span class="badge <?php echo $c['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                            <td class="text-end">
                                <a href="edit.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                                <a href="delete.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this category?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
