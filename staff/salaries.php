<?php
$activePage = 'staff_salaries';
$pageTitle = 'Staff Salaries';
include __DIR__ . '/../includes/header.php';

$error = '';
$currentMonth = date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay_salary') {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_salary') {
    $edit_id = (int)($_POST['payment_id'] ?? 0);
    $staff_id = (int)($_POST['staff_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_type = ($_POST['payment_type'] ?? 'salary') === 'advance' ? 'advance' : 'salary';
    $salary_month = trim($_POST['salary_month'] ?? '');
    $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $notes = trim($_POST['notes'] ?? '');

    if ($edit_id <= 0) {
        $error = 'Invalid payment ID.';
    } elseif ($staff_id <= 0) {
        $error = 'Select a staff member.';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than 0.';
    } elseif (!preg_match('/^\d{4}-\d{2}$/', $salary_month)) {
        $error = 'Select the salary month.';
    } else {
        $stmt = $pdo->prepare('UPDATE staff_salaries SET staff_id = ?, amount = ?, payment_type = ?, salary_month = ?, payment_date = ?, payment_method = ?, notes = ? WHERE id = ?');
        $stmt->execute([$staff_id, $amount, $payment_type, $salary_month, $payment_date, $payment_method, $notes ?: null, $edit_id]);
        header('Location: /gym/staff/salaries.php?msg=updated&month=' . urlencode($salary_month));
        exit;
    }
}

$msg = $_GET['msg'] ?? '';
if ($msg === 'paid') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Salary payment recorded successfully.</div>';
if ($msg === 'advance') echo '<div class="alert alert-warning py-2"><i class="fas fa-hand-holding-usd me-1"></i>Advance payment recorded successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Salary payment deleted.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Salary payment updated.</div>';

$staff = $pdo->query('SELECT id, name, role, salary FROM staff WHERE status = "active" ORDER BY name')->fetchAll();

$month = trim($_GET['month'] ?? $currentMonth);
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = $currentMonth;

// Stats for selected month
$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS total FROM staff_salaries WHERE salary_month = ?');
$stmt->execute([$month]);
$paidThisMonth = (float)$stmt->fetch()['total'];
$payroll = (float)$pdo->query('SELECT COALESCE(SUM(salary),0) FROM staff WHERE status = "active"')->fetchColumn();
$pending = max(0, $payroll - $paidThisMonth);

// Payment history with search & date filter
$hSearch = trim($_GET['hq'] ?? '');
$hDateFrom = $_GET['h_from'] ?? '';
$hDateTo = $_GET['h_to'] ?? '';

$hSql = "SELECT sp.*, s.name AS staff_name, s.role
         FROM staff_salaries sp
         JOIN staff s ON s.id = sp.staff_id";
$hParams = [];
$hWhere = [];

if ($hSearch !== '') {
    $hWhere[] = '(s.name LIKE ? OR s.role LIKE ? OR sp.notes LIKE ?)';
    $like = '%' . $hSearch . '%';
    $hParams[] = $like;
    $hParams[] = $like;
    $hParams[] = $like;
}
if ($hDateFrom !== '') {
    $hWhere[] = 'sp.payment_date >= ?';
    $hParams[] = $hDateFrom;
}
if ($hDateTo !== '') {
    $hWhere[] = 'sp.payment_date <= ?';
    $hParams[] = $hDateTo;
}
if (!empty($hWhere)) {
    $hSql .= ' WHERE ' . implode(' AND ', $hWhere);
}
$hSql .= ' ORDER BY sp.payment_date DESC, sp.id DESC LIMIT 200';
$hStmt = $pdo->prepare($hSql);
$hStmt->execute($hParams);
$history = $hStmt->fetchAll();

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

<div class="card mb-4" style="border-top:3px solid #6c5ce7;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h6 class="fw-bold mb-0"><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Salary Status &mdash; <?php echo date('F Y', strtotime($month . '-01')); ?></h6>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <form method="GET" action="" class="d-flex gap-2 align-items-center">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-calendar-alt text-muted"></i></span>
                        <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month); ?>" style="max-width: 170px;">
                        <button type="submit" class="btn btn-dark fw-semibold px-3"><i class="fas fa-filter me-1"></i>Filter</button>
                    </div>
                </form>
                <button type="button" class="btn btn-success fw-bold px-3" data-bs-toggle="modal" data-bs-target="#paySalaryModal" onclick="openPayModal()"><i class="fas fa-plus me-1"></i>Pay Salary</button>
            </div>
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
                                    <span class="badge text-bg-success"><i class="fas fa-check me-1"></i>Paid</span>
                                <?php elseif ($paid > 0): ?>
                                    <span class="badge text-bg-warning"><i class="fas fa-clock me-1"></i>Partial</span>
                                <?php else: ?>
                                    <span class="badge text-bg-danger"><i class="fas fa-times me-1"></i>Due</span>
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

<div class="card" style="border-top:3px solid #f7b731;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="fas fa-history text-warning me-2"></i>Payment History</h6>
        </div>
        <form method="GET" action="" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
            <div class="col-md-5">
                <label class="form-label small fw-bold mb-1">Search Staff / Notes</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="hq" class="form-control form-control-sm" placeholder="Search staff, role, notes..." value="<?php echo htmlspecialchars($hSearch); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">From Date</label>
                <input type="date" name="h_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hDateFrom); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">To Date</label>
                <input type="date" name="h_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($hDateTo); ?>">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-warning btn-sm flex-fill fw-bold px-3" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-filter me-1"></i>Filter</button>
                <?php if ($hSearch !== '' || $hDateFrom !== '' || $hDateTo !== ''): ?>
                    <a href="salaries.php?month=<?php echo urlencode($month); ?>" class="btn btn-outline-secondary btn-sm" title="Clear"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>#</th><th>Date</th><th>Staff</th><th>Salary Month</th><th>Method</th><th>Notes</th><th class="text-end">Amount</th><th class="text-end">Actions</th></tr></thead>
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
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit" onclick="editPayment(<?php echo $p['id']; ?>, <?php echo $p['staff_id']; ?>, '<?php echo htmlspecialchars($p['payment_type'] ?? 'salary', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['salary_month'], ENT_QUOTES); ?>', <?php echo $p['amount']; ?>, '<?php echo htmlspecialchars($p['payment_date'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['payment_method'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['notes'] ?? '', ENT_QUOTES); ?>')"><i class="fas fa-pen"></i></button>
                                    <a href="salary_slip.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Slip"><i class="fas fa-print"></i></a>
                                    <a href="salary_delete.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this salary payment?');"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pay Salary Modal -->
<div class="modal fade" id="paySalaryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#00b894,#00a381);color:#fff;">
                <h6 class="modal-title fw-bold"><i class="fas fa-hand-holding-usd me-2"></i><span id="payModalTitle">Pay Salary</span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="" id="paySalaryForm">
                    <input type="hidden" name="action" value="pay_salary" id="formAction">
                    <input type="hidden" name="payment_id" value="" id="editPaymentId">
                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="payment_type" value="salary" id="ptSalary" onchange="updatePayType()" checked>
                        <label class="btn btn-outline-success" for="ptSalary"><i class="fas fa-money-bill-wave me-1"></i>Salary</label>
                        <input type="radio" class="btn-check" name="payment_type" value="advance" id="ptAdvance" onchange="updatePayType()">
                        <label class="btn btn-outline-warning" for="ptAdvance"><i class="fas fa-hand-holding-usd me-1"></i>Advance</label>
                    </div>
                    <div class="mb-3 position-relative">
                        <label class="form-label fw-semibold">Search Staff Member *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-badge text-muted"></i></span>
                            <input type="text" id="payStaffSearch" class="form-control" placeholder="Type staff name or role..." autocomplete="off" spellcheck="false" required>
                            <button type="button" class="btn btn-outline-secondary" id="clearPayStaff" style="display:none;"><i class="fas fa-times"></i></button>
                        </div>
                        <input type="hidden" name="staff_id" id="staffSelect" value="" required>
                        <div id="payStaffResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1060; max-height:220px; overflow-y:auto; display:none; border-radius:6px;"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold" id="monthLabel">Salary Month *</label>
                            <input type="month" name="salary_month" class="form-control" value="<?php echo htmlspecialchars($currentMonth); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Amount (Rs.) *</label>
                            <input type="number" step="1" min="1" name="amount" id="amountInput" class="form-control" placeholder="0" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Method</label>
                            <select name="payment_method" class="form-select">
                                <?php foreach (['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer', 'easypaisa' => 'EasyPaisa', 'jazzcash' => 'JazzCash'] as $val => $label): ?>
                                    <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional note">
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success fw-bold"><i class="fas fa-check-circle me-1"></i><span id="payBtnText">Record Payment</span></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
var staffData = <?php echo json_encode($staff); ?>;
var staffSearchInput = document.getElementById('payStaffSearch');
var staffHiddenInput = document.getElementById('staffSelect');
var staffResultsBox = document.getElementById('payStaffResults');
var clearStaffBtn = document.getElementById('clearPayStaff');

function renderStaffList(query) {
    var q = (query || '').trim().toLowerCase();
    staffResultsBox.innerHTML = '';

    if (q.length < 1) {
        staffResultsBox.style.display = 'none';
        return;
    }

    var filtered = staffData.filter(function(s) {
        return s.name.toLowerCase().includes(q) || (s.role && s.role.toLowerCase().includes(q));
    });

    if (filtered.length === 0) {
        staffResultsBox.innerHTML = '<div class="list-group-item text-muted py-2 text-center small"><i class="fas fa-user-slash me-1"></i>No staff found</div>';
        staffResultsBox.style.display = 'block';
        return;
    }

    filtered.forEach(function(s) {
        var a = document.createElement('a');
        a.href = '#';
        a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 small';
        a.innerHTML = '<div><strong>' + escapeHtml(s.name) + '</strong> <small class="text-muted">(' + escapeHtml(s.role) + ')</small></div><span class="badge bg-light text-dark border">Salary: Rs.' + Number(s.salary).toLocaleString() + '</span>';
        
        a.addEventListener('click', function(e) {
            e.preventDefault();
            staffSearchInput.value = s.name + ' (' + s.role + ')';
            staffHiddenInput.value = s.id;
            document.getElementById('amountInput').value = parseFloat(s.salary) || '';
            staffResultsBox.style.display = 'none';
            clearStaffBtn.style.display = 'inline-block';
        });
        staffResultsBox.appendChild(a);
    });

    staffResultsBox.style.display = 'block';
}

function escapeHtml(text) {
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return (text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
}

staffSearchInput.addEventListener('focus', function() {
    if (this.value.trim().length >= 1) {
        renderStaffList(this.value);
    }
});

staffSearchInput.addEventListener('input', function() {
    staffHiddenInput.value = '';
    if (this.value.trim().length > 0) {
        clearStaffBtn.style.display = 'inline-block';
    } else {
        clearStaffBtn.style.display = 'none';
    }
    renderStaffList(this.value);
});

clearStaffBtn.addEventListener('click', function() {
    staffSearchInput.value = '';
    staffHiddenInput.value = '';
    clearStaffBtn.style.display = 'none';
    staffResultsBox.style.display = 'none';
    staffResultsBox.innerHTML = '';
    staffSearchInput.focus();
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('#payStaffSearch') && !e.target.closest('#payStaffResults')) {
        staffResultsBox.style.display = 'none';
    }
});

function updatePayType() {
    var isAdvance = document.getElementById('ptAdvance').checked;
    document.getElementById('payModalTitle').textContent = isAdvance ? 'Give Advance' : 'Pay Salary';
    document.getElementById('monthLabel').textContent = isAdvance ? 'Adjust Against Month *' : 'Salary Month *';
    document.getElementById('payBtnText').textContent = isAdvance ? 'Record Advance' : 'Record Payment';
}

function openPayModal() {
    document.getElementById('paySalaryForm').reset();
    staffSearchInput.value = '';
    staffHiddenInput.value = '';
    clearStaffBtn.style.display = 'none';
    document.getElementById('amountInput').value = '';
    document.getElementById('formAction').value = 'pay_salary';
    document.getElementById('editPaymentId').value = '';
    document.getElementById('payModalTitle').textContent = 'Pay Salary';
    document.getElementById('payBtnText').textContent = 'Record Payment';
    document.getElementById('monthLabel').textContent = 'Salary Month *';
}

function editPayment(id, staffId, type, month, amount, date, method, notes) {
    document.getElementById('formAction').value = 'edit_salary';
    document.getElementById('editPaymentId').value = id;
    document.getElementById('payModalTitle').textContent = 'Edit Payment';
    document.getElementById('payBtnText').textContent = 'Update Payment';
    staffHiddenInput.value = staffId;
    var found = staffData.find(function(s) { return s.id == staffId; });
    if (found) {
        staffSearchInput.value = found.name + ' (' + found.role + ')';
        clearStaffBtn.style.display = 'inline-block';
    }
    if (type === 'advance') {
        document.getElementById('ptAdvance').checked = true;
    } else {
        document.getElementById('ptSalary').checked = true;
    }
    updatePayType();
    document.querySelector('#paySalaryForm input[name="salary_month"]').value = month;
    document.getElementById('amountInput').value = amount;
    document.querySelector('#paySalaryForm input[name="payment_date"]').value = date;
    document.querySelector('#paySalaryForm select[name="payment_method"]').value = method;
    document.querySelector('#paySalaryForm input[name="notes"]').value = notes;
    var modal = new bootstrap.Modal(document.getElementById('paySalaryModal'));
    modal.show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
