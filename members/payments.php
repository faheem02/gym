<?php
$activePage = 'member_payments';
$pageTitle = 'Member Payments';
include __DIR__ . '/../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'payment') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Payment recorded successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Payment deleted.</div>';

$members = $pdo->query("SELECT id, name, phone, registration_fee, monthly_fee, kids_fee, trainer_fee, trainer_id, access_type FROM members ORDER BY name")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $amounts = $_POST['amounts'] ?? [];
    $method = $_POST['payment_method'] ?? 'cash';
    $notes = trim($_POST['notes'] ?? '');
    $pay_date = trim($_POST['payment_date'] ?? date('Y-m-d'));

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
        $stmt = $pdo->prepare('INSERT INTO member_payments (member_id, amount, payment_method, payment_for, notes, payment_date) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            $stmt->execute([$member_id, $row['amount'], $method, $row['for'] ?: null, $notes ?: null, $pay_date]);
        }
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
    var payForRows = document.getElementById('payForRows');

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return (text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function feeOptionsFor(member) {
        var opts = [];
        if (parseFloat(member.monthly_fee || 0) > 0) opts.push('Membership Fee');
        if (parseFloat(member.kids_fee || 0) > 0 || (member.access_type === 'kids_play' || member.access_type === 'both')) opts.push('Kids Fee');
        if ((member.trainer_id && member.trainer_id > 0) || parseFloat(member.trainer_fee || 0) > 0) opts.push('Personal Training');
        if (parseFloat(member.registration_fee || 0) > 0) opts.push('Registration Fee');
        if (!opts.length) opts.push('Membership Fee');
        opts.push('Plan Renewal');
        opts.push('Other');
        return opts;
    }

    function renderPayFor(member) {
        var opts = feeOptionsFor(member);
        payForRows.innerHTML = '';
        opts.forEach(function(v) {
            var row = document.createElement('div');
            row.className = 'input-group mb-2';
            var label = document.createElement('span');
            label.className = 'input-group-text fw-semibold';
            label.style.minWidth = '150px';
            label.textContent = v;
            var input = document.createElement('input');
            input.type = 'number';
            input.step = '1';
            input.min = '0';
            input.name = 'amounts[' + v + ']';
            input.className = 'form-control';
            input.placeholder = '0 (leave empty if not paying)';
            row.appendChild(label);
            row.appendChild(input);
            payForRows.appendChild(row);
        });
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
                a.innerHTML = '<div><strong>' + escapeHtml(m.name) + '</strong><br><span class="text-muted"><i class="fas fa-phone me-1"></i>' + escapeHtml(m.phone || 'No phone') + '</span></div><span class="badge bg-light text-dark border">Select</span>';

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
        },
        onSelect: renderPayFor,
        onClear: resetPayFor
    });

    // Pre-fill modal if a member was selected but submission failed
    var activeId = document.getElementById('payMemberId').value;
    if (activeId) {
        var found = members.find(function(m) { return m.id == activeId; });
        if (found) {
            document.getElementById('payMemberSearch').value = found.name + (found.phone ? ' (' + found.phone + ')' : '');
            document.getElementById('clearPayMember').style.display = 'inline-block';
            renderPayFor(found);
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