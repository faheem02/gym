<?php
$activePage = 'canteen_sales';
$pageTitle  = 'Edit Sale';
include __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /gym/canteen/sales/'); exit; }

// Load sale
$stmt = $pdo->prepare("SELECT s.*, m.name AS member_name FROM canteen_sales s LEFT JOIN members m ON m.id = s.member_id WHERE s.id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale) { header('Location: /gym/canteen/sales/'); exit; }

// All active products for the dropdown
$allProducts = $pdo->query("SELECT id, name, unit, sale_price, stock_qty FROM canteen_products WHERE status = 'active' ORDER BY name")->fetchAll();

$errors  = [];
$success = false;

// -----------------------------------------------------------
// POST handler
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Sale-level fields ---
    $customerName   = trim($_POST['customer_name']   ?? '');
    $saleDate       = trim($_POST['sale_date']        ?? '');
    $discount       = max(0, (float)($_POST['discount']         ?? 0));
    $paymentMethod  = trim($_POST['payment_method']   ?? 'cash');
    $receivedAmount = max(0, (float)($_POST['received_amount']  ?? 0));
    $notes          = trim($_POST['notes']            ?? '');

    // --- Item arrays from form ---
    $itemIds      = $_POST['item_id']       ?? [];   // canteen_sale_items.id  (0 = new row)
    $productIds   = $_POST['product_id']    ?? [];
    $quantities   = $_POST['quantity']      ?? [];
    $unitPrices   = $_POST['unit_price']    ?? [];

    $allowedMethods = ['cash','card','online','easypaisa','jazzcash'];
    if (!$saleDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $saleDate)) $errors[] = 'Valid sale date is required.';
    if (!in_array($paymentMethod, $allowedMethods))                      $errors[] = 'Invalid payment method.';
    if (empty($productIds))                                              $errors[] = 'At least one item is required.';

    // Build & validate items
    $newItems = [];
    if (empty($errors)) {
        foreach ($productIds as $k => $pid) {
            $pid = (int)$pid;
            $qty = max(1, (int)($quantities[$k]  ?? 1));
            $up  = max(0, (float)($unitPrices[$k] ?? 0));
            if ($pid <= 0) continue;

            // Verify product exists
            $ps = $pdo->prepare("SELECT id, name, unit, sale_price, stock_qty FROM canteen_products WHERE id = ?");
            $ps->execute([$pid]);
            $prod = $ps->fetch();
            if (!$prod) { $errors[] = "Product #$pid not found."; break; }

            $newItems[] = [
                'item_id'    => (int)($itemIds[$k] ?? 0),   // existing row id
                'product_id' => $pid,
                'quantity'   => $qty,
                'unit_price' => $up ?: (float)$prod['sale_price'],
                'subtotal'   => ($up ?: (float)$prod['sale_price']) * $qty,
                'stock_qty'  => (int)$prod['stock_qty'],
                'name'       => $prod['name'],
            ];
        }
    }

    if (empty($errors) && empty($newItems)) {
        $errors[] = 'At least one valid item is required.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // --- Step 1: load OLD items so we can reverse their stock ---
            $oldStmt = $pdo->prepare("SELECT * FROM canteen_sale_items WHERE sale_id = ?");
            $oldStmt->execute([$id]);
            $oldItems = $oldStmt->fetchAll();

            // Build lookup: product_id => old_qty (to restore stock)
            $oldQtyByProduct = [];
            foreach ($oldItems as $oi) {
                $oldQtyByProduct[(int)$oi['product_id']] = (int)$oi['quantity'];
            }

            // Build lookup: new qty per product
            $newQtyByProduct = [];
            foreach ($newItems as $ni) {
                $pid = $ni['product_id'];
                $newQtyByProduct[$pid] = ($newQtyByProduct[$pid] ?? 0) + $ni['quantity'];
            }

            // --- Step 2: stock validation ---
            // For each product in new items: available = current_stock + old_qty_sold - new_qty_needed
            foreach ($newQtyByProduct as $pid => $newQty) {
                $stockStmt = $pdo->prepare("SELECT stock_qty FROM canteen_products WHERE id = ?");
                $stockStmt->execute([$pid]);
                $currentStock = (int)$stockStmt->fetchColumn();
                $oldQty = $oldQtyByProduct[$pid] ?? 0;
                $available = $currentStock + $oldQty; // restore old, then apply new
                if ($newQty > $available) {
                    $nameStmt = $pdo->prepare("SELECT name FROM canteen_products WHERE id = ?");
                    $nameStmt->execute([$pid]);
                    $pname = $nameStmt->fetchColumn();
                    throw new Exception("Insufficient stock for \"$pname\". Available: $available, Requested: $newQty.");
                }
            }

            // --- Step 3: restore old stock (undo original sale) ---
            foreach ($oldItems as $oi) {
                $pdo->prepare("UPDATE canteen_products SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$oi['quantity'], $oi['product_id']]);
            }

            // --- Step 4: delete all old sale items & their stock log entries ---
            $pdo->prepare("DELETE FROM canteen_sale_items WHERE sale_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM canteen_stock_log WHERE reference_id = ? AND type = 'sale'")->execute([$id]);

            // --- Step 5: insert new items & deduct stock ---
            $totalAmount = 0;
            foreach ($newItems as $ni) {
                $pdo->prepare("INSERT INTO canteen_sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?,?,?,?,?)")
                    ->execute([$id, $ni['product_id'], $ni['quantity'], $ni['unit_price'], $ni['subtotal']]);

                $pdo->prepare("UPDATE canteen_products SET stock_qty = stock_qty - ? WHERE id = ?")
                    ->execute([$ni['quantity'], $ni['product_id']]);

                $pdo->prepare("INSERT INTO canteen_stock_log (product_id, type, quantity, reference_id, notes) VALUES (?, 'sale', ?, ?, ?)")
                    ->execute([$ni['product_id'], $ni['quantity'], $id, 'Sale ' . $sale['receipt_no']]);

                $totalAmount += $ni['subtotal'];
            }

            // --- Step 6: update sale header ---
            $finalAmount = max(0, $totalAmount - $discount);
            $pdo->prepare("
                UPDATE canteen_sales
                SET customer_name=?, sale_date=?, total_amount=?, discount=?, final_amount=?,
                    payment_method=?, received_amount=?, payment_date=?, notes=?
                WHERE id=?
            ")->execute([
                $customerName ?: null,
                $saleDate,
                $totalAmount,
                $discount,
                $finalAmount,
                $paymentMethod,
                $receivedAmount,
                $saleDate,
                $notes ?: null,
                $id,
            ]);

            $pdo->commit();

            // Redirect to view page so back button works correctly
            header('Location: /gym/canteen/sales/view.php?id=' . $id . '&updated=1');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

// Load current items (after possible save)
$stmt = $pdo->prepare("SELECT si.*, cp.name AS product_name, cp.unit FROM canteen_sale_items si LEFT JOIN canteen_products cp ON cp.id = si.product_id WHERE si.sale_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$methodMeta = [
    'cash'      => 'Cash',
    'card'      => 'Card',
    'online'    => 'Online',
    'easypaisa' => 'EasyPaisa',
    'jazzcash'  => 'JazzCash',
];

// Build product map for JS (id => {name, sale_price, unit})
$productMap = [];
foreach ($allProducts as $p) {
    $productMap[$p['id']] = ['name' => $p['name'], 'price' => (float)$p['sale_price'], 'unit' => $p['unit'], 'stock' => (int)$p['stock_qty']];
}
?>

<!-- Top nav -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="if(window.opener||window.history.length<=1){window.close();}else{window.location='/gym/canteen/sales/view.php?id=<?php echo $id; ?>';}">
        <i class="fas fa-arrow-left me-1"></i>Back to Invoice
    </button>
    <a href="/gym/canteen/sales/" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-list me-1"></i>All Sales
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle me-1"></i>Sale updated successfully.</div>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($e); ?></div>
<?php endforeach; ?>

<div class="row justify-content-center">
<div class="col-lg-10">
<form method="POST" id="editForm">
<div class="card" style="border-top:3px solid #f7b731;">
    <div class="card-header d-flex align-items-center justify-content-between" style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
        <h6 class="fw-bold mb-0">
            <i class="fas fa-edit me-2" style="color:#f7b731;"></i>
            Edit Sale — <?php echo htmlspecialchars($sale['receipt_no'] ?? ('SALE-' . $id)); ?>
        </h6>
        <span class="badge" style="background:#f7b731;color:#1a1a2e;"><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></span>
    </div>
    <div class="card-body">

        <!-- ============ SECTION 1: ITEMS ============ -->
        <h6 class="fw-bold mb-3"><i class="fas fa-box-open me-2 text-warning"></i>Sale Items</h6>

        <div class="table-responsive mb-2">
            <table class="table table-sm align-middle" id="itemsTable">
                <thead class="table-dark">
                    <tr>
                        <th style="width:35%">Product</th>
                        <th style="width:14%">Qty</th>
                        <th style="width:18%">Unit Price (Rs.)</th>
                        <th style="width:18%" class="text-end">Subtotal</th>
                        <th style="width:8%"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <?php foreach ($items as $i => $it): ?>
                    <tr class="item-row">
                        <td>
                            <input type="hidden" name="item_id[]" value="<?php echo $it['id']; ?>">
                            <select name="product_id[]" class="form-select form-select-sm product-select" required>
                                <option value="">— Select Product —</option>
                                <?php foreach ($allProducts as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"
                                        data-price="<?php echo $p['sale_price']; ?>"
                                        data-unit="<?php echo htmlspecialchars($p['unit']); ?>"
                                        <?php echo (int)$p['id'] === (int)$it['product_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name']); ?> (Stock: <?php echo $p['stock_qty']; ?>)
                                    </option>
                                <?php endforeach; ?>
                                <?php
                                // If product was deleted, keep it selectable with original name
                                if (!empty($it['product_id']) && !array_filter($allProducts, fn($p) => (int)$p['id'] === (int)$it['product_id'])):
                                ?>
                                    <option value="<?php echo $it['product_id']; ?>" selected>
                                        <?php echo htmlspecialchars($it['product_name'] ?? 'Deleted Product #'.$it['product_id']); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </td>
                        <td>
                            <input type="number" name="quantity[]" class="form-control form-control-sm qty-input"
                                   value="<?php echo $it['quantity']; ?>" min="1" required style="width:80px;">
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" name="unit_price[]" class="form-control form-control-sm price-input"
                                       value="<?php echo number_format($it['unit_price'], 2, '.', ''); ?>" min="0" step="0.01" required>
                            </div>
                        </td>
                        <td class="text-end fw-bold subtotal-cell">
                            Rs.<?php echo number_format($it['subtotal'], 2); ?>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove item">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="button" id="addRowBtn" class="btn btn-sm fw-bold mb-4"
                style="background:linear-gradient(135deg,#00b894,#55efc4);color:#fff;border:none;border-radius:50px;padding:6px 18px;">
            <i class="fas fa-plus me-1"></i>Add Product
        </button>

        <hr>

        <!-- ============ SECTION 2: SALE DETAILS ============ -->
        <h6 class="fw-bold mb-3 mt-3"><i class="fas fa-file-invoice me-2 text-warning"></i>Sale Details</h6>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Customer Name</label>
                <input type="text" name="customer_name" class="form-control"
                       value="<?php echo htmlspecialchars($sale['member_id'] ? ($sale['member_name'] ?? '') : ($sale['customer_name'] ?? '')); ?>"
                       <?php echo $sale['member_id'] ? 'readonly' : ''; ?>>
                <?php if ($sale['member_id']): ?>
                    <small class="text-muted">Linked to member — name is read-only.</small>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Sale Date</label>
                <input type="date" name="sale_date" class="form-control" required
                       value="<?php echo htmlspecialchars($sale['sale_date']); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Discount (Rs.)</label>
                <div class="input-group">
                    <span class="input-group-text">Rs.</span>
                    <input type="number" name="discount" id="discountInput" step="0.01" min="0"
                           class="form-control" value="<?php echo number_format($sale['discount'], 2, '.', ''); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Payment Method</label>
                <select name="payment_method" class="form-select">
                    <?php foreach ($methodMeta as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $sale['payment_method'] === $val ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Amount Received (Rs.)</label>
                <div class="input-group">
                    <span class="input-group-text">Rs.</span>
                    <input type="number" name="received_amount" step="0.01" min="0"
                           class="form-control" value="<?php echo number_format($sale['received_amount'], 2, '.', ''); ?>">
                </div>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."><?php echo htmlspecialchars($sale['notes'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- ============ TOTALS SUMMARY ============ -->
        <div class="row justify-content-end mt-4">
            <div class="col-md-5">
                <div class="bg-light rounded p-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Items Total:</span>
                        <span class="fw-bold" id="summarySubtotal">Rs.0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Discount:</span>
                        <span class="text-danger fw-bold" id="summaryDiscount">- Rs.0.00</span>
                    </div>
                    <div class="d-flex justify-content-between pt-2 border-top">
                        <span class="fw-bold fs-6">Net Total:</span>
                        <span class="fw-bold fs-6 text-success" id="summaryTotal">Rs.0.00</span>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- card-body -->

    <div class="card-footer d-flex justify-content-between align-items-center">
        <a href="/gym/canteen/sales/view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-times me-1"></i>Cancel
        </a>
        <button type="submit" class="btn fw-bold px-5"
                style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#1a1a2e;border:none;border-radius:50px;">
            <i class="fas fa-save me-1"></i>Save Changes
        </button>
    </div>
</div>
</form>
</div><!-- col -->
</div><!-- row -->

<!-- Empty row template (hidden) -->
<template id="newRowTemplate">
    <tr class="item-row">
        <td>
            <input type="hidden" name="item_id[]" value="0">
            <select name="product_id[]" class="form-select form-select-sm product-select" required>
                <option value="">— Select Product —</option>
                <?php foreach ($allProducts as $p): ?>
                    <option value="<?php echo $p['id']; ?>"
                        data-price="<?php echo $p['sale_price']; ?>"
                        data-unit="<?php echo htmlspecialchars($p['unit']); ?>">
                        <?php echo htmlspecialchars($p['name']); ?> (Stock: <?php echo $p['stock_qty']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="number" name="quantity[]" class="form-control form-control-sm qty-input"
                   value="1" min="1" required style="width:80px;">
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text">Rs.</span>
                <input type="number" name="unit_price[]" class="form-control form-control-sm price-input"
                       value="0" min="0" step="0.01" required>
            </div>
        </td>
        <td class="text-end fw-bold subtotal-cell">Rs.0.00</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove item">
                <i class="fas fa-times"></i>
            </button>
        </td>
    </tr>
</template>

<script>
(function () {
    const tbody       = document.getElementById('itemsBody');
    const addRowBtn   = document.getElementById('addRowBtn');
    const discInput   = document.getElementById('discountInput');
    const template    = document.getElementById('newRowTemplate');

    // Auto-fill price when product changes
    function onProductChange(select) {
        const opt   = select.options[select.selectedIndex];
        const price = opt.getAttribute('data-price') || 0;
        const row   = select.closest('.item-row');
        row.querySelector('.price-input').value = parseFloat(price).toFixed(2);
        recalcRow(row);
    }

    // Recalc one row subtotal
    function recalcRow(row) {
        const qty   = parseFloat(row.querySelector('.qty-input').value)   || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const sub   = qty * price;
        row.querySelector('.subtotal-cell').textContent = 'Rs.' + sub.toFixed(2);
        recalcTotals();
    }

    // Recalc grand totals
    function recalcTotals() {
        let subtotal = 0;
        document.querySelectorAll('.subtotal-cell').forEach(function (cell) {
            subtotal += parseFloat(cell.textContent.replace('Rs.', '')) || 0;
        });
        const discount = parseFloat(discInput.value) || 0;
        const net      = Math.max(0, subtotal - discount);

        document.getElementById('summarySubtotal').textContent = 'Rs.' + subtotal.toFixed(2);
        document.getElementById('summaryDiscount').textContent = '- Rs.' + discount.toFixed(2);
        document.getElementById('summaryTotal').textContent    = 'Rs.' + net.toFixed(2);
    }

    // Attach listeners to a row
    function bindRow(row) {
        const sel   = row.querySelector('.product-select');
        const qty   = row.querySelector('.qty-input');
        const price = row.querySelector('.price-input');
        const del   = row.querySelector('.remove-row');

        sel.addEventListener('change', function () { onProductChange(sel); });
        qty.addEventListener('input',  function () { recalcRow(row); });
        price.addEventListener('input',function () { recalcRow(row); });
        del.addEventListener('click',  function () {
            if (tbody.querySelectorAll('.item-row').length <= 1) {
                alert('At least one item is required.');
                return;
            }
            row.remove();
            recalcTotals();
        });
    }

    // Bind existing rows
    tbody.querySelectorAll('.item-row').forEach(bindRow);

    // Add row button
    addRowBtn.addEventListener('click', function () {
        const clone = template.content.cloneNode(true);
        const row   = clone.querySelector('tr');
        tbody.appendChild(clone);
        bindRow(tbody.lastElementChild);
        tbody.lastElementChild.querySelector('.product-select').focus();
    });

    // Discount changes
    discInput.addEventListener('input', recalcTotals);

    // Init totals on page load
    recalcTotals();
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
