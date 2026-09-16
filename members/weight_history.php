<?php
$activePage = 'members';
$pageTitle = 'Weight History';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<div class="alert alert-warning">Invalid member. <a href="index.php">Back to members</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT m.*, t.name AS trainer_name FROM members m LEFT JOIN trainers t ON t.id = m.trainer_id WHERE m.id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    echo '<div class="alert alert-warning">Member not found. <a href="index.php">Back to members</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Build the full timeline: the member's STARTING weight (members.weight, set at add/edit) comes first,
// followed by every measurement recorded at payment time.
$records = [];
$baseDate = $member['join_date'] ?? '';
if ($baseDate === '' || $baseDate === null || $baseDate === '0000-00-00') $baseDate = date('Y-m-d');
if ($member['weight'] !== null && $member['weight'] !== '') {
    $records[] = [
        'id' => 0,
        'payment_date' => $baseDate,
        'payment_for' => 'Starting weight (member record)',
        'amount' => null,
        'weight' => $member['weight'],
        'age' => null,
        'payment_method' => null,
    ];
}
$stmt = $pdo->prepare("SELECT id, payment_date, payment_for, amount, weight, age, payment_method FROM member_payments WHERE member_id = ? AND (weight IS NOT NULL OR age IS NOT NULL) ORDER BY payment_date ASC, id ASC");
$stmt->execute([$id]);
foreach ($stmt->fetchAll() as $r) $records[] = $r;

// Order chronologically (stable sort keeps the member record first on equal dates)
usort($records, function ($a, $b) {
    return strtotime($a['payment_date']) <=> strtotime($b['payment_date']);
});

// Compute summary figures across the whole timeline
$startWeight = null;
$startAge = null;
$currentWeight = null;
$currentAge = null;
foreach ($records as $r) {
    if ($startWeight === null && $r['weight'] !== null && $r['weight'] !== '') $startWeight = (float)$r['weight'];
    if ($startAge === null && $r['age'] !== null && $r['age'] !== '') $startAge = (int)$r['age'];
    if ($r['weight'] !== null && $r['weight'] !== '') $currentWeight = (float)$r['weight'];
    if ($r['age'] !== null && $r['age'] !== '') $currentAge = (int)$r['age'];
}
// Fall back to the member's current profile values
if ($currentWeight === null && $member['weight'] !== null && $member['weight'] !== '') $currentWeight = (float)$member['weight'];
if ($currentAge === null && $member['age'] !== null && $member['age'] !== '') $currentAge = (int)$member['age'];

$totalDiff = null;
if ($startWeight !== null && $currentWeight !== null) $totalDiff = $currentWeight - $startWeight;
$ageDiff = null;
if ($startAge !== null && $currentAge !== null) $ageDiff = $currentAge - $startAge;

// Per-record change vs previous record
$prevW = null;
$prevA = null;
foreach ($records as $i => &$r) {
    $r['w_prev'] = $prevW;
    $r['a_prev'] = $prevA;
    $r['w_change'] = null;
    $r['a_change'] = null;
    if ($r['weight'] !== null && $prevW !== null) $r['w_change'] = (float)$r['weight'] - $prevW;
    if ($r['age'] !== null && $prevA !== null) $r['a_change'] = (int)$r['age'] - $prevA;
    if ($r['weight'] !== null) $prevW = (float)$r['weight'];
    if ($r['age'] !== null) $prevA = (int)$r['age'];
}
unset($r);
?>

<div class="mb-3 no-print">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Members</a>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-1"><i class="fas fa-weight-hanging text-warning me-2"></i><?php echo htmlspecialchars($member['name']); ?></h5>
        <small class="text-muted"><i class="fas fa-hashtag me-1"></i>Member #<?php echo $member['id']; ?> &mdash; Weight History: starting weight plus every change recorded at payment time</small>
    </div>
    <div class="d-flex gap-2 no-print">
        <button type="button" onclick="downloadWeightPDF();" class="btn btn-primary fw-bold btn-sm"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
        <button onclick="window.print();" class="btn btn-danger fw-bold btn-sm"><i class="fas fa-print me-1"></i>Print</button>
    </div>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary"><i class="fas fa-play"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $startWeight !== null ? number_format($startWeight, 1) . ' kg' : '—'; ?></h5>
                    <small class="text-muted">Start Weight</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info"><i class="fas fa-weight-hanging"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $currentWeight !== null ? number_format($currentWeight, 1) . ' kg' : '—'; ?></h5>
                    <small class="text-muted">Current Weight</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon <?php echo $totalDiff === null ? 'bg-secondary' : ($totalDiff > 0 ? 'bg-warning' : 'bg-success'); ?>"><i class="fas fa-chart-line"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">
                        <?php if ($totalDiff === null): ?>—<?php else: ?><span class="<?php echo $totalDiff > 0 ? 'text-warning' : ($totalDiff < 0 ? 'text-success' : ''); ?>"><?php echo $totalDiff > 0 ? '+' : ''; ?><?php echo number_format($totalDiff, 1); ?> kg</span><?php endif; ?>
                    </h5>
                    <small class="text-muted"><?php echo $totalDiff === null ? 'Total Change' : ($totalDiff > 0 ? 'Total Gained' : ($totalDiff < 0 ? 'Total Lost' : 'No Change')); ?></small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-dark"><i class="fas fa-sort-numeric-up"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo $startAge !== null && $currentAge !== null ? $startAge . ' → ' . $currentAge . ' yrs' : ($currentAge ? $currentAge . ' yrs' : '—'); ?></h5>
                    <small class="text-muted">Age (<?php echo $ageDiff !== null && $ageDiff != 0 ? ($ageDiff > 0 ? '+' . $ageDiff : $ageDiff) : 'same'; ?>)</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Detailed record table -->
    <div class="col-12">
        <div class="card" style="border-top:3px solid #8b5cf6;">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-list-ol me-2 text-primary"></i>Every Recorded Entry</h6>
                <?php if (empty($records)): ?>
                    <div class="text-center text-muted py-4"><i class="fas fa-inbox me-1"></i>No weight/age entries recorded yet.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Payment For</th>
                                <th class="text-end">Prev. Wt.</th>
                                <th class="text-end">New Wt.</th>
                                <th class="text-end">Wt. Change</th>
                                <th class="text-end">Age</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $r): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($r['payment_date'])); ?></td>
                                <td class="small"><?php echo htmlspecialchars($r['payment_for'] ?? '-'); ?></td>
                                <td class="text-end"><?php echo $r['w_prev'] !== null ? number_format($r['w_prev'], 1) . ' kg' : '—'; ?></td>
                                <td class="text-end fw-bold"><?php echo $r['weight'] !== null ? number_format((float)$r['weight'], 1) . ' kg' : '-'; ?></td>
                                <td class="text-end">
                                    <?php if ($r['w_change'] === null || $r['w_change'] == 0): ?>
                                        <span class="badge bg-light text-dark">&mdash;</span>
                                    <?php elseif ($r['w_change'] > 0): ?>
                                        <span class="badge text-bg-warning">+<?php echo number_format($r['w_change'], 1); ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success"><?php echo number_format($r['w_change'], 1); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($r['age'] !== null): ?>
                                        <?php echo $r['age'] . ' yrs'; ?>
                                        <?php if ($r['a_change'] !== null && $r['a_change'] != 0): ?>
                                            <span class="badge bg-light text-dark ms-1">+<?php echo $r['a_change']; ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===================== PRINT-ONLY SECTION ===================== -->
<div id="printSection">
    <?php
    $printReportTitle = 'Weight History';
    include __DIR__ . "/../includes/print_header.php";
    ?>
    <div class="print-member-line">
        <div class="pm-name"><?php echo htmlspecialchars($member['name']); ?> <span class="pm-id">(#<?php echo $member['id']; ?>)</span></div>
        <div class="pm-meta">
            Age: <?php echo $currentAge !== null ? $currentAge . ' yrs' : '-'; ?>
            &nbsp;|&nbsp; Start: <?php echo $startWeight !== null ? number_format($startWeight, 1) . ' kg' : '-'; ?>
            &nbsp;|&nbsp; Current: <?php echo $currentWeight !== null ? number_format($currentWeight, 1) . ' kg' : '-'; ?>
            &nbsp;|&nbsp;
            <?php if ($totalDiff === null): ?>No change data<?php else: ?>
            Total <span style="color:<?php echo $totalDiff > 0 ? '#b45309' : '#047857'; ?>;font-weight:700;">
                <?php echo $totalDiff > 0 ? 'Gained +' : 'Lost '; ?><?php echo number_format(abs($totalDiff), 1); ?> kg
            </span>
            <?php endif; ?>
        </div>
    </div>

    <table class="print-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Payment For</th>
                <th class="text-right">Prev. Wt. (kg)</th>
                <th class="text-right">New Wt. (kg)</th>
                <th class="text-right">Change (kg)</th>
                <th class="text-right">Age</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="6" style="text-align:center;padding:20px;color:#666;">No weight/age records yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($records as $i => $r): ?>
            <tr class="<?php echo $i % 2 === 0 ? 'even' : ''; ?>">
                <td><?php echo date('d M Y', strtotime($r['payment_date'])); ?></td>
                <td><?php echo htmlspecialchars($r['payment_for'] ?? '-'); ?></td>
                <td class="text-right"><?php echo $r['w_prev'] !== null ? number_format($r['w_prev'], 1) : '-'; ?></td>
                <td class="text-right"><?php echo $r['weight'] !== null ? number_format((float)$r['weight'], 1) : '-'; ?></td>
                <td class="text-right"><?php echo $r['w_change'] === null || $r['w_change'] == 0 ? '-' : ($r['w_change'] > 0 ? '+' . number_format($r['w_change'], 1) : number_format($r['w_change'], 1)); ?></td>
                <td class="text-right"><?php echo $r['age'] !== null ? $r['age'] . ' yrs' : '-'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="bold">Total &mdash; <?php echo count($records); ?> entr(y/ies)</td>
                <td class="text-right bold"><?php echo $startWeight !== null ? number_format($startWeight, 1) : '-'; ?></td>
                <td class="text-right bold"><?php echo $currentWeight !== null ? number_format($currentWeight, 1) : '-'; ?></td>
                <td class="text-right bold"><?php echo $totalDiff !== null ? ($totalDiff > 0 ? '+' . number_format($totalDiff, 1) : number_format($totalDiff, 1)) : '-'; ?></td>
                <td class="text-right bold"><?php echo $currentAge !== null ? $currentAge . ' yrs' : '-'; ?></td>
            </tr>
        </tfoot>
    </table>

    <?php include __DIR__ . "/../includes/print_footer.php"; ?>
</div><!-- /printSection -->

<style>
#printSection { display: none; background: #ffffff; color: #111111; font-family: Arial, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
#printSection .print-header { text-align: center; border-bottom: 2px solid #1a1a2e; padding-bottom: 12px; margin-bottom: 16px; }
#printSection .print-logo { margin-bottom: 6px; }
#printSection .print-logo img { height: 55px; width: auto; display: inline-block; object-fit: contain; filter: brightness(0); -webkit-filter: brightness(0); }
#printSection .print-gym-name { font-size: 20px; font-weight: 800; letter-spacing: 2px; color: #1a1a2e; text-transform: uppercase; margin-top: 2px; }
#printSection .print-gym-contact { font-size: 11px; color: #333333; margin-top: 3px; }
#printSection .print-gym-address { font-size: 10.5px; color: #555555; margin-top: 2px; }
#printSection .print-gym-sub { font-size: 12.5px; letter-spacing: 1.5px; text-transform: uppercase; color: #1a1a2e; font-weight: 700; margin-top: 8px; padding: 3px 0; border-top: 1px dashed #cccccc; border-bottom: 1px dashed #cccccc; }
#printSection .print-gym-meta { font-size: 11px; color: #333333; margin-top: 5px; }
#printSection .print-member-line { font-size: 10.5px; color: #333333; border: 1px dashed #999999; padding: 8px 10px; margin-bottom: 14px; background: #fafafa; }
#printSection .print-member-line .pm-name { font-size: 15px; font-weight: 800; color: #1a1a2e; }
#printSection .print-member-line .pm-id { font-size: 10px; font-weight: 700; color: #555555; }
#printSection .print-member-line .pm-meta { font-size: 10.5px; color: #333333; margin-top: 3px; }
#printSection .print-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 16px; }
#printSection .print-table thead tr { background: #1a1a2e !important; color: #ffffff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
#printSection .print-table thead th { padding: 8px 10px; text-align: left; font-weight: 700; font-size: 10.5px; letter-spacing: 0.5px; border: 1px solid #1a1a2e; color: #ffffff; }
#printSection .print-table tbody tr td { padding: 7px 10px; border: 1px solid #e0e0e0; vertical-align: middle; }
#printSection .print-table tbody tr.even td { background: #f9fafb !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
#printSection .print-table tfoot tr td { padding: 8px 10px; background: #f3f4f6 !important; font-weight: 700; border: 1px solid #d1d5db; border-top: 2px solid #1a1a2e; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
#printSection .print-table .text-right { text-align: right; }
#printSection .print-table .bold { font-weight: 700; }
#printSection .print-footer { display: flex; justify-content: space-between; font-size: 9.5px; color: #666666; margin-top: 16px; border-top: 1px solid #cccccc; padding-top: 8px; }

@media print {
    .sidebar, .sidebar-overlay, .topbar, .hamburger,
    .search-bar, .no-print, .alert,
    .card, script { display: none !important; }
    body { background: #fff !important; margin: 0; padding: 0; font-family: Arial, sans-serif; color: #000; }
    .layout-wrapper { display: block !important; }
    .main-content { margin: 0 !important; width: 100% !important; min-height: unset; }
    .content { padding: 0 !important; }
    #printSection { display: block !important; padding: 18px 24px; }
    @page { margin: 12mm 10mm; size: A4 portrait; }
}
</style>

<script src="/gym/assets/vendor/html2pdf/html2pdf.bundle.min.js"></script>
<script>
function downloadWeightPDF() {
    var printSection = document.getElementById('printSection');
    if (!printSection) return;
    var btn = event && event.target ? event.target.closest('button') : null;
    var originalHTML = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating PDF...'; }
    printSection.style.display = 'block';
    var opt = {
        margin: [8, 8, 8, 8],
        filename: 'Weight_History_<?php echo preg_replace('/[^A-Za-z0-9_-]/', '_', $member['name']); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(printSection).save().then(function() {
        printSection.style.display = 'none';
        if (btn) { btn.disabled = false; btn.innerHTML = originalHTML; }
    }).catch(function(err) {
        console.error('PDF error:', err);
        printSection.style.display = 'none';
        if (btn) { btn.disabled = false; btn.innerHTML = originalHTML; }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>