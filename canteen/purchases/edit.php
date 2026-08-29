<?php
$activePage = 'canteen_purchases';
$pageTitle = 'Edit Purchase';
include __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM canteen_purchases WHERE id = ?');
$stmt->execute([$id]);
$purchase = $stmt->fetch();

if (!$purchase) { echo '<div class="alert alert-warning">Not found. <a href="index.php">Back</a></div>'; include __DIR__ . '/../../includes/footer.php'; exit; }

$stmt = $pdo->prepare('SELECT * FROM canteen_purchase_items WHERE purchase_id = ?');
$stmt->execute([$id]);
$existingItems = $stmt->fetchAll();

$suppliers = $pdo->query("SELECT id, name, balance FROM canteen_suppliers WHERE status = 'active' ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id, name, unit, purchase_price, stock_qty FROM canteen_products WHERE status = 'active' ORDER BY name")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $purchase_date = trim($_POST['purchase_date'] ?? date('Y-m-d'));
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    $item_product_ids = $_POST['item_product_id'] ?? [];
    $item_quantities = $_POST['item_quantity'] ?? [];
    $item_prices = $_POST['item_price'] ?? [];

    $total = 0;
    $validItems = [];
    for ($i = 0; $i < count($item_product_ids); $i++) {
        $pid = (int)$item_product_ids[$i];
        $qty = (float)$item_quantities[$i];
        $price = (float)$item_prices[$i];
        if ($pid > 0 && $qty > 0 && $price > 0) {
            $subtotal = $qty * $price;
            $total += $subtotal;
            $validItems[] = ['product_id' => $pid, 'quantity' => $qty, 'unit_price' => $price, 'subtotal' => $subtotal];
        }
    }

    if (empty($validItems)) { $error = 'Please add at least one item.'; }
    elseif ($supplier_id <= 0) { $error = 'Please select a supplier.'; }
    else {
        $pdo->beginTransaction();
        try {
            $oldDue = (float)$purchase['total_amount'] - (float)$purchase['paid_amount'];
            if ($oldDue > 0 && $purchase['supplier_id']) {
                $stmt = $pdo->prepare('UPDATE canteen_suppliers SET balance = balance - ? WHERE id = ?');
                $stmt->execute([$oldDue, $purchase['supplier_id']]);
            }

            foreach ($existingItems as $item) {
                $stmt = $pdo->prepare('UPDATE canteen_products SET stock_qty = stock_qty - ? WHERE id = ?');
                $stmt->execute([$item['qty'], $item['product_id']]);
                $stmt = $pdo->prepare('INSERT INTO canteen_stock_log (product_id, type, quantity, reference_id, notes) VALUES (?, "adjustment_out", ?, ?, ?)');
                $stmt->execute([$item['product_id'], $item['qty'], $id, 'Purchase #' . $id . ' edit reversal']);
            }

            $stmt = $pdo->prepare('DELETE FROM canteen_purchase_items WHERE purchase_id = ?');
            $stmt->execute([$id]);

            $stmt = $pdo->prepare('UPDATE canteen_purchases SET supplier_id=?, total_amount=?, paid_amount=?, purchase_date=?, notes=? WHERE id=?');
            $stmt->execute([$supplier_id, $total, $paid_amount, $purchase_date, $notes ?: null, $id]);

            foreach ($validItems as $item) {
                $stmt = $pdo->prepare('INSERT INTO canteen_purchase_items (purchase_id, product_id, qty, unit_price, total) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['subtotal']]);
                $stmt = $pdo->prepare('UPDATE canteen_products SET stock_qty = stock_qty + ? WHERE id = ?');
                $stmt->execute([$item['quantity'], $item['product_id']]);
                $stmt = $pdo->prepare('INSERT INTO canteen_stock_log (product_id, type, quantity, reference_id, notes) VALUES (?, "purchase", ?, ?, ?)');
                $stmt->execute([$item['product_id'], $item['quantity'], $id, 'Purchase #' . $id . ' edit']);
            }

            $newDue = $total - $paid_amount;
            if ($newDue > 0) {
                $stmt = $pdo->prepare('UPDATE canteen_suppliers SET balance = balance + ? WHERE id = ?');
                $stmt->execute([$newDue, $supplier_id]);
            }

            $pdo->commit();
            header('Location: /gym/canteen/purchases/index.php?msg=updated');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <a href="index.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="card form-card" style="max-width:900px;border-top:3px solid #f7b731;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-edit me-2" style="color:#f7b731;"></i>Edit Purchase #<?php echo $id; ?></h5>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="" id="purchaseForm">
            <div class="row g-3 mb-4">
                <div class="col-md-5 position-relative">
                    <label class="form-label fw-semibold"><i class="fas fa-truck me-1 text-muted"></i>Supplier *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-truck text-muted"></i></span>
                        <input type="text" id="purchSupplierSearch" class="form-control" placeholder="Type supplier name..." autocomplete="off" spellcheck="false" required>
                        <button type="button" class="btn btn-outline-secondary" id="clearPurchSupplier" style="display:none;"><i class="fas fa-times"></i></button>
                    </div>
                    <input type="hidden" name="supplier_id" id="purchSupplierId" value="<?php echo htmlspecialchars($purchase['supplier_id']); ?>" required>
                    <div id="purchSupplierResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1050; max-height:220px; overflow-y:auto; display:none; border-radius:6px;"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar me-1 text-muted"></i>Purchase Date *</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?php echo $purchase['purchase_date']; ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold"><i class="fas fa-money-bill me-1 text-muted"></i>Paid Amount (Rs.)</label>
                    <input type="number" step="1" name="paid_amount" class="form-control" value="<?php echo $purchase['paid_amount']; ?>" min="0" id="paidAmount">
                </div>
            </div>

            <h6 class="fw-bold mb-3"><i class="fas fa-list me-1 text-muted"></i>Items</h6>
            <div class="table-responsive mb-3">
                <table class="table align-middle" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="min-width:200px;">Product</th>
                            <th style="width:120px;">Quantity</th>
                            <th style="width:150px;">Unit Price (Rs.)</th>
                            <th style="width:130px;">Subtotal</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <?php foreach ($existingItems as $ei): ?>
                        <tr>
                            <td>
                                <select name="item_product_id[]" class="form-select form-select-sm product-select" required>
                                    <option value="">Select product...</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['purchase_price']; ?>" data-unit="<?php echo $p['unit']; ?>" <?php echo $p['id'] == $ei['product_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['unit']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" name="item_quantity[]" class="form-control form-control-sm qty-input" min="0.01" required value="<?php echo $ei['qty']; ?>"></td>
                            <td><input type="number" step="1" name="item_price[]" class="form-control form-control-sm price-input" min="0" required value="<?php echo $ei['unit_price']; ?>"></td>
                            <td class="subtotal fw-bold">Rs.<?php echo number_format($ei['total'], 0); ?></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-outline-success mb-4" id="addRow"><i class="fas fa-plus me-1"></i>Add Item</button>

            <div class="row g-3 align-items-end mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Total</label>
                    <div class="form-control form-control-lg bg-light fw-bold" id="totalDisplay">Rs.<?php echo number_format($purchase['total_amount'], 0); ?></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Due</label>
                    <div class="form-control form-control-lg bg-light fw-bold text-danger" id="dueDisplay">Rs.<?php echo number_format(max(0, $purchase['total_amount'] - $purchase['paid_amount']), 0); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><i class="fas fa-sticky-note me-1 text-muted"></i>Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?php echo htmlspecialchars($purchase['notes'] ?? ''); ?>">
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-lg fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-save me-1"></i>Update Purchase</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('itemsBody');

    function recalc() {
        let total = 0;
        document.querySelectorAll('#itemsBody tr').forEach(function(row) {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const sub = qty * price;
            row.querySelector('.subtotal').textContent = 'Rs.' + Math.round(sub).toLocaleString();
            total += sub;
        });
        document.getElementById('totalDisplay').textContent = 'Rs.' + Math.round(total).toLocaleString();
        const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
        const due = total - paid;
        document.getElementById('dueDisplay').textContent = 'Rs.' + Math.round(Math.max(0, due)).toLocaleString();
    }

    function cloneRow() {
        const firstRow = tbody.querySelector('tr');
        const newRow = firstRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach(function(inp) { inp.value = ''; });
        newRow.querySelectorAll('select').forEach(function(sel) { sel.selectedIndex = 0; });
        newRow.querySelector('.subtotal').textContent = 'Rs.0';
        tbody.appendChild(newRow);
        bindEvents(newRow);
    }

    function bindEvents(row) {
        row.querySelector('.remove-row').addEventListener('click', function() {
            if (tbody.querySelectorAll('tr').length > 1) {
                row.remove();
                recalc();
            }
        });
        row.querySelector('.product-select').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const priceInput = row.querySelector('.price-input');
            if (selected.value) {
                priceInput.value = selected.getAttribute('data-price') || 0;
            }
            recalc();
        });
        row.querySelector('.qty-input').addEventListener('input', recalc);
        row.querySelector('.price-input').addEventListener('input', recalc);
    }

    document.getElementById('addRow').addEventListener('click', cloneRow);
    document.getElementById('paidAmount').addEventListener('input', recalc);

    tbody.querySelectorAll('tr').forEach(function(row) { bindEvents(row); });
    recalc();

    // Supplier Autocomplete
    (function() {
        var suppliers = <?php echo json_encode($suppliers); ?>;
        var searchInput = document.getElementById('purchSupplierSearch');
        var hiddenInput = document.getElementById('purchSupplierId');
        var resultsBox = document.getElementById('purchSupplierResults');
        var clearBtn = document.getElementById('clearPurchSupplier');

        var activeId = hiddenInput.value;
        if (activeId) {
            var found = suppliers.find(function(s) { return s.id == activeId; });
            if (found) {
                searchInput.value = found.name;
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
                var balText = bal > 0 ? 'Due: Rs.' + Math.round(bal).toLocaleString() : 'Rs.0';
                a.innerHTML = '<div><strong>' + escapeHtml(s.name) + '</strong></div><span class="badge bg-light text-dark border">' + balText + '</span>';
                
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    searchInput.value = s.name;
                    hiddenInput.value = s.id;
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
            if (!e.target.closest('#purchSupplierSearch') && !e.target.closest('#purchSupplierResults')) {
                resultsBox.style.display = 'none';
            }
        });
    })();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
