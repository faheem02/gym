<?php
$activePage = 'staff_salaries';
$pageTitle = 'Staff Salaries';
include __DIR__ . '/../includes/header.php';

$error = '';
$currentMonth = date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = (int)($_POST['staff_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_type = ($_POST['payment_type'] ?? 'salary') === 'advance' ? 'advance' : 'salary';
    $salary_month = trim($_POST['salary_month'] ?? '');
    $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $notes = trim($_POST['notes'] ?? '');

    if ($staff_id <= 0) {
        $error = 'Select a staff member.';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than 0.';
    } elseif (!preg_match('/^\d{4}-\d{2}$/', $salary_month)) {
        $error = 'Select the salary month.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO staff_salaries (staff_id, amount, payment_type, salary_month, payment_date, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$staff_id, $amount, $payment_type, $salary_month, $payment_date, $payment_method, $notes ?: null]);
        header('Location: /gym/staff/salaries.php?msg=' . ($payment_type === 'advance' ? 'advance' : 'paid') . '&month=' . urlencode($salary_month));
        exit;
    }
}

$msg = $_GET['msg'] ?? '';
if ($msg === 'paid') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Salary payment recorded successfully.</div>';
if ($msg === 'advance') echo '<div class="alert alert-warning py-2"><i class="fas fa-hand-holding-usd me-1"></i>Advance payment recorded successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Salary payment deleted.</div>';

$staff = $pdo->query('SELECT id, name, role, salary FROM staff WHERE status = "active" ORDER BY name')->fetchAll();

// Prefill form via GET (?type=advance&staff_id=X) e.g. from staff view modal
$prefillType = ($_GET['type'] ?? '') === 'advance' ? 'advance' : 'salary';
$prefillStaff = (int)($_GET['staff_id'] ?? 0);
$formType = $_POST['payment_type'] ?? $prefillType;

$month = trim($_GET['month'] ?? $currentMonth);
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = $currentMonth;

// Stats for selected month
$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS total FROM staff_salaries WHERE salary_month = ?');
$stmt->execute([$month]);
$paidThisMonth = (float)$stmt->fetch()['total'];
$payroll = (float)$pdo->query('SELECT COALESCE(SUM(salary),0) FROM staff WHERE status = "active"')->fetchColumn();
$pending = max(0, $payroll - $paidThisMonth);

// Payment history (latest first)
$history = $pdo->query(
    "SELECT sp.*, s.name AS staff_name, s.role
     FROM staff_salaries sp
     JOIN staff s ON s.id = sp.staff_id
     ORDER BY sp.payment_date DESC, sp.id DESC
     LIMIT 100"
)->fetchAll();

// Who is paid / pending for selected month
$paidMap = [];
$stmt = $pdo->prepare('SELECT staff_id, COALESCE(SUM(amount),0) AS paid FROM staff_salaries WHERE salary_month = ? GROUP BY staff_id');
$stmt->execute([$month]);
foreach ($stmt->fetchAll() as $r) $paidMap[$r['staff_id']] = (float)$r['paid'];
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card" style="border-top:3px solid #6c5ce7;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(108,92,231,0.1);color:#6c5ce7;"><i class="fas fa-users"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($payroll); ?></h5>
                    <small class="text-muted">Monthly Payroll (Active)</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card" style="border-top:3px solid #00b894;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(0,184,148,0.1);color:#00b894;"><i class="fas fa-check-double"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($paidThisMonth); ?></h5>
                    <small class="text-muted">Paid (<?php echo date('M Y', strtotime($month . '-01')); ?>)</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card" style="border-top:3px solid #e74c3c;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(231,76,60,0.1);color:#e74c3c;"><i class="fas fa-exclamation-circle"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($pending); ?></h5>
                    <small class="text-muted">Pending (<?php echo date('M Y', strtotime($month . '-01')); ?>)</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card" style="border-top:3px solid #00b894;">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-hand-holding-usd text-success me-2"></i><span id="payFormTitle">Pay Salary</span></h6>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="payment_type" value="salary" id="ptSalary" onchange="updatePayType()" <?php echo $formType !== 'advance' ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-success" for="ptSalary"><i class="fas fa-money-bill-wave me-1"></i>Salary</label>
                        <input type="radio" class="btn-check" name="payment_type" value="advance" id="ptAdvance" onchange="updatePayType()" <?php echo $formType === 'advance' ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-warning" for="ptAdvance"><i class="fas fa-hand-holding-usd me-1"></i>Advance</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Staff *</label>
                        <select name="staff_id" id="staffSelect" class="form-select" required onchange="fillSalary()">
                            <option value="">-- Select Staff --</option>
                            <?php foreach ($staff as $s): ?>
                                <option value="<?php echo $s['id']; ?>" data-salary="<?php echo $s['salary']; ?>" <?php echo ($_POST['staff_id'] ?? $prefillStaff) == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?> (<?php echo ucfirst($s['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold" id="monthLabel">Salary Month *</label>
                            <input type="month" name="salary_month" class="form-control" value="<?php echo htmlspecialchars($_POST['salary_month'] ?? $currentMonth); ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Amount (Rs.) *</label>
                            <input type="number" step="1" min="1" name="amount" id="amountInput" class="form-control" placeholder="0" value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Method</label>
                            <select name="payment_method" class="form-select">
                                <?php foreach (['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer', 'easypaisa' => 'EasyPaisa', 'jazzcash' => 'JazzCash'] as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo ($_POST['payment_method'] ?? 'cash') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional note" value="<?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-success fw-bold w-100"><i class="fas fa-check-circle me-1"></i><span id="payBtnText">Record Payment</span></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100" style="border-top:3px solid #6c5ce7;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0"><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Status &mdash; <?php echo date('F Y', strtotime($month . '-01')); ?></h6>
                    <form method="GET" action="" class="d-flex">
                        <input type="month" name="month" class="form-control form-control-sm me-2" value="<?php echo htmlspecialchars($month); ?>">
                        <button class="btn btn-dark btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Staff</th><th>Role</th><th class="text-end">Monthly Salary</th><th class="text-end">Paid</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($staff as $s): ?>
                                <?php $paid = $paidMap[$s['id']] ?? 0; ?>
                                <tr>
                                    <td class="fw-semibold"><a href="ledger.php?id=<?php echo $s['id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($s['name']); ?></a></td>
                                    <td><small class="text-muted"><?php echo ucfirst($s['role']); ?></small></td>
                                    <td class="text-end">Rs.<?php echo number_format($s['salary'], 0); ?></td>
                                    <td class="text-end fw-bold text-success">Rs.<?php echo number_format($paid, 0); ?></td>
                                    <td>
                                        <?php if ($paid >= (float)$s['salary']): ?>
                                            <span class="badge text-bg-success">Paid</span>
                                        <?php elseif ($paid > 0): ?>
                                            <span class="badge text-bg-warning">Partial</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-danger">Due</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($staff)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No active staff found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="border-top:3px solid #f7b731;">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="fas fa-history text-warning me-2"></i>Payment History</h6>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>#</th><th>Date</th><th>Staff</th><th>Salary Month</th><th>Method</th><th>Notes</th><th class="text-end">Amount</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-money-bill me-1"></i>No salary payments recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($history as $i => $p): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                            <td class="fw-semibold"><a href="ledger.php?id=<?php echo $p['staff_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($p['staff_name']); ?></a></td>
                            <td>
                                <span class="badge text-bg-dark"><?php echo date('M Y', strtotime($p['salary_month'] . '-01')); ?></span>
                                <?php if (($p['payment_type'] ?? 'salary') === 'advance'): ?>
                                    <span class="badge text-bg-warning"><i class="fas fa-hand-holding-usd me-1"></i>Advance</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></small></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($p['notes'] ?? '-'); ?></small></td>
                            <td class="text-end fw-bold text-success">Rs.<?php echo number_format($p['amount'], 0); ?></td>
                            <td class="text-end">
                                <a href="salary_delete.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this salary payment?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function fillSalary() {
    var sel = document.getElementById('staffSelect');
    var opt = sel.options[sel.selectedIndex];
    var amount = document.getElementById('amountInput');
    if (opt && opt.getAttribute('data-salary')) {
        amount.value = parseFloat(opt.getAttribute('data-salary'));
    }
}

function updatePayType() {
    var isAdvance = document.getElementById('ptAdvance').checked;
    document.getElementById('payFormTitle').textContent = isAdvance ? 'Give Advance' : 'Pay Salary';
    document.getElementById('monthLabel').textContent = isAdvance ? 'Adjust Against Month *' : 'Salary Month *';
    document.getElementById('payBtnText').textContent = isAdvance ? 'Record Advance' : 'Record Payment';
}
updatePayType();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
