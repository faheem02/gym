<?php
$activePage = 'member_payments';
$pageTitle = 'Member Payments';
include __DIR__ . '/../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'payment') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Payment recorded successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Payment deleted.</div>';

$members = $pdo->query("SELECT m.id, m.name, m.phone, m.registration_fee, m.monthly_fee, m.kids_fee, m.trainer_fee, m.trainer_id, m.access_type,
    COALESCE((SELECT mp.weight FROM member_payments mp WHERE mp.member_id = m.id AND mp.weight IS NOT NULL AND mp.weight > 0 ORDER BY mp.payment_date DESC, mp.id DESC LIMIT 1), m.weight) AS weight,
    COALESCE((SELECT mp.age FROM member_payments mp WHERE mp.member_id = m.id AND mp.age IS NOT NULL AND mp.age > 0 ORDER BY mp.payment_date DESC, mp.id DESC LIMIT 1), m.age) AS age
    FROM members m ORDER BY m.name")->fetchAll();
$financialSummaries = getAllMembersFinancialSummary($pdo);

$allWeightHistory = $pdo->query("
    SELECT mp.member_id, mp.weight as cur_weight,
        (SELECT mp2.weight FROM member_payments mp2 WHERE mp2.member_id = mp.member_id AND mp2.id < mp.id AND mp2.weight IS NOT NULL AND mp2.weight > 0 ORDER BY mp2.id DESC LIMIT 1) as prev_weight
    FROM member_payments mp
    INNER JOIN (
        SELECT MAX(id) as max_id FROM member_payments WHERE weight IS NOT NULL AND weight > 0 GROUP BY member_id
    ) latest ON mp.id = latest.max_id
")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $amounts = $_POST['amounts'] ?? [];
    $method = $_POST['payment_method'] ?? 'cash';
    $notes = trim($_POST['notes'] ?? '');
    $pay_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $weight = (isset($_POST['weight']) && $_POST['weight'] !== '') ? (float)$_POST['weight'] : null;
    $age = (isset($_POST['age']) && $_POST['age'] !== '') ? (int)$_POST['age'] : null;

    $rows = [];
    foreach ($amounts as $pf => $amt) {
        $amt = (float)$amt;
        if ($amt > 0) {
            $rows[] = ['for' => trim($pf), 'amount' => $amt];
        }
    }

    if ($member_id <= 0) {
        $error = 'Please select a member.';
    } elseif (empty($rows)) {
        $error = 'Enter an amount for at least one payment type.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO member_payments (member_id, amount, weight, age, payment_method, payment_for, notes, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            $stmt->execute([$member_id, $row['amount'], $weight, $age, $method, $row['for'] ?: null, $notes ?: null, $pay_date]);
        }
        if ($weight !== null && $weight > 0) {
            $updW = $pdo->prepare('UPDATE members SET weight = ? WHERE id = ?');
            $updW->execute([$weight, $member_id]);
        }
        if ($age !== null && $age > 0) {
            $updA = $pdo->prepare('UPDATE members SET age = ? WHERE id = ?');
            $updA->execute([$age, $member_id]);
        }
        header('Location: /gym/members/payments.php?msg=payment');
        exit;
    }
}

$filterMember = $_GET['member_id'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

$sql = "SELECT mp.*, m.name AS member_name, m.phone AS member_phone, m.weight AS current_weight,
    (SELECT mp2.weight FROM member_payments mp2 WHERE mp2.member_id = mp.member_id AND mp2.id < mp.id AND mp2.weight IS NOT NULL ORDER BY mp2.id DESC LIMIT 1) AS prev_weight,
    (SELECT mp2.age FROM member_payments mp2 WHERE mp2.member_id = mp.member_id AND mp2.id < mp.id AND mp2.age IS NOT NULL ORDER BY mp2.id DESC LIMIT 1) AS prev_age
    FROM member_payments mp LEFT JOIN members m ON m.id = mp.member_id WHERE 1=1";
$params = [];
if ($filterMember !== '') { $sql .= " AND mp.member_id = ?"; $params[] = $filterMember; }
if ($filterDateFrom !== '') { $sql .= " AND mp.payment_date >= ?"; $params[] = $filterDateFrom; }
if ($filterDateTo !== '') { $sql .= " AND mp.payment_date <= ?"; $params[] = $filterDateTo; }
$sql .= " ORDER BY mp.payment_date DESC, mp.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$totalCollected = 0;
foreach ($payments as $p) $totalCollected += (float)$p['amount'];

$filterMemberName = '';
if ($filterMember !== '') {
    foreach ($members as $m) {
        if ((int)$m['id'] === (int)$filterMember) { $filterMemberName = $m['name']; break; }
    }
}
?>

<div class="search-bar">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h6 class="fw-bold mb-0"><i class="fas fa-history me-2 text-primary"></i>Payment History</h6>
            <small class="text-muted">All member payments &mdash; use the search below to filter, or record a new payment.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-size:0.9rem;padding:0.55rem 0.9rem;">
                <i class="fas fa-money-bill-wave me-1"></i>Total: Rs.<?php echo number_format($totalCollected, 0); ?>
            </span>
            <button type="button" class="btn btn-success fw-bold" id="btnOpenRecordPayment">
                <i class="fas fa-plus-circle me-1"></i>Record Payment
            </button>
        </div>
    </div>
</div>

<!-- Filter / search bar -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4 position-relative">
                <label class="form-label fw-semibold mb-1"><i class="fas fa-search me-1 text-muted"></i>Search Member</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" id="filterMemberSearch" class="form-control" placeholder="Type member name or phone..." autocomplete="off" spellcheck="false" value="<?php echo htmlspecialchars($filterMemberName); ?>">
                    <button type="button" class="btn btn-outline-secondary" id="filterClearMember" style="display:<?php echo $filterMember !== '' ? 'inline-block' : 'none'; ?>;"><i class="fas fa-times"></i></button>
                    <input type="hidden" name="member_id" id="filterMemberId" value="<?php echo htmlspecialchars($filterMember); ?>">
                </div>
                <div id="filterMemberResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:220px; overflow-y:auto; display:none; border-radius:6px;"></div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold mb-1"><i class="fas fa-calendar me-1 text-muted"></i>From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold mb-1"><i class="fas fa-calendar-check me-1 text-muted"></i>To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filterDateTo); ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-dark fw-bold flex-grow-1"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="payments.php" class="btn btn-outline-secondary" title="Clear"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<?php 
$fMemberId = (int)$filterMember;
if ($fMemberId > 0 && isset($financialSummaries[$fMemberId])): 
    $fSummary = $financialSummaries[$fMemberId];
    $fBal = (float)$fSummary['balance'];
?>
<div class="card mb-4 shadow-sm <?php echo $fBal > 0 ? 'border-danger' : ($fBal < 0 ? 'border-info' : 'border-success'); ?>" style="border-left: 5px solid <?php echo $fBal > 0 ? '#ef4444' : ($fBal < 0 ? '#0ea5e9' : '#10b981'); ?>;">
    <div class="card-body py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span class="badge bg-dark">#<?php echo $fMemberId; ?></span>
                <h6 class="mb-0 fw-bold fs-6"><?php echo htmlspecialchars($filterMemberName); ?></h6>
                <?php if ($fBal > 0): ?>
                    <span class="badge text-bg-danger fs-6 fw-bold"><i class="fas fa-exclamation-triangle me-1"></i>Remaining Due: Rs. <?php echo number_format($fBal, 0); ?></span>
                <?php elseif ($fBal < 0): ?>
                    <span class="badge text-bg-info text-dark fs-6 fw-bold"><i class="fas fa-arrow-down me-1"></i>Advance Credit: Rs. <?php echo number_format(abs($fBal), 0); ?></span>
                <?php else: ?>
                    <span class="badge text-bg-success fs-6 fw-bold"><i class="fas fa-check-circle me-1"></i>All Dues Settled (Rs. 0)</span>
                <?php endif; ?>
            </div>
            <div class="text-muted small mt-1">
                Gross Charges: <strong>Rs. <?php echo number_format($fSummary['gross_total'], 0); ?></strong>
                <?php if ((float)$fSummary['discount'] > 0): ?>
                    | Concession: <strong class="text-danger">-Rs. <?php echo number_format($fSummary['discount'], 0); ?></strong>
                <?php endif; ?>
                | Net Payable: <strong>Rs. <?php echo number_format($fSummary['net_payable'], 0); ?></strong>
                | Total Paid: <strong class="text-success">Rs. <?php echo number_format($fSummary['total_paid'], 0); ?></strong>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="/gym/members/ledger.php?id=<?php echo $fMemberId; ?>" class="btn btn-outline-dark btn-sm fw-semibold">
                <i class="fas fa-book-open me-1"></i>View Full Ledger
            </a>
            <button type="button" class="btn btn-success btn-sm fw-bold" onclick="openRecordForMember(<?php echo $fMemberId; ?>)">
                <i class="fas fa-hand-holding-usd me-1"></i>Record Payment
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Payment history table - full width -->
<div class="card" style="border-top:3px solid #8b5cf6;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="fas fa-receipt text-primary me-2"></i>Payment History</h6>
            <span class="text-muted small"><?php echo count($payments); ?> record(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Member</th>
                        <th>For</th>
                        <th>Method</th>
                        <th>Weight</th>
                        <th>Age</th>
                        <th class="text-end">Amount Paid</th>
                        <th class="text-center">Remaining Balance</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4"><i class="fas fa-receipt me-1"></i>No payments recorded.</td></tr>
                    <?php endif; ?>
                    <?php
                    $changeText = '';
                    foreach ($payments as $i => $p):
                        $curW = $p['weight'];
                        $prevW = $p['prev_weight'];
                        $changeText = '';
                        if ($curW !== null && $curW !== '') {
                            $curW = (float)$curW;
                            if ($prevW !== null && $prevW !== '') {
                                $diff = $curW - (float)$prevW;
                                if (abs($diff) > 0.01) {
                                    if ($diff > 0) {
                                        $changeText = ' <span class="badge text-bg-warning" title="Gained">+' . number_format($diff, 1) . '</span>';
                                    } else {
                                        $changeText = ' <span class="badge text-bg-success" title="Lost">' . number_format($diff, 1) . '</span>';
                                    }
                                } else {
                                    $changeText = ' <span class="badge bg-light text-dark" title="No change">0.0</span>';
                                }
                            } else {
                                $changeText = ' <span class="badge bg-light text-dark">New</span>';
                            }
                        }
                        $curAgeTxt = '';
                        if ($p['age'] !== null && $p['age'] !== '') {
                            $curAgeTxt = '<span class="fw-semibold">' . (int)$p['age'] . ' yrs</span>';
                            if ($p['prev_age'] !== null && $p['prev_age'] !== '' && (int)$p['age'] !== (int)$p['prev_age']) {
                                $aDiff = (int)$p['age'] - (int)$p['prev_age'];
                                $curAgeTxt .= ' <span class="badge bg-light text-dark" title="Age change">' . ($aDiff > 0 ? '+' : '') . $aDiff . '</span>';
                            }
                        }
                        $fin = $financialSummaries[$p['member_id']] ?? null;
                        $bal = $fin ? (float)$fin['balance'] : 0;
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                        <td class="fw-semibold">
                            <a href="ledger.php?id=<?php echo $p['member_id']; ?>" class="text-decoration-none text-dark fw-bold"><?php echo htmlspecialchars($p['member_name'] ?? 'Unknown'); ?></a>
                        </td>
                        <td><span class="badge" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;"><?php echo htmlspecialchars($p['payment_for'] ?? '-'); ?></span></td>
                        <td><span class="badge bg-light text-dark border"><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></span></td>
                        <td>
                            <?php if ($p['weight'] !== null && $p['weight'] !== ''): ?>
                                <span class="fw-semibold"><?php echo number_format((float)$p['weight'], 1); ?> kg</span><?php echo $changeText; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($curAgeTxt !== ''): ?>
                                <?php echo $curAgeTxt; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold text-success">Rs.<?php echo number_format($p['amount'], 0); ?></td>
                        <td class="text-center">
                            <?php if ($bal > 0): ?>
                                <a href="ledger.php?id=<?php echo $p['member_id']; ?>" class="text-decoration-none" title="Gross: Rs.<?php echo number_format($fin['gross_total'], 0); ?> | Paid: Rs.<?php echo number_format($fin['total_paid'], 0); ?> &bull; Click to open ledger">
                                    <span class="badge text-bg-danger fw-bold d-inline-flex align-items-center gap-1" style="font-size:0.82rem;">
                                        <i class="fas fa-exclamation-circle"></i>Due: Rs.<?php echo number_format($bal, 0); ?>
                                    </span>
                                </a>
                            <?php elseif ($bal < 0): ?>
                                <a href="ledger.php?id=<?php echo $p['member_id']; ?>" class="text-decoration-none">
                                    <span class="badge text-bg-info text-dark fw-semibold">
                                        Adv: Rs.<?php echo number_format(abs($bal), 0); ?>
                                    </span>
                                </a>
                            <?php else: ?>
                                <span class="badge text-bg-success fw-normal">
                                    <i class="fas fa-check-circle me-1"></i>Settled
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?php echo htmlspecialchars($p['notes'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;">
                <h5 class="modal-title fw-bold"><i class="fas fa-hand-holding-usd me-2"></i>Record Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="" id="recordPaymentForm">
                    <div class="mb-3 position-relative">
                        <label class="form-label fw-semibold"><i class="fas fa-search me-1 text-muted"></i>Search Member *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" id="payMemberSearch" class="form-control" placeholder="Type member name or phone..." autocomplete="off" spellcheck="false" required>
                            <button type="button" class="btn btn-outline-secondary" id="clearPayMember" style="display:none;"><i class="fas fa-times"></i></button>
                        </div>
                        <input type="hidden" name="member_id" id="payMemberId" value="<?php echo htmlspecialchars($_POST['member_id'] ?? ''); ?>">
                        <div id="payMemberResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:220px; overflow-y:auto; display:none; border-radius:6px;"></div>
                        <div id="memberBalanceBanner" style="display:none;" class="mt-2"></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="fas fa-weight-hanging me-1 text-muted"></i>Current Weight (kg) <span class="text-muted fw-normal small">(optional &mdash; for progress tracking)</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-weight-hanging"></i></span>
                                <input type="number" step="0.01" min="0" name="weight" id="payWeight" class="form-control" placeholder="e.g. 70">
                                <span class="input-group-text">kg</span>
                            </div>
                            <small class="text-muted d-block mt-1" id="weightHint"></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="fas fa-sort-numeric-up me-1 text-muted"></i>Current Age <span class="text-muted fw-normal small">(optional)</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-sort-numeric-up"></i></span>
                                <input type="number" step="1" min="1" max="120" name="age" id="payAge" class="form-control" placeholder="e.g. 28">
                                <span class="input-group-text">yrs</span>
                            </div>
                            <small class="text-muted d-block mt-1" id="ageHint"></small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-list me-1 text-muted"></i>Payment For (Enter an amount for each)</label>
                        <div id="payForRows">
                            <div class="text-muted small py-2"><i class="fas fa-user me-1"></i>Select a member first &mdash; their payment options will appear here.</div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="fas fa-credit-card me-1 text-muted"></i>Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <?php foreach (['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer'] as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo ($_POST['payment_method'] ?? 'cash') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="fas fa-calendar me-1 text-muted"></i>Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold"><i class="fas fa-sticky-note me-1 text-muted"></i>Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional note" value="<?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-success btn-lg fw-bold w-100"><i class="fas fa-check-circle me-1"></i>Record Payment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var members = <?php echo json_encode($members); ?>;
    var finSummaries = <?php echo json_encode($financialSummaries); ?>;
    var payForRows = document.getElementById('payForRows');
    var weightInput = document.getElementById('payWeight');
    var weightHint = document.getElementById('weightHint');
    var ageInput = document.getElementById('payAge');
    var ageHint = document.getElementById('ageHint');
    var balanceBanner = document.getElementById('memberBalanceBanner');

    var weightHistory = {};
    <?php foreach ($allWeightHistory as $wh): ?>
        weightHistory[<?php echo (int)$wh['member_id']; ?>] = {
            cur: parseFloat(<?php echo (float)$wh['cur_weight']; ?>),
            prev: <?php echo ($wh['prev_weight'] !== null && $wh['prev_weight'] !== '') ? 'parseFloat(' . (float)$wh['prev_weight'] . ')' : 'null'; ?>
        };
    <?php endforeach; ?>

    function renderMemberBalance(member) {
        if (!balanceBanner || !member) return;
        var fs = finSummaries[member.id];
        if (fs) {
            var bal = parseFloat(fs.balance || 0);
            if (bal > 0) {
                balanceBanner.className = 'alert alert-danger d-flex flex-wrap justify-content-between align-items-center py-2 px-3 mb-0 mt-2 rounded-3 border-danger';
                balanceBanner.innerHTML = '<div><div class="fw-bold text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Remaining Due: Rs. ' + Math.round(bal).toLocaleString() + '</div><div class="small text-muted">Gross: Rs. ' + Math.round(fs.gross_total).toLocaleString() + (fs.discount > 0 ? ' | Disc: -Rs. ' + Math.round(fs.discount).toLocaleString() : '') + ' | Net: Rs. ' + Math.round(fs.net_payable).toLocaleString() + ' | Paid: Rs. ' + Math.round(fs.total_paid).toLocaleString() + '</div></div><a href="/gym/members/ledger.php?id=' + member.id + '" target="_blank" class="btn btn-sm btn-outline-danger fw-semibold"><i class="fas fa-book-open me-1"></i>Ledger</a>';
                balanceBanner.style.display = 'flex';
            } else if (bal < 0) {
                balanceBanner.className = 'alert alert-info d-flex flex-wrap justify-content-between align-items-center py-2 px-3 mb-0 mt-2 rounded-3 border-info';
                balanceBanner.innerHTML = '<div><div class="fw-bold text-info"><i class="fas fa-arrow-down me-1"></i>Advance Credit: Rs. ' + Math.round(Math.abs(bal)).toLocaleString() + '</div><div class="small text-muted">All current charges paid in advance</div></div><a href="/gym/members/ledger.php?id=' + member.id + '" target="_blank" class="btn btn-sm btn-outline-info fw-semibold"><i class="fas fa-book-open me-1"></i>Ledger</a>';
                balanceBanner.style.display = 'flex';
            } else {
                balanceBanner.className = 'alert alert-success d-flex flex-wrap justify-content-between align-items-center py-2 px-3 mb-0 mt-2 rounded-3 border-success';
                balanceBanner.innerHTML = '<div><div class="fw-bold text-success"><i class="fas fa-check-circle me-1"></i>All Dues Settled (Rs. 0)</div><div class="small text-muted">No outstanding balance on account</div></div><a href="/gym/members/ledger.php?id=' + member.id + '" target="_blank" class="btn btn-sm btn-outline-success fw-semibold"><i class="fas fa-book-open me-1"></i>Ledger</a>';
                balanceBanner.style.display = 'flex';
            }
        } else {
            balanceBanner.style.display = 'none';
            balanceBanner.innerHTML = '';
        }
    }

    function resetMemberBalance() {
        if (balanceBanner) {
            balanceBanner.style.display = 'none';
            balanceBanner.innerHTML = '';
        }
    }

    function populateWeight(member) {
        if (!member) return;
        var h = weightHistory[member.id];
        var w = (h && h.cur > 0) ? h.cur : (member && member.weight ? parseFloat(member.weight) : null);
        weightInput.value = (w > 0) ? w : '';
        if (h && h.prev > 0) {
            var diff = h.cur - h.prev;
            weightHint.innerHTML = 'Last recorded: <strong>' + h.cur.toFixed(1) + ' kg</strong> (previous ' + h.prev.toFixed(1) + ' kg &mdash; ' +
                (Math.abs(diff) > 0.01 ? (diff > 0 ? '<span class="text-warning fw-semibold">gained +' + diff.toFixed(1) + '</span>' : '<span class="text-success fw-semibold">lost ' + Math.abs(diff).toFixed(1) + '</span>') : 'no change') + ')';
        } else if (w > 0) {
            weightHint.innerHTML = 'Current weight: <strong>' + w.toFixed(1) + ' kg</strong>';
        } else {
            weightHint.innerHTML = '';
        }

        var a = member && member.age ? parseInt(member.age, 10) : null;
        ageInput.value = (a > 0) ? a : '';
        ageHint.innerHTML = (a > 0) ? 'Current age: <strong>' + a + ' yrs</strong>' : '';
    }

    function resetWeight() {
        weightInput.value = '';
        weightHint.innerHTML = '';
        ageInput.value = '';
        ageHint.innerHTML = '';
    }

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return (text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function feeOptionsFor(member) {
        var opts = [];
        var mf = parseFloat(member.monthly_fee || 0);
        if (mf > 0) {
            opts.push({ key: 'Membership Fee', label: 'Membership Fee', amount: mf, isDefault: true });
        }
        var tf = parseFloat(member.trainer_fee || 0);
        var hasTrainer = (member.trainer_id && parseInt(member.trainer_id, 10) > 0) || tf > 0;
        if (hasTrainer) {
            opts.push({ key: 'Personal Training', label: 'Personal Training', amount: tf, isDefault: true });
        }
        var kf = parseFloat(member.kids_fee || 0);
        if (kf > 0 || (member.access_type === 'kids_play' || member.access_type === 'both')) {
            opts.push({ key: 'Kids Fee', label: 'Kids Fee', amount: kf, isDefault: (kf > 0) });
        }
        var rf = parseFloat(member.registration_fee || 0);
        if (rf > 0) {
            opts.push({ key: 'Registration Fee', label: 'Registration Fee', amount: 0, isDefault: false });
        }
        if (!opts.length) {
            opts.push({ key: 'Membership Fee', label: 'Membership Fee', amount: 0, isDefault: false });
        }
        opts.push({ key: 'Plan Renewal', label: 'Plan Renewal', amount: 0, isDefault: false });
        opts.push({ key: 'Other', label: 'Other', amount: 0, isDefault: false });
        return opts;
    }

    function updateTotalFeeDisplay() {
        var total = 0;
        var inputs = payForRows.querySelectorAll('.fee-amount-input');
        inputs.forEach(function(inp) {
            var val = parseFloat(inp.value || 0);
            if (val > 0) total += val;
        });
        var totalEl = document.getElementById('payTotalAmount');
        if (totalEl) {
            totalEl.textContent = 'Rs. ' + Math.round(total).toLocaleString();
        }
    }

    function renderPayFor(member) {
        var opts = feeOptionsFor(member);
        payForRows.innerHTML = '';

        // Check if multiple pre-filled options exist
        var defaultOpts = opts.filter(function(o) { return o.isDefault && o.amount > 0; });
        if (defaultOpts.length > 1) {
            var toolbar = document.createElement('div');
            toolbar.className = 'd-flex align-items-center justify-content-between mb-2 flex-wrap gap-1 p-2 bg-light rounded border';
            toolbar.innerHTML = '<small class="text-muted"><i class="fas fa-magic text-primary me-1"></i>Auto-filled from member profile:</small>' +
                '<div class="btn-group btn-group-sm">' +
                    '<button type="button" class="btn btn-outline-primary py-0" id="btnPayAllFees"><i class="fas fa-check-double me-1"></i>Pay All</button>' +
                    '<button type="button" class="btn btn-outline-success py-0" id="btnPayMonthlyOnly"><i class="fas fa-calendar-check me-1"></i>Monthly Only</button>' +
                    '<button type="button" class="btn btn-outline-secondary py-0" id="btnClearFees"><i class="fas fa-eraser me-1"></i>Clear All</button>' +
                '</div>';
            payForRows.appendChild(toolbar);

            toolbar.querySelector('#btnPayAllFees').addEventListener('click', function() {
                var inputs = payForRows.querySelectorAll('.fee-amount-input');
                inputs.forEach(function(inp) {
                    var def = inp.getAttribute('data-default') || '';
                    inp.value = def;
                });
                updateTotalFeeDisplay();
            });

            toolbar.querySelector('#btnPayMonthlyOnly').addEventListener('click', function() {
                var inputs = payForRows.querySelectorAll('.fee-amount-input');
                inputs.forEach(function(inp) {
                    var type = inp.getAttribute('data-type');
                    if (type === 'Membership Fee') {
                        inp.value = inp.getAttribute('data-default') || '';
                    } else {
                        inp.value = '';
                    }
                });
                updateTotalFeeDisplay();
            });

            toolbar.querySelector('#btnClearFees').addEventListener('click', function() {
                var inputs = payForRows.querySelectorAll('.fee-amount-input');
                inputs.forEach(function(inp) {
                    inp.value = '';
                });
                updateTotalFeeDisplay();
            });
        }

        opts.forEach(function(item) {
            var v = item.key;
            var defaultAmt = (item.isDefault && item.amount > 0) ? item.amount : '';
            var row = document.createElement('div');
            row.className = 'input-group mb-2';

            var label = document.createElement('span');
            label.className = 'input-group-text fw-semibold';
            label.style.minWidth = '160px';
            label.innerHTML = escapeHtml(item.label) + (item.amount > 0 ? ' <span class="badge bg-light text-muted border ms-1" style="font-size:0.75rem;">Rs.' + Math.round(item.amount).toLocaleString() + '</span>' : '');

            var input = document.createElement('input');
            input.type = 'number';
            input.step = '1';
            input.min = '0';
            input.name = 'amounts[' + v + ']';
            input.className = 'form-control fee-amount-input';
            input.placeholder = '0 (leave empty if not paying)';
            if (defaultAmt !== '') {
                input.value = defaultAmt;
            }
            input.setAttribute('data-default', defaultAmt);
            input.setAttribute('data-type', v);
            input.addEventListener('input', updateTotalFeeDisplay);

            var clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'btn btn-outline-secondary';
            clearBtn.title = 'Clear / Set to 0';
            clearBtn.innerHTML = '<i class="fas fa-times"></i>';
            clearBtn.addEventListener('click', function() {
                input.value = '';
                updateTotalFeeDisplay();
                input.focus();
            });

            row.appendChild(label);
            row.appendChild(input);
            row.appendChild(clearBtn);
            payForRows.appendChild(row);
        });

        var summaryBox = document.createElement('div');
        summaryBox.className = 'd-flex justify-content-between align-items-center p-2 rounded mt-1 border bg-light';
        summaryBox.innerHTML = '<span class="small fw-semibold text-muted"><i class="fas fa-calculator me-1"></i>Total Payment Amount:</span><span class="fs-6 fw-bold text-success" id="payTotalAmount">Rs. 0</span>';
        payForRows.appendChild(summaryBox);

        updateTotalFeeDisplay();
    }

    function resetPayFor() {
        payForRows.innerHTML = '<div class="text-muted small py-2"><i class="fas fa-user me-1"></i>Select a member first &mdash; their payment options will appear here.</div>';
    }

    function initMemberSearch(opts) {
        var input = document.getElementById(opts.inputId);
        var hidden = document.getElementById(opts.hiddenId);
        var box = document.getElementById(opts.resultsId);
        var clearBtn = document.getElementById(opts.clearBtnId);

        function renderList(query) {
            var q = (query || '').trim().toLowerCase();
            box.innerHTML = '';

            if (q.length < 1) {
                box.style.display = 'none';
                return;
            }

            var filtered = members.filter(function(m) {
                return m.name.toLowerCase().includes(q) || (m.phone && m.phone.toLowerCase().includes(q));
            });

            if (filtered.length === 0) {
                box.innerHTML = '<div class="list-group-item text-muted py-2 text-center small"><i class="fas fa-user-slash me-1"></i>No members found</div>';
                box.style.display = 'block';
                return;
            }

            filtered.slice(0, 40).forEach(function(m) {
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 small';
                
                var fs = finSummaries[m.id];
                var balHtml = '';
                if (fs) {
                    var b = parseFloat(fs.balance || 0);
                    if (b > 0) {
                        balHtml = '<span class="badge text-bg-danger ms-2" style="font-size:0.75rem;"><i class="fas fa-exclamation-circle me-1"></i>Due: Rs.' + Math.round(b).toLocaleString() + '</span>';
                    } else if (b < 0) {
                        balHtml = '<span class="badge text-bg-info text-dark ms-2" style="font-size:0.75rem;">Adv: Rs.' + Math.round(Math.abs(b)).toLocaleString() + '</span>';
                    } else {
                        balHtml = '<span class="badge text-bg-success ms-2" style="font-size:0.75rem;"><i class="fas fa-check-circle me-1"></i>Settled</span>';
                    }
                }

                a.innerHTML = '<div class="me-2"><div class="d-flex align-items-center flex-wrap"><strong>' + escapeHtml(m.name) + '</strong>' + balHtml + '</div><span class="text-muted small"><i class="fas fa-phone me-1"></i>' + escapeHtml(m.phone || 'No phone') + '</span></div><span class="badge bg-light text-dark border">Select</span>';

                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    input.value = m.name + (m.phone ? ' (' + m.phone + ')' : '');
                    hidden.value = m.id;
                    box.style.display = 'none';
                    box.innerHTML = '';
                    clearBtn.style.display = 'inline-block';
                    if (typeof opts.onSelect === 'function') opts.onSelect(m);
                });
                box.appendChild(a);
            });

            box.style.display = 'block';
        }

        input.addEventListener('focus', function() {
            if (this.value.trim().length >= 1) {
                renderList(this.value);
            }
        });

        input.addEventListener('input', function() {
            if (opts.onInput) opts.onInput();
            if (this.value.trim().length > 0) {
                clearBtn.style.display = 'inline-block';
            } else {
                clearBtn.style.display = 'none';
            }
            renderList(this.value);
        });

        clearBtn.addEventListener('click', function() {
            input.value = '';
            hidden.value = '';
            clearBtn.style.display = 'none';
            box.style.display = 'none';
            box.innerHTML = '';
            if (opts.onClear) opts.onClear();
            input.focus();
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#' + opts.inputId) && !e.target.closest('#' + opts.resultsId)) {
                box.style.display = 'none';
            }
        });
    }

    // ---- Modal member search (Record Payment) ----
    initMemberSearch({
        inputId: 'payMemberSearch',
        hiddenId: 'payMemberId',
        resultsId: 'payMemberResults',
        clearBtnId: 'clearPayMember',
        onInput: function() {
            document.getElementById('payMemberId').value = '';
            resetPayFor();
            resetWeight();
            resetMemberBalance();
        },
        onSelect: function(member) {
            renderPayFor(member);
            populateWeight(member);
            renderMemberBalance(member);
        },
        onClear: function() {
            resetPayFor();
            resetWeight();
            resetMemberBalance();
        }
    });

    // Helper to open modal pre-populated for a specific member
    window.openRecordForMember = function(memberId) {
        var found = members.find(function(m) { return m.id == memberId; });
        if (found) {
            document.getElementById('payMemberId').value = found.id;
            document.getElementById('payMemberSearch').value = found.name + (found.phone ? ' (' + found.phone + ')' : '');
            document.getElementById('clearPayMember').style.display = 'inline-block';
            renderPayFor(found);
            populateWeight(found);
            renderMemberBalance(found);
            var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('recordPaymentModal'));
            modal.show();
        }
    };

    // Pre-fill modal if a member was selected but submission failed, or requested via query string (?record=1&member_id=X)
    var activeId = document.getElementById('payMemberId').value;
    var urlParams = new URLSearchParams(window.location.search);
    var autoOpen = false;

    if (!activeId && urlParams.get('member_id')) {
        activeId = urlParams.get('member_id');
        if (urlParams.get('record') === '1') {
            autoOpen = true;
        }
    }

    if (activeId) {
        var found = members.find(function(m) { return m.id == activeId; });
        if (found) {
            document.getElementById('payMemberId').value = found.id;
            document.getElementById('payMemberSearch').value = found.name + (found.phone ? ' (' + found.phone + ')' : '');
            document.getElementById('clearPayMember').style.display = 'inline-block';
            renderPayFor(found);
            populateWeight(found);
            renderMemberBalance(found);
            if (autoOpen) {
                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('recordPaymentModal'));
                modal.show();
            }
        }
    }

    // ---- Filter member search (typeahead instead of select) ----
    initMemberSearch({
        inputId: 'filterMemberSearch',
        hiddenId: 'filterMemberId',
        resultsId: 'filterMemberResults',
        clearBtnId: 'filterClearMember',
        onInput: function() {
            document.getElementById('filterMemberId').value = '';
        },
        onSelect: null,
        onClear: null
    });

    // ---- Open modal ----
    var btnOpen = document.getElementById('btnOpenRecordPayment');
    btnOpen.addEventListener('click', function() {
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('recordPaymentModal'));
        modal.show();
    });

    // Auto-open modal if validation error occurred on POST
    <?php if ($error !== ''): ?>
    var modalErr = bootstrap.Modal.getOrCreateInstance(document.getElementById('recordPaymentModal'));
    modalErr.show();
    <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>