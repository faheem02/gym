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

<div class="page-header mb-3">
    <div></div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="button" onclick="downloadSupplierListPDF();" class="btn btn-primary btn-sm fw-bold" title="Download PDF"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
        <button type="button" onclick="window.print();" class="btn btn-danger btn-sm fw-bold" title="Print supplier list"><i class="fas fa-print me-1"></i>Print</button>
        <a href="payments.php" class="btn btn-outline-success btn-sm fw-bold"><i class="fas fa-money-check-alt me-1"></i>Payments</a>
        <a href="add.php" class="btn btn-warning btn-sm fw-bold"><i class="fas fa-plus me-1"></i>Add Supplier</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="" class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name or phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <?php if ($search !== ''): ?>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
            <?php endif; ?>
            <button type="submit" class="btn btn-dark btn-sm fw-bold text-nowrap px-3"><i class="fas fa-search me-1"></i>Search</button>
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
#printSection {
    display: none;
    background: #ffffff;
    color: #111111;
    font-family: Arial, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* ── Print & PDF Styles ── */
#printSection .print-header {
    text-align: center;
    border-bottom: 2px solid #1a1a2e;
    padding-bottom: 12px;
    margin-bottom: 16px;
}
#printSection .print-logo { margin-bottom: 6px; }
#printSection .print-logo img {
    height: 55px;
    width: auto;
    display: inline-block;
    object-fit: contain;
    filter: brightness(0);
    -webkit-filter: brightness(0);
}
#printSection .print-gym-name {
    font-size: 20px;
    font-weight: 800;
    letter-spacing: 2px;
    color: #1a1a2e;
    text-transform: uppercase;
    margin-top: 2px;
}
#printSection .print-gym-contact { font-size: 11px; color: #333333; margin-top: 3px; }
#printSection .print-gym-address { font-size: 10.5px; color: #555555; margin-top: 2px; }
#printSection .print-gym-sub {
    font-size: 12.5px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #1a1a2e;
    font-weight: 700;
    margin-top: 8px;
    padding: 3px 0;
    border-top: 1px dashed #cccccc;
    border-bottom: 1px dashed #cccccc;
}
#printSection .print-gym-meta { font-size: 11px; color: #333333; margin-top: 5px; }

/* ── Summary boxes ── */
#printSection .print-summary {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
}
#printSection .print-summary-box {
    flex: 1;
    text-align: center;
    padding: 10px 8px;
    border: 1px solid #1a1a2e;
    border-radius: 4px;
    background: #fdfdfd;
}
#printSection .print-summary-box.highlight {
    background: #1a1a2e !important;
    color: #ffffff !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-summary-val { font-size: 16px; font-weight: 700; }
#printSection .print-summary-lbl {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #666666;
    margin-top: 3px;
}
#printSection .print-summary-box.highlight .print-summary-lbl { color: #dddddd !important; }

/* ── Table ── */
#printSection .print-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    margin-bottom: 16px;
}
#printSection .print-table thead tr {
    background: #1a1a2e !important;
    color: #ffffff !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-table thead th {
    padding: 8px 10px;
    text-align: left;
    font-weight: 700;
    font-size: 10.5px;
    letter-spacing: 0.5px;
    border: 1px solid #1a1a2e;
    color: #ffffff;
}
#printSection .print-table tbody tr td {
    padding: 7px 10px;
    border: 1px solid #e0e0e0;
    vertical-align: middle;
}
#printSection .print-table tbody tr.even td {
    background: #f9fafb !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-table tfoot tr td {
    padding: 8px 10px;
    background: #f3f4f6 !important;
    font-weight: 700;
    border: 1px solid #d1d5db;
    border-top: 2px solid #1a1a2e;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
#printSection .print-table .text-right { text-align: right; }
#printSection .print-table .bold { font-weight: 700; }

/* ── Footer ── */
#printSection .print-footer {
    display: flex;
    justify-content: space-between;
    font-size: 9.5px;
    color: #666666;
    margin-top: 16px;
    border-top: 1px solid #cccccc;
    padding-top: 8px;
}

/* ── Print Media ── */
@media print {
    .sidebar, .sidebar-overlay, .topbar, .hamburger,
    .page-header, .card, script { display: none !important; }

    body { background: #fff !important; margin: 0; padding: 0; font-family: Arial, sans-serif; color: #000; }
    .layout-wrapper { display: block !important; }
    .main-content { margin: 0 !important; width: 100% !important; min-height: unset; }
    .content { padding: 0 !important; }

    #printSection { display: block !important; padding: 18px 24px; }
    @page { margin: 12mm 10mm; size: A4 portrait; }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadSupplierListPDF() {
    var printSection = document.getElementById('printSection');
    if (!printSection) return;

    var btn = event && event.target ? event.target.closest('button') : null;
    var originalHTML = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating PDF...';
    }

    printSection.style.display = 'block';

    var opt = {
        margin:       [8, 8, 8, 8],
        filename:     'Canteen_Suppliers_List.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(printSection).save().then(function() {
        printSection.style.display = 'none';
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    }).catch(function(err) {
        console.error('PDF error:', err);
        printSection.style.display = 'none';
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
