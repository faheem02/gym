<?php
$activePage = 'expenses';
$pageTitle = 'Edit Expense';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM expenses WHERE id = ?');
$stmt->execute([$id]);
$expense = $stmt->fetch();

if (!$expense) { echo '<div class="alert alert-warning">Not found. <a href="index.php">Back</a></div>'; include __DIR__ . '/../includes/footer.php'; exit; }

$categories = $pdo->query("SELECT id, name FROM expense_categories WHERE status = 'active' ORDER BY name")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int)($_POST['category_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $expense_date = trim($_POST['expense_date'] ?? date('Y-m-d'));
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $description = trim($_POST['description'] ?? '');
    $receipt_no = trim($_POST['receipt_no'] ?? '');

    if ($category_id <= 0) { $error = 'Please select a category.'; }
    elseif ($amount <= 0) { $error = 'Amount must be greater than 0.'; }
    else {
        $stmt = $pdo->prepare('UPDATE expenses SET category_id=?, amount=?, expense_date=?, payment_method=?, description=?, receipt_no=? WHERE id=?');
        $stmt->execute([$category_id, $amount, $expense_date, $payment_method, $description ?: null, $receipt_no ?: null, $id]);
        header('Location: /gym/expenses/index.php?msg=updated');
        exit;
    }
}
?>

<div class="mb-4">
    <a href="/gym/expenses/" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="card form-card" style="max-width:640px;border-top:3px solid #f7b731;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-edit me-2" style="color:#f7b731;"></i>Edit Expense</h5>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-tag me-1 text-muted"></i>Category *</label>
                <select name="category_id" class="form-select" required>
                    <option value="">Select category...</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $expense['category_id'] == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><i class="fas fa-money-bill me-1 text-muted"></i>Amount (Rs.) *</label>
                    <input type="number" step="1" name="amount" class="form-control form-control-lg" min="1" required value="<?php echo $expense['amount']; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar me-1 text-muted"></i>Date *</label>
                    <input type="date" name="expense_date" class="form-control form-control-lg" value="<?php echo $expense['expense_date']; ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-credit-card me-1 text-muted"></i>Payment Method</label>
                <select name="payment_method" class="form-select">
                    <?php foreach (['cash'=>'Cash','card'=>'Card','bank_transfer'=>'Bank Transfer','easypaisa'=>'Easypaisa','jazzcash'=>'JazzCash'] as $v => $l): ?>
                        <option value="<?php echo $v; ?>" <?php echo $expense['payment_method'] === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-sticky-note me-1 text-muted"></i>Description</label>
                <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($expense['description'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-file-invoice me-1 text-muted"></i>Receipt No.</label>
                <input type="text" name="receipt_no" class="form-control" value="<?php echo htmlspecialchars($expense['receipt_no'] ?? ''); ?>">
            </div>
            <button type="submit" class="btn btn-lg fw-bold w-100" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-save me-1"></i>Update Expense</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
