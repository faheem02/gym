<?php
$activePage = 'expenses';
$pageTitle = 'Add Expense';
include __DIR__ . '/../includes/header.php';

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
        $stmt = $pdo->prepare('INSERT INTO expenses (category_id, amount, expense_date, payment_method, description, receipt_no) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$category_id, $amount, $expense_date, $payment_method, $description ?: null, $receipt_no ?: null]);
        header('Location: /gym/expenses/index.php?msg=added');
        exit;
    }
}
?>

<div class="mb-4">
    <a href="/gym/expenses/" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="card form-card" style="max-width:640px;border-top:3px solid #f7b731;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-receipt me-2" style="color:#f7b731;"></i>Add Expense</h5>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-tag me-1 text-muted"></i>Category *</label>
                <select name="category_id" class="form-select" required>
                    <option value="">Select category...</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><i class="fas fa-money-bill me-1 text-muted"></i>Amount (Rs.) *</label>
                    <input type="number" step="1" name="amount" class="form-control form-control-lg" min="1" required placeholder="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar me-1 text-muted"></i>Date *</label>
                    <input type="date" name="expense_date" class="form-control form-control-lg" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-credit-card me-1 text-muted"></i>Payment Method</label>
                <select name="payment_method" class="form-select">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="easypaisa">Easypaisa</option>
                    <option value="jazzcash">JazzCash</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-sticky-note me-1 text-muted"></i>Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Expense details..."></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-file-invoice me-1 text-muted"></i>Receipt No.</label>
                <input type="text" name="receipt_no" class="form-control" placeholder="Optional receipt/reference number">
            </div>
            <button type="submit" class="btn btn-lg fw-bold w-100" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-save me-1"></i>Save Expense</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
