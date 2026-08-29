<?php
$activePage = 'day_passes';
$pageTitle = 'Day Passes';
include __DIR__ . '/../includes/header.php';

$msg = $_GET['msg'] ?? '';
$search = trim($_GET['search'] ?? '');
$date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$passType = trim($_GET['pass_type'] ?? '');
$status = trim($_GET['status'] ?? '');

if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Day pass updated successfully.</div>';
if ($msg === 'checkedout') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Visitor checked out.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Day pass deleted.</div>';

$sql = "SELECT dp.*, m.name AS member_name
        FROM day_passes dp
        LEFT JOIN members m ON m.id = dp.member_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (dp.visitor_name LIKE ? OR dp.phone LIKE ? OR m.name LIKE ? OR dp.notes LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($date !== '') {
    $sql .= " AND dp.pass_date = ?";
    $params[] = $date;
}
if ($passType !== '') {
    $sql .= " AND dp.pass_type = ?";
    $params[] = $passType;
}
if ($status === 'inside') {
    $sql .= " AND dp.check_out_time IS NULL";
} elseif ($status === 'checkedout') {
    $sql .= " AND dp.check_out_time IS NOT NULL";
}

$sql .= " ORDER BY dp.pass_date DESC, dp.check_in_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$passes = $stmt->fetchAll();

$todayRevenue = 0;
$currentlyInside = 0;
foreach ($passes as $p) {
    $todayRevenue += (float)$p['amount'];
    if ($p['check_out_time'] === null) $currentlyInside++;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $passId = (int)($_POST['pass_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE day_passes SET check_out_time = ? WHERE id = ? AND check_out_time IS NULL");
    $stmt->execute([date('H:i:s'), $passId]);
    header('Location: /gym/day_passes/index.php?msg=checkedout' . ($date ? '&date=' . urlencode($date) : '') . ($search ? '&search=' . urlencode($search) : ''));
    exit;
}
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary"><i class="fas fa-ticket-alt"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo count($passes); ?></h5>
                    <small class="text-muted">Filtered Passes</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($todayRevenue, 0); ?></h5>
                    <small class="text-muted">Total Revenue</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning"><i class="fas fa-door-open"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $currentlyInside; ?></h5>
                    <small class="text-muted">Currently Inside</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Multi-Filter Bar -->
<div class="card mb-4 shadow-sm" style="border-top:3px solid #f7b731;">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Search Visitor / Member</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Visitor name, phone, member..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Pass Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Pass Type</label>
                <select name="pass_type" class="form-select form-select-sm">
                    <option value="">All Pass Types</option>
                    <option value="gym" <?php echo $passType === 'gym' ? 'selected' : ''; ?>>Gym Access</option>
                    <option value="kids_play" <?php echo $passType === 'kids_play' ? 'selected' : ''; ?>>Kids Play Area</option>
                    <option value="both" <?php echo $passType === 'both' ? 'selected' : ''; ?>>Gym + Kids Play</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="inside" <?php echo $status === 'inside' ? 'selected' : ''; ?>>Currently Inside</option>
                    <option value="checkedout" <?php echo $status === 'checkedout' ? 'selected' : ''; ?>>Checked Out</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-warning btn-sm flex-fill fw-bold px-3" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="index.php?date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset to Today"><i class="fas fa-redo me-1"></i>Today</a>
                <a href="index.php?date=" class="btn btn-outline-secondary btn-sm" title="View All Dates"><i class="fas fa-calendar-alt"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h6 class="fw-bold mb-0"><i class="fas fa-list me-1 text-muted"></i>Passes List (<?php echo count($passes); ?>)</h6>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" onclick="downloadDayPassListPDF();" class="btn btn-outline-primary btn-sm fw-bold" title="Download PDF"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
        <button type="button" onclick="window.print();" class="btn btn-outline-dark btn-sm fw-bold" title="Print day passes list"><i class="fas fa-print me-1"></i>Print</button>
        <a href="add.php" class="btn btn-warning btn-sm fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-plus me-1"></i>Issue Day Pass</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Visitor</th>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Related Member</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Amount</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($passes)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-ticket-alt me-1"></i>No day passes for this date.</td></tr>
                <?php endif; ?>
                <?php foreach ($passes as $i => $p): ?>
                    <?php
                        $typeLabels = [
                            'gym' => ['Gym Access', 'primary'],
                            'kids_play' => ['Kids Play Area', 'success'],
                            'both' => ['Gym + Kids Play', 'warning'],
                        ];
                        $label = $typeLabels[$p['pass_type']] ?? ['Unknown', 'secondary'];
                        $duration = '';
                        if ($p['check_out_time']) {
                            $mins = (strtotime($p['check_out_time']) - strtotime($p['check_in_time'])) / 60;
                            $hrs = floor($mins / 60);
                            $mins = $mins % 60;
                            $duration = ($hrs > 0 ? $hrs . 'h ' : '') . $mins . 'm';
                        }
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($p['visitor_name']); ?></td>
                        <td><?php echo htmlspecialchars($p['phone'] ?? '-'); ?></td>
                        <td><span class="badge text-bg-<?php echo $label[1]; ?>"><?php echo $label[0]; ?></span></td>
                        <td><?php echo $p['member_name'] ? htmlspecialchars($p['member_name']) : '<span class="text-muted">Walk-in</span>'; ?></td>
                        <td><i class="fas fa-clock text-muted me-1"></i><?php echo date('h:i A', strtotime($p['check_in_time'])); ?></td>
                        <td>
                            <?php if ($p['check_out_time']): ?>
                                <span class="text-success"><i class="fas fa-check-circle me-1"></i><?php echo date('h:i A', strtotime($p['check_out_time'])); ?></span>
                                <?php if ($duration): ?>
                                    <br><small class="text-muted"><?php echo $duration; ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-active"><i class="fas fa-circle me-1" style="font-size:0.5rem;vertical-align:middle;"></i>Inside</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold">Rs.<?php echo number_format($p['amount'], 0); ?></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="slip.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Slip (Thermal)"><i class="fas fa-print"></i></a>
                                <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Pass"><i class="fas fa-pen"></i></a>
                                <?php if (!$p['check_out_time']): ?>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Check out this visitor?');">
                                        <input type="hidden" name="action" value="checkout">
                                        <input type="hidden" name="pass_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Check Out"><i class="fas fa-sign-out-alt"></i></button>
                                    </form>
                                <?php endif; ?>
                                <a href="delete.php?id=<?php echo $p['id']; ?>&date=<?php echo urlencode($date); ?>" class="btn btn-sm btn-outline-danger" title="Delete Pass" onclick="return confirm('Are you sure you want to delete this day pass?');"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================== PRINT-ONLY SECTION (A4) ===================== -->
<div id="printSection">

    <!-- Letterhead -->
    <?php
    $printReportTitle = 'Day Passes Report — ' . date('d M Y', strtotime($date));
    $printMeta = '<strong>Report Date:</strong> ' . date('d-m-Y', strtotime($date));
    include __DIR__ . "/../includes/print_header.php";
    ?>

    <!-- Summary boxes -->
    <div class="print-summary">
        <div class="print-summary-box">
            <div class="print-summary-val"><?php echo count($passes); ?></div>
            <div class="print-summary-lbl">Total Passes</div>
        </div>
        <div class="print-summary-box">
            <div class="print-summary-val"><?php echo $currentlyInside; ?></div>
            <div class="print-summary-lbl">Currently Inside</div>
        </div>
        <div class="print-summary-box highlight">
            <div class="print-summary-val">Rs.<?php echo number_format($todayRevenue, 0); ?></div>
            <div class="print-summary-lbl">Total Revenue</div>
        </div>
    </div>

    <!-- Day Passes table -->
    <table class="print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Visitor</th>
                <th>Phone</th>
                <th>Pass Type</th>
                <th>Related Member</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($passes)): ?>
                <tr><td colspan="8" style="text-align:center;padding:20px;color:#666;">No day passes for this date.</td></tr>
            <?php endif; ?>
            <?php foreach ($passes as $i => $p):
                $typeLabels = [
                    'gym' => 'Gym Access',
                    'kids_play' => 'Kids Play Area',
                    'both' => 'Gym + Kids Play',
                ];
                $typeText = $typeLabels[$p['pass_type']] ?? ucfirst($p['pass_type']);
            ?>
            <tr class="<?php echo $i % 2 === 0 ? 'even' : ''; ?>">
                <td><?php echo $i + 1; ?></td>
                <td class="bold"><?php echo htmlspecialchars($p['visitor_name']); ?></td>
                <td><?php echo htmlspecialchars($p['phone'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($typeText); ?></td>
                <td><?php echo $p['member_name'] ? htmlspecialchars($p['member_name']) : 'Walk-in'; ?></td>
                <td><?php echo date('h:i A', strtotime($p['check_in_time'])); ?></td>
                <td>
                    <?php if ($p['check_out_time']): ?>
                        <?php echo date('h:i A', strtotime($p['check_out_time'])); ?>
                    <?php else: ?>
                        Inside
                    <?php endif; ?>
                </td>
                <td class="text-right bold">Rs.<?php echo number_format($p['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="bold">Total: <?php echo count($passes); ?> pass(es) | Inside: <?php echo $currentlyInside; ?></td>
                <td class="bold text-right">Revenue:</td>
                <td class="bold text-right">Rs.<?php echo number_format($todayRevenue, 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Footer -->
    <?php include __DIR__ . "/../includes/print_footer.php"; ?>

</div><!-- /printSection -->

<style>
/* ── Screen: hide print section ── */
#printSection {
    display: none;
    background: #ffffff;
    color: #111111;
    font-family: Arial, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* ── Print & PDF Styles (Available to both Print and html2pdf) ── */
#printSection .print-header {
    text-align: center;
    border-bottom: 2px solid #1a1a2e;
    padding-bottom: 12px;
    margin-bottom: 16px;
}
#printSection .print-logo {
    margin-bottom: 6px;
}
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
#printSection .print-gym-contact {
    font-size: 11px;
    color: #333333;
    margin-top: 3px;
}
#printSection .print-gym-address {
    font-size: 10.5px;
    color: #555555;
    margin-top: 2px;
}
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
#printSection .print-gym-meta {
    font-size: 11px;
    color: #333333;
    margin-top: 5px;
}

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
#printSection .print-summary-val {
    font-size: 16px;
    font-weight: 700;
}
#printSection .print-summary-lbl {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #666666;
    margin-top: 3px;
}
#printSection .print-summary-box.highlight .print-summary-lbl {
    color: #dddddd !important;
}

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
#printSection .print-table .text-right {
    text-align: right;
}
#printSection .print-table .bold {
    font-weight: 700;
}

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
    /* Hide all screen UI */
    .sidebar, .sidebar-overlay, .topbar, .hamburger,
    .search-bar, .no-print, .alert,
    .row.g-3.mb-4, .card, script { display: none !important; }

    body { background: #fff !important; margin: 0; padding: 0; font-family: Arial, sans-serif; color: #000; }
    .layout-wrapper { display: block !important; }
    .main-content { margin: 0 !important; width: 100% !important; min-height: unset; }
    .content { padding: 0 !important; }

    /* Show print section */
    #printSection { display: block !important; padding: 18px 24px; }

    /* Page setup */
    @page { margin: 12mm 10mm; size: A4 portrait; }
}
</style>

<!-- Include html2pdf.js for A4 PDF Download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadDayPassListPDF() {
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
        filename:     'Day_Passes_Report_<?php echo htmlspecialchars($date); ?>.pdf',
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
        console.error('PDF generation error:', err);
        printSection.style.display = 'none';
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
