<?php
$activePage = 'staff';
$pageTitle = 'Staff';
include __DIR__ . '/../includes/header.php';

$search = trim($_GET['q'] ?? '');
$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Staff member added successfully.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Staff member updated successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Staff member deleted.</div>';

$roleColors = [
    'receptionist' => 'text-bg-info',
    'trainer' => 'text-bg-primary',
    'helper' => 'text-bg-secondary',
    'cleaner' => 'text-bg-warning',
    'manager' => 'text-bg-success',
    'accountant' => 'text-bg-dark',
    'other' => 'text-bg-secondary'
];

$totalStaff = $pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn();
$activeStaff = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();
$totalSalary = $pdo->query("SELECT COALESCE(SUM(salary),0) FROM staff WHERE status = 'active'")->fetchColumn();

$currentMonth = date('Y-m');
// Last payment per staff
$lastPay = [];
foreach ($pdo->query('SELECT sp.staff_id, sp.amount, sp.payment_date, sp.salary_month FROM staff_salaries sp JOIN (SELECT staff_id, MAX(id) AS mid FROM staff_salaries GROUP BY staff_id) x ON x.mid = sp.id') as $r) {
    $lastPay[$r['staff_id']] = $r;
}
// Paid this month per staff
$paidThisMonth = [];
$stmt = $pdo->prepare('SELECT staff_id, COALESCE(SUM(amount),0) AS paid FROM staff_salaries WHERE salary_month = ? GROUP BY staff_id');
$stmt->execute([$currentMonth]);
foreach ($stmt->fetchAll() as $r) $paidThisMonth[$r['staff_id']] = (float)$r['paid'];
// Full payment history per staff (for view modal)
$payHistory = [];
foreach ($pdo->query('SELECT * FROM staff_salaries ORDER BY payment_date DESC, id DESC LIMIT 200') as $r) {
    $payHistory[$r['staff_id']][] = $r;
}
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card" style="border-top:3px solid #6c5ce7;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(108,92,231,0.1);color:#6c5ce7;"><i class="fas fa-users"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $totalStaff; ?></h5>
                    <small class="text-muted">Total Staff</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card" style="border-top:3px solid #00b894;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(0,184,148,0.1);color:#00b894;"><i class="fas fa-user-check"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $activeStaff; ?></h5>
                    <small class="text-muted">Active Staff</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card" style="border-top:3px solid #f7b731;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(247,183,49,0.1);color:#f7b731;"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalSalary); ?></h5>
                    <small class="text-muted">Total Salaries (Active)</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="search-bar">
    <div class="row g-2 align-items-center">
        <div class="col-md-7 col-lg-8">
            <form method="GET" action="" class="d-flex">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control me-2" placeholder="Search by name, phone, role...">
                <button class="btn btn-dark" type="submit"><i class="fas fa-search me-1"></i>Search</button>
            </form>
        </div>
        <div class="col-md-5 col-lg-4 text-md-end">
            <button type="button" onclick="window.print();" class="btn btn-danger fw-bold me-2" title="Print staff list"><i class="fas fa-print me-1"></i>Print</button>
            <a href="salaries.php" class="btn btn-success fw-bold me-2"><i class="fas fa-money-bill-wave me-1"></i>Salaries</a>
            <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>Add Staff</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Salary</th>
                    <th>Salary Status</th>
                    <th>Join Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = 'SELECT * FROM staff';
                $params = [];
                if ($search !== '') {
                    $sql .= ' WHERE name LIKE ? OR phone LIKE ? OR role LIKE ? OR email LIKE ?';
                    $like = '%' . $search . '%';
                    $params = [$like, $like, $like, $like];
                }
                $sql .= ' ORDER BY id DESC';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $staff = $stmt->fetchAll();
                ?>
                <?php if (empty($staff)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-user-slash me-1"></i>No staff found.</td></tr>
                <?php endif; ?>
                <?php foreach ($staff as $s): ?>
                    <?php
                    $paidM = $paidThisMonth[$s['id']] ?? 0;
                    $last = $lastPay[$s['id']] ?? null;
                    ?>
                    <tr>
                        <td><?php echo $s['id']; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($s['name']); ?></td>
                        <td><?php echo htmlspecialchars($s['phone']); ?></td>
                        <td><span class="badge <?php echo $roleColors[$s['role']] ?? 'text-bg-secondary'; ?>"><i class="fas fa-briefcase me-1"></i><?php echo ucfirst($s['role']); ?></span></td>
                        <td>Rs.<?php echo number_format($s['salary'], 0); ?></td>
                        <td>
                            <?php if ($paidM >= (float)$s['salary'] && (float)$s['salary'] > 0): ?>
                                <span class="badge text-bg-success">Paid</span>
                            <?php elseif ($paidM > 0): ?>
                                <span class="badge text-bg-warning">Partial</span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">Due</span>
                            <?php endif; ?>
                            <?php if ($last): ?>
                                <small class="text-muted d-block">Last: <?php echo date('d M Y', strtotime($last['payment_date'])); ?></small>
                            <?php else: ?>
                                <small class="text-muted d-block">Never paid</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($s['join_date'])); ?></td>
                        <td>
                            <span class="badge <?php echo $s['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo ucfirst($s['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group-actions d-inline-flex gap-1">
                                <a href="#" class="btn btn-sm btn-outline-primary" title="View" data-bs-toggle="modal" data-bs-target="#viewStaff<?php echo $s['id']; ?>"><i class="fas fa-eye"></i></a>
                                <a href="ledger.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-dark" title="Ledger"><i class="fas fa-book"></i></a>
                                <a href="edit.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this staff member?');"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($staff as $s): ?>
    <?php $paidM = $paidThisMonth[$s['id']] ?? 0; ?>
    <div class="modal fade" id="viewStaff<?php echo $s['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
                    <h5 class="modal-title"><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($s['name']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="fw-bold text-muted mb-3"><i class="fas fa-user me-1"></i> Personal Information</h6>
                                <p class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($s['phone']); ?></p>
                                <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($s['email'] ?? '-'); ?></p>
                                <p class="mb-2"><strong>Address:</strong> <?php echo htmlspecialchars($s['address'] ?? '-'); ?></p>
                                <p class="mb-0"><strong>Status:</strong> <span class="badge <?php echo $s['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>"><?php echo ucfirst($s['status']); ?></span></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="fw-bold text-muted mb-3"><i class="fas fa-briefcase me-1"></i> Job Details</h6>
                                <p class="mb-2"><strong>Role:</strong> <?php echo ucfirst($s['role']); ?></p>
                                <p class="mb-2"><strong>Monthly Salary:</strong> Rs.<?php echo number_format($s['salary'], 0); ?></p>
                                <p class="mb-2"><strong>Join Date:</strong> <?php echo date('d M Y', strtotime($s['join_date'])); ?></p>
                                <p class="mb-0"><strong>This Month Paid:</strong> Rs.<?php echo number_format($paidM, 0); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="fw-bold text-muted mb-3"><i class="fas fa-user-friends me-1"></i> Emergency Contact</h6>
                                <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($s['emergency_contact'] ?? '-'); ?></p>
                                <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($s['emergency_phone'] ?? '-'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="fw-bold text-muted mb-3"><i class="fas fa-sticky-note me-1"></i> Description / Notes</h6>
                                <p class="mb-0"><?php echo $s['notes'] ? nl2br(htmlspecialchars($s['notes'])) : '<span class="text-muted">No notes added.</span>'; ?></p>
                            </div>
                        </div>
                    </div>
                    <h6 class="fw-bold text-muted mb-2"><i class="fas fa-money-bill-wave me-1"></i> Salary Payment History</h6>
                    <div class="table-responsive border rounded">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead><tr><th>Date</th><th>Salary Month</th><th>Method</th><th>Notes</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                <?php if (empty($payHistory[$s['id']])): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-3">No salary payments yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach (($payHistory[$s['id']] ?? []) as $p): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                                        <td><span class="badge text-bg-dark"><?php echo date('M Y', strtotime($p['salary_month'] . '-01')); ?></span></td>
                                        <td><small><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></small></td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($p['notes'] ?? '-'); ?></small></td>
                                        <td class="text-end fw-bold text-success">Rs.<?php echo number_format($p['amount'], 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="ledger.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-dark fw-bold"><i class="fas fa-book me-1"></i>Ledger</a>
                    <a href="salaries.php?type=advance&staff_id=<?php echo $s['id']; ?>" class="btn btn-warning fw-bold"><i class="fas fa-hand-holding-usd me-1"></i>Give Advance</a>
                    <a href="salaries.php" class="btn btn-success fw-bold"><i class="fas fa-money-bill-wave me-1"></i>Pay Salary</a>
                    <a href="edit.php?id=<?php echo $s['id']; ?>" class="btn btn-warning fw-bold"><i class="fas fa-pen me-1"></i>Edit</a>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- ===================== PRINT-ONLY SECTION ===================== -->
<?php
$listTotal = count($staff);
$listActive = 0;
$listSalary = 0;
foreach ($staff as $s) {
    if ($s['status'] === 'active') {
        $listActive++;
        $listSalary += (float)$s['salary'];
    }
}
?>
<div id="printSection">

    <!-- Letterhead -->
    <?php
    $printReportTitle = 'Staff & Salaries';
    include __DIR__ . "/../includes/print_header.php";
    ?>

    <!-- Summary boxes -->
    <div class="print-summary">
        <div class="print-summary-box">
            <div class="print-summary-val"><?php echo $listTotal; ?></div>
            <div class="print-summary-lbl">Total Staff</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val"><?php echo $listActive; ?></div>
            <div class="print-summary-lbl">Active</div>
        </div>
        <div class="print-summary-box highlight">
            <div class="print-summary-val">Rs.<?php echo number_format($listSalary, 0); ?></div>
            <div class="print-summary-lbl">Monthly Salary (Active)</div>
        </div>
    </div>

    <!-- Staff table -->
    <table class="print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Role</th>
                <th class="text-right">Salary (Rs.)</th>
                <th>Salary Status</th>
                <th>Join Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($staff)): ?>
                <tr><td colspan="8" style="text-align:center;padding:20px;color:#666;">No staff found.</td></tr>
            <?php endif; ?>
            <?php foreach ($staff as $i => $s):
                $paidM = $paidThisMonth[$s['id']] ?? 0;
                if ((float)$s['salary'] > 0 && $paidM >= (float)$s['salary']) { $payStatus = 'Paid'; }
                elseif ($paidM > 0) { $payStatus = 'Partial'; }
                else { $payStatus = 'Due'; }
                $last = $lastPay[$s['id']] ?? null;
            ?>
            <tr class="<?php echo $i % 2 === 0 ? 'even' : ''; ?>">
                <td><?php echo $i + 1; ?></td>
                <td><?php echo htmlspecialchars($s['name']); ?></td>
                <td><?php echo htmlspecialchars($s['phone']); ?></td>
                <td><?php echo ucfirst($s['role']); ?></td>
                <td class="text-right"><?php echo number_format($s['salary'], 0); ?></td>
                <td><?php echo $payStatus; ?><?php echo $last ? ' <span class="last-pay">(Last: ' . date('d M Y', strtotime($last['payment_date'])) . ')</span>' : ' <span class="last-pay">(Never paid)</span>'; ?></td>
                <td><?php echo date('d M Y', strtotime($s['join_date'])); ?></td>
                <td><?php echo ucfirst($s['status']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="bold">Totals — <?php echo $listTotal; ?> staff member(s)</td>
                <td class="text-right bold"><?php echo number_format(array_sum(array_column($staff, 'salary')), 0); ?></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

    <!-- Footer -->
    <?php include __DIR__ . "/../includes/print_footer.php"; ?>

</div><!-- /printSection -->

<style>
/* ── Screen: hide print section ── */
#printSection { display: none; }

/* ── Print styles ── */
@media print {
    /* Hide all screen UI */
    .sidebar, .sidebar-overlay, .topbar, .hamburger,
    .search-bar, .no-print, .alert,
    .row.g-3.mb-4, .card, .modal, script { display: none !important; }

    body        { background:#fff !important; margin:0; padding:0; font-family: Arial, sans-serif; color:#000; }
    .layout-wrapper { display:block !important; }
    .main-content   { margin:0 !important; width:100% !important; min-height:unset; }
    .content        { padding:0 !important; }

    /* Show print section */
    #printSection { display:block !important; padding: 18px 24px; }

    /* ── Letterhead ── */
    .print-header        { text-align:center; border-bottom:3px solid #1a1a2e; padding-bottom:10px; margin-bottom:14px; }
    .print-logo          { font-size:28px; color:#f7b731; margin-bottom:2px; }
    .print-gym-name      { font-size:20px; font-weight:900; letter-spacing:3px; color:#1a1a2e; }
    .print-gym-sub       { font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#555; margin-top:2px; }
    .print-gym-meta      { font-size:10px; color:#444; margin-top:6px; }

    /* ── Summary boxes ── */
    .print-summary       { display:flex; gap:0; border:1px solid #1a1a2e; margin-bottom:14px; }
    .print-summary-box   { flex:1; text-align:center; padding:8px 4px; border-right:1px solid #1a1a2e; }
    .print-summary-box:last-child { border-right:none; }
    .print-summary-box.highlight  { background:#1a1a2e; color:#fff; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .print-summary-val   { font-size:14px; font-weight:700; }
    .print-summary-lbl   { font-size:9px; text-transform:uppercase; letter-spacing:1px; color:#666; margin-top:2px; }
    .print-summary-box.highlight .print-summary-lbl { color:#ccc; }

    /* ── Table ── */
    .print-table         { width:100%; border-collapse:collapse; font-size:11px; }
    .print-table thead tr{ background:#1a1a2e; color:#fff; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .print-table thead th{ padding:7px 8px; text-align:left; font-weight:700; font-size:10px; letter-spacing:0.5px; }
    .print-table tbody tr td { padding:6px 8px; border-bottom:1px solid #e0e0e0; vertical-align:middle; }
    .print-table tbody tr.even td { background:#f9f9f9; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .print-table tfoot tr td { padding:7px 8px; background:#f0f0f0; font-weight:700; border-top:2px solid #1a1a2e; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .print-table .text-right { text-align:right; }
    .print-table .bold       { font-weight:700; }
    .print-table .last-pay   { font-size:8.5px; color:#777; }

    /* ── Footer ── */
    .print-footer { display:flex; justify-content:space-between; font-size:9px; color:#666; margin-top:14px; border-top:1px solid #ccc; padding-top:6px; }

    /* Page setup */
    @page { margin: 12mm 10mm; size: A4 portrait; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
