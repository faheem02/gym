<?php
$activePage = 'member_payments';
$pageTitle = 'Member Payments';
include __DIR__ . '/../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'payment') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Payment recorded successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Payment deleted.</div>';

$members = $pdo->query("SELECT id, name, phone FROM members ORDER BY name")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? 'cash';
    $payment_for = trim($_POST['payment_for'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $pay_date = trim($_POST['payment_date'] ?? date('Y-m-d'));

    if ($member_id <= 0) {
        $error = 'Please select a member.';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than 0.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO member_payments (member_id, amount, payment_method, payment_for, notes, payment_date) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$member_id, $amount, $method, $payment_for ?: null, $notes ?: null, $pay_date]);
        header('Location: /gym/members/payments.php?msg=payment');
        exit;
    }
}

$filterMember = $_GET['member_id'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

$sql = "SELECT mp.*, m.name AS member_name, m.phone AS member_phone FROM member_payments mp LEFT JOIN members m ON m.id = mp.member_id WHERE 1=1";
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
?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card" style="border-top:3px solid #10b981;">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-hand-holding-usd text-success me-2"></i>Record Payment</h6>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-3 position-relative">
                        <label class="form-label fw-semibold"><i class="fas fa-search me-1 text-muted"></i>Search Member *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" id="payMemberSearch" class="form-control" placeholder="Type member name or phone..." autocomplete="off" spellcheck="false" required>
                            <button type="button" class="btn btn-outline-secondary" id="clearPayMember" style="display:none;"><i class="fas fa-times"></i></button>
                        </div>
                        <input type="hidden" name="member_id" id="payMemberId" value="<?php echo htmlspecialchars($_POST['member_id'] ?? ($filterMember ?: '')); ?>" required>
                        <div id="payMemberResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:220px; overflow-y:auto; display:none; border-radius:6px;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-money-bill me-1 text-muted"></i>Amount (Rs.) *</label>
                        <input type="number" step="1" name="amount" class="form-control form-control-lg" min="1" required placeholder="0" value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-list me-1 text-muted"></i>Payment For</label>
                        <select name="payment_for" class="form-select">
                            <option value="">-- Select --</option>
                            <?php foreach (['Membership Fee', 'Plan Renewal', 'Registration Fee', 'Personal Training', 'Other'] as $pf): ?>
                                <option value="<?php echo $pf; ?>" <?php echo ($_POST['payment_for'] ?? '') === $pf ? 'selected' : ''; ?>><?php echo $pf; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-credit-card me-1 text-muted"></i>Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <?php foreach (['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer', 'easypaisa' => 'EasyPaisa', 'jazzcash' => 'JazzCash'] as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($_POST['payment_method'] ?? 'cash') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-calendar me-1 text-muted"></i>Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-sticky-note me-1 text-muted"></i>Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional note" value="<?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-success btn-lg fw-bold w-100"><i class="fas fa-check-circle me-1"></i>Record Payment</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card" style="border-top:3px solid #8b5cf6;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-history text-primary me-2"></i>Payment History</h6>
                    <span class="badge" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-size:0.85rem;">Total: Rs.<?php echo number_format($totalCollected, 0); ?></span>
                </div>
                <form class="row g-2 mb-3" method="GET">
                    <div class="col-md-3">
                        <select name="member_id" class="form-select form-select-sm">
                            <option value="">All Members</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo $filterMember == $m['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDateFrom); ?>" placeholder="From">
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDateTo); ?>" placeholder="To">
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-dark flex-grow-1"><i class="fas fa-filter me-1"></i>Filter</button>
                        <a href="payments.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Member</th>
                                <th>For</th>
                                <th>Method</th>
                                <th class="text-end">Amount</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-receipt me-1"></i>No payments recorded.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($payments as $i => $p): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                                    <td class="fw-semibold">
                                        <a href="ledger.php?id=<?php echo $p['member_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($p['member_name'] ?? 'Unknown'); ?></a>
                                    </td>
                                    <td><span class="badge" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;"><?php echo htmlspecialchars($p['payment_for'] ?? '-'); ?></span></td>
                                    <td><span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></span></td>
                                    <td class="text-end fw-bold text-success">Rs.<?php echo number_format($p['amount'], 0); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($p['notes'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var members = <?php echo json_encode($members); ?>;
    var searchInput = document.getElementById('payMemberSearch');
    var hiddenInput = document.getElementById('payMemberId');
    var resultsBox = document.getElementById('payMemberResults');
    var clearBtn = document.getElementById('clearPayMember');

    // Pre-fill if active member ID exists
    var activeId = hiddenInput.value;
    if (activeId) {
        var found = members.find(function(m) { return m.id == activeId; });
        if (found) {
            searchInput.value = found.name + (found.phone ? ' (' + found.phone + ')' : '');
            clearBtn.style.display = 'inline-block';
        }
    }

    function renderList(query) {
        var q = (query || '').trim().toLowerCase();
        resultsBox.innerHTML = '';

        if (q.length < 1) {
            resultsBox.style.display = 'none';
            return;
        }

        var filtered = members.filter(function(m) {
            return m.name.toLowerCase().includes(q) || (m.phone && m.phone.toLowerCase().includes(q));
        });

        if (filtered.length === 0) {
            resultsBox.innerHTML = '<div class="list-group-item text-muted py-2 text-center small"><i class="fas fa-user-slash me-1"></i>No members found</div>';
            resultsBox.style.display = 'block';
            return;
        }

        filtered.slice(0, 40).forEach(function(m) {
            var a = document.createElement('a');
            a.href = '#';
            a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 small';
            a.innerHTML = '<div><strong>' + escapeHtml(m.name) + '</strong><br><span class="text-muted"><i class="fas fa-phone me-1"></i>' + (m.phone || 'No phone') + '</span></div><span class="badge bg-light text-dark border">Select</span>';
            
            a.addEventListener('click', function(e) {
                e.preventDefault();
                searchInput.value = m.name + (m.phone ? ' (' + m.phone + ')' : '');
                hiddenInput.value = m.id;
                resultsBox.style.display = 'none';
                clearBtn.style.display = 'inline-block';
            });
            resultsBox.appendChild(a);
        });

        resultsBox.style.display = 'block';
    }

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return (text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 1) {
            renderList(this.value);
        }
    });

    searchInput.addEventListener('input', function() {
        hiddenInput.value = '';
        if (this.value.trim().length > 0) {
            clearBtn.style.display = 'inline-block';
        } else {
            clearBtn.style.display = 'none';
        }
        renderList(this.value);
    });

    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        hiddenInput.value = '';
        clearBtn.style.display = 'none';
        resultsBox.style.display = 'none';
        resultsBox.innerHTML = '';
        searchInput.focus();
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#payMemberSearch') && !e.target.closest('#payMemberResults')) {
            resultsBox.style.display = 'none';
        }
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
