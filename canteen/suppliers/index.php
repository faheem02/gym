<?php
$activePage = 'canteen_suppliers';
$pageTitle = 'Suppliers';
include __DIR__ . '/../../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Supplier added.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Supplier updated.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Supplier deleted.</div>';
if ($msg === 'payment') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Payment recorded.</div>';

$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM canteen_suppliers WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (name LIKE ? OR phone LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sql .= " ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();
?>

<div class="page-header">
    <div></div>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print();" class="btn btn-danger fw-bold" title="Print supplier list"><i class="fas fa-print me-1"></i>Print</button>
        <a href="payments.php" class="btn btn-outline-success fw-bold"><i class="fas fa-money-check-alt me-1"></i>Payments</a>
        <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>Add Supplier</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="" class="d-flex align-items-center gap-2">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by name or phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <?php if ($search !== ''): ?>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm fw-bold"><i class="fas fa-filter me-1"></i>Search</button>
        </form>
    </div>
</div>

<div class="card" style="border-top:3px solid #3b82f6;">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Balance (Rs.)</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-truck me-1"></i>No suppliers found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($suppliers as $s): ?>
                        <?php $bal = (float)$s['balance']; ?>
                        <tr>
                            <td><?php echo $s['id']; ?></td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($s['name']); ?></td>
                            <td><?php echo htmlspecialchars($s['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($s['email'] ?? '-'); ?></td>
                            <td>
                                <?php if ($bal > 0): ?>
                                    <span class="text-danger fw-bold">Rs.<?php echo number_format($bal, 0); ?></span>
                                    <small class="text-muted">(due)</small>
                                <?php elseif ($bal < 0): ?>
                                    <span class="text-success fw-bold">Rs.<?php echo number_format(abs($bal), 0); ?></span>
                                    <small class="text-muted">(advance)</small>
                                <?php else: ?>
                                    <span class="text-muted">Rs.0</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo $s['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>"><?php echo ucfirst($s['status']); ?></span></td>
                            <td class="text-end">
                                <a href="view.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                <a href="ledger.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-success" title="Ledger"><i class="fas fa-book"></i></a>
                                <a href="edit.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                                <a href="delete.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this supplier?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===================== PRINT-ONLY SECTION ===================== -->
<?php
$listTotal = count($suppliers);
$dueCount = 0;
$dueTotal = 0;
$advanceTotal = 0;
foreach ($suppliers as $s) {
    $b = (float)$s['balance'];
    if ($b > 0) { $dueCount++; $dueTotal += $b; }
    elseif ($b < 0) { $advanceTotal += abs($b); }
}
?>
<div id="printSection">

    <!-- Letterhead -->
    <?php
    $printReportTitle = 'Suppliers List';
    include __DIR__ . "/../../includes/print_header.php";
    ?>

    <!-- Summary boxes -->
    <div class="print-summary">
        <div class="print-summary-box">
            <div class="print-summary-val"><?php echo $listTotal; ?></div>
            <div class="print-summary-lbl">Total Suppliers</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val"><?php echo $dueCount; ?></div>
            <div class="print-summary-lbl">With Due</div>
        </div>
        <div class="print-summary-box highlight">
            <div class="print-summary-val">Rs.<?php echo number_format($dueTotal, 0); ?></div>
            <div class="print-summary-lbl">Outstanding Due</div>
        </div>
    </div>

    <!-- Suppliers table -->
    <table class="print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th class="text-right">Balance (Rs.)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($suppliers)): ?>
                <tr><td colspan="6" style="text-align:center;padding:20px;color:#666;">No suppliers found.</td></tr>
            <?php endif; ?>
            <?php foreach ($suppliers as $i => $s): $bal = (float)$s['balance']; ?>
            <tr class="<?php echo $i % 2 === 0 ? 'even' : ''; ?>">
                <td><?php echo $i + 1; ?></td>
                <td><?php echo htmlspecialchars($s['name']); ?></td>
                <td><?php echo htmlspecialchars($s['phone'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($s['email'] ?? '-'); ?></td>
                <td class="text-right bold"><?php echo number_format(abs($bal), 0) . ($bal > 0 ? ' Dr' : ($bal < 0 ? ' Cr' : '')); ?></td>
                <td><?php echo ucfirst($s['status']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="bold">Totals — <?php echo $listTotal; ?> supplier(s)</td>
                <td class="text-right bold">Due: Rs.<?php echo number_format($dueTotal, 0); ?><?php echo $advanceTotal > 0 ? '<br>Adv: Rs.' . number_format($advanceTotal, 0) : ''; ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <!-- Footer -->
    <?php include __DIR__ . "/../../includes/print_footer.php"; ?>

</div><!-- /printSection -->

<style>
/* ── Screen: hide print section ── */
#printSection { display: none; }

/* ── Print styles ── */
@media print {
    /* Hide all screen UI */
    .sidebar, .sidebar-overlay, .topbar, .hamburger,
    .page-header, .card, script { display: none !important; }

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

    /* ── Footer ── */
    .print-footer { display:flex; justify-content:space-between; font-size:9px; color:#666; margin-top:14px; border-top:1px solid #ccc; padding-top:6px; }

    /* Page setup */
    @page { margin: 12mm 10mm; size: A4 portrait; }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
