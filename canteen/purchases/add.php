<?php
$activePage = 'canteen_purchases';
$pageTitle = 'New Purchase';
include __DIR__ . '/../../includes/header.php';

$suppliers = $pdo->query("SELECT id, name, balance FROM canteen_suppliers WHERE status = 'active' ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id, name, unit, purchase_price FROM canteen_products WHERE status = 'active' ORDER BY name")->fetchAll();

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

    if (empty($validItems)) {
        $error = 'Please add at least one item.';
    } elseif ($supplier_id <= 0) {
        $error = 'Please select a supplier.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO canteen_purchases (supplier_id, total_amount, paid_amount, purchase_date, notes) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$supplier_id, $total, $paid_amount, $purchase_date, $notes ?: null]);
            $purchase_id = $pdo->lastInsertId();

            foreach ($validItems as $item) {
                $stmt = $pdo->prepare('INSERT INTO canteen_purchase_items (purchase_id, product_id, qty, unit_price, total) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$purchase_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['subtotal']]);

                $stmt = $pdo->prepare('UPDATE canteen_products SET stock_qty = stock_qty + ? WHERE id = ?');
                $stmt->execute([$item['quantity'], $item['product_id']]);

                $stmt = $pdo->prepare('INSERT INTO canteen_stock_log (product_id, type, quantity, reference_id, notes) VALUES (?, "purchase", ?, ?, ?)');
                $stmt->execute([$item['product_id'], $item['quantity'], $purchase_id, 'Purchase #' . $purchase_id]);
            }

            $due = $total - $paid_amount;
            if ($due > 0) {
                $stmt = $pdo->prepare('UPDATE canteen_suppliers SET balance = balance + ? WHERE id = ?');
                $stmt->execute([$due, $supplier_id]);
            }

            $pdo->commit();
            header('Location: /gym/canteen/purchases/index.php?msg=added');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="card form-card" style="max-width: 900px;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-shopping-cart text-warning me-2"></i>New Purchase</h5>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="" id="purchaseForm">
            <div class="row g-3 mb-4">
                <div class="col-md-5">
                    <label class="form-label"><i class="fas fa-truck me-1 text-muted"></i>Supplier *</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">Select supplier...</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $s['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name']); ?> (Due: Rs.<?php echo number_format($s['balance'], 0); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-calendar me-1 text-muted"></i>Purchase Date *</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-money-bill me-1 text-muted"></i>Paid Amount (Rs.)</label>
                    <input type="number" step="1" name="paid_amount" class="form-control" value="0" min="0" id="paidAmount">
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
                        <tr>
                            <td>
                                <select name="item_product_id[]" class="form-select form-select-sm product-select" required>
                                    <option value="">Select product...</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['purchase_price']; ?>" data-unit="<?php echo $p['unit']; ?>">
                                            <?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['unit']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" name="item_quantity[]" class="form-control form-control-sm qty-input" min="0.01" required placeholder="0"></td>
                            <td><input type="number" step="1" name="item_price[]" class="form-control form-control-sm price-input" min="0" required placeholder="0"></td>
                            <td class="subtotal fw-bold">Rs.0</td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-outline-success mb-4" id="addRow"><i class="fas fa-plus me-1"></i>Add Item</button>

            <div class="row g-3 align-items-end mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Total</label>
                    <div class="form-control form-control-lg bg-light fw-bold" id="totalDisplay">Rs.0</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Due</label>
                    <div class="form-control form-control-lg bg-light fw-bold text-danger" id="dueDisplay">Rs.0</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-sticky-note me-1 text-muted"></i>Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Optional purchase notes">
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Save Purchase</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const products = <?php echo json_encode($products); ?>;
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
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
