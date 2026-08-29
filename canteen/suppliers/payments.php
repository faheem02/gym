<?php
$activePage = 'canteen_payments';
$pageTitle = 'Supplier Payments';
include __DIR__ . '/../../includes/header.php';

$suppliers = $pdo->query("SELECT id, name, phone, balance FROM canteen_suppliers WHERE status = 'active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? 'cash';
    $notes = trim($_POST['notes'] ?? '');
    $pay_date = trim($_POST['payment_date'] ?? date('Y-m-d'));

    if ($supplier_id <= 0) {
        $error = 'Please select a supplier.';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than 0.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO canteen_supplier_payments (supplier_id, amount, payment_method, notes, payment_date) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$supplier_id, $amount, $method, $notes ?: null, $pay_date]);
        $stmt = $pdo->prepare('UPDATE canteen_suppliers SET balance = balance - ? WHERE id = ?');
        $stmt->execute([$amount, $supplier_id]);
        header('Location: /gym/canteen/suppliers/payments.php?msg=payment');
        exit;
    }
}

$msg = $_GET['msg'] ?? '';
if ($msg === 'payment') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Payment recorded successfully.</div>';

$filterSupplier = $_GET['supplier_id'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

$sql = "SELECT sp.*, cs.name AS supplier_name FROM canteen_supplier_payments sp LEFT JOIN canteen_suppliers cs ON cs.id = sp.supplier_id WHERE 1=1";
$params = [];
if ($filterSupplier !== '') { $sql .= " AND sp.supplier_id = ?"; $params[] = $filterSupplier; }
if ($filterDateFrom !== '') { $sql .= " AND sp.payment_date >= ?"; $params[] = $filterDateFrom; }
if ($filterDateTo !== '') { $sql .= " AND sp.payment_date <= ?"; $params[] = $filterDateTo; }
$sql .= " ORDER BY sp.payment_date DESC, sp.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card" style="border-top:3px solid #10b981;">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-hand-holding-usd text-success me-2"></i>Record Payment</h6>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-3 position-relative">
                        <label class="form-label fw-semibold"><i class="fas fa-search me-1 text-muted"></i>Search Supplier *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-truck text-muted"></i></span>
                            <input type="text" id="paySupplierSearch" class="form-control" placeholder="Type supplier name..." autocomplete="off" spellcheck="false" required>
                            <button type="button" class="btn btn-outline-secondary" id="clearPaySupplier" style="display:none;"><i class="fas fa-times"></i></button>
                        </div>
                        <input type="hidden" name="supplier_id" id="paySupplierId" value="<?php echo htmlspecialchars($_POST['supplier_id'] ?? ($filterSupplier ?: '')); ?>" required>
                        <div id="supplierBalance" class="form-text mt-1"></div>
                        <div id="paySupplierResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:220px; overflow-y:auto; display:none; border-radius:6px;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-money-bill me-1 text-muted"></i>Amount (Rs.) *</label>
                        <input type="number" step="1" name="amount" class="form-control form-control-lg" min="1" required placeholder="0" id="payAmount">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-credit-card me-1 text-muted"></i>Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash"><i class="fas fa-money-bill"></i> Cash</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-calendar me-1 text-muted"></i>Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="fas fa-sticky-note me-1 text-muted"></i>Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional note">
                    </div>
                    <button type="submit" class="btn btn-success btn-lg fw-bold w-100"><i class="fas fa-check-circle me-1"></i>Record Payment</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card" style="border-top:3px solid #8b5cf6;">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-history text-primary me-2"></i>Payment History</h6>
                <form class="row g-2 mb-3" method="GET">
                    <div class="col-md-4">
                        <select name="supplier_id" class="form-select form-select-sm">
                            <option value="">All Suppliers</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $filterSupplier == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDateFrom); ?>" placeholder="From">
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDateTo); ?>" placeholder="To">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>#</th><th>Date</th><th>Supplier</th><th>Method</th><th class="text-end">Amount</th><th>Notes</th></tr></thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-money-bill me-1"></i>No payments found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($payments as $i => $p): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                                    <td class="fw-semibold">
                                        <a href="ledger.php?id=<?php echo $p['supplier_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($p['supplier_name'] ?? 'Unknown'); ?></a>
                                    </td>
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
    var suppliers = <?php echo json_encode($suppliers); ?>;
    var searchInput = document.getElementById('paySupplierSearch');
    var hiddenInput = document.getElementById('paySupplierId');
    var resultsBox = document.getElementById('paySupplierResults');
    var clearBtn = document.getElementById('clearPaySupplier');
    var balEl = document.getElementById('supplierBalance');

    // Pre-fill if active supplier ID exists
    var activeId = hiddenInput.value;
    if (activeId) {
        var found = suppliers.find(function(s) { return s.id == activeId; });
        if (found) {
            searchInput.value = found.name;
            clearBtn.style.display = 'inline-block';
            showBal(found.balance);
        }
    }

    function showBal(bal) {
        var b = parseFloat(bal || 0);
        if (b > 0) { balEl.innerHTML = '<span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i>Due Balance: Rs.' + Math.round(b).toLocaleString() + '</span>'; }
        else if (b < 0) { balEl.innerHTML = '<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Advance: Rs.' + Math.round(Math.abs(b)).toLocaleString() + '</span>'; }
        else { balEl.innerHTML = '<span class="text-muted"><i class="fas fa-info-circle me-1"></i>Settled (Rs.0)</span>'; }
    }

    function renderList(query) {
        var q = (query || '').trim().toLowerCase();
        resultsBox.innerHTML = '';

        if (q.length < 1) {
            resultsBox.style.display = 'none';
            return;
        }

        var filtered = suppliers.filter(function(s) {
            return s.name.toLowerCase().includes(q);
        });

        if (filtered.length === 0) {
            resultsBox.innerHTML = '<div class="list-group-item text-muted py-2 text-center small"><i class="fas fa-truck me-1"></i>No suppliers found</div>';
            resultsBox.style.display = 'block';
            return;
        }

        filtered.forEach(function(s) {
            var a = document.createElement('a');
            a.href = '#';
            a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 small';
            var bal = parseFloat(s.balance || 0);
            var balBadge = '';
            if (bal > 0) {
                balBadge = '<span class="badge text-bg-danger">Due: Rs.' + Math.round(bal).toLocaleString() + '</span>';
            } else if (bal < 0) {
                balBadge = '<span class="badge text-bg-success">Adv: Rs.' + Math.round(Math.abs(bal)).toLocaleString() + '</span>';
            } else {
                balBadge = '<span class="badge text-bg-light border">Rs.0</span>';
            }
            a.innerHTML = '<div><strong>' + escapeHtml(s.name) + '</strong></div>' + balBadge;
            
            a.addEventListener('click', function(e) {
                e.preventDefault();
                searchInput.value = s.name;
                hiddenInput.value = s.id;
                resultsBox.style.display = 'none';
                clearBtn.style.display = 'inline-block';
                showBal(s.balance);
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
        balEl.innerHTML = '';
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
        balEl.innerHTML = '';
        clearBtn.style.display = 'none';
        resultsBox.style.display = 'none';
        resultsBox.innerHTML = '';
        searchInput.focus();
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#paySupplierSearch') && !e.target.closest('#paySupplierResults')) {
            resultsBox.style.display = 'none';
        }
    });
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
