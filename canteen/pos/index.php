<?php
$activePage = 'canteen_pos';
$pageTitle = 'POS / Billing';
include __DIR__ . '/../../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'sale') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Sale completed successfully! Receipt #' . ($_GET['rid'] ?? '') . '</div>';
if ($msg === 'insufficient') echo '<div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i>Insufficient stock for one or more items.</div>';

$products = $pdo->query("SELECT id, name, category, unit, sale_price, stock_qty AS stock FROM canteen_products WHERE status = 'active' AND stock_qty > 0 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_ids = $_POST['pos_item_id'] ?? [];
    $item_qtys = $_POST['pos_qty'] ?? [];
    $discount = (float)($_POST['pos_discount'] ?? 0);
    $payment_method = $_POST['pos_payment_method'] ?? 'cash';
    $received = (float)($_POST['pos_received'] ?? 0);
    $customer_name = trim($_POST['pos_customer'] ?? '');

    $validItems = [];
    for ($i = 0; $i < count($item_ids); $i++) {
        $pid = (int)$item_ids[$i];
        $qty = (int)$item_qtys[$i];
        if ($pid > 0 && $qty > 0) {
            $validItems[] = ['product_id' => $pid, 'quantity' => $qty];
        }
    }

    if (empty($validItems)) {
        $posError = 'Add at least one item.';
    } else {
        $pdo->beginTransaction();
        try {
            $grandTotal = 0;
            $itemsToSell = [];

            foreach ($validItems as $item) {
                $stmt = $pdo->prepare('SELECT id, name, sale_price, stock_qty AS stock, unit FROM canteen_products WHERE id = ? AND status = "active"');
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch();
                if (!$product || $product['stock'] < $item['quantity']) {
                    $pdo->rollBack();
                    header('Location: /gym/canteen/pos/index.php?msg=insufficient');
                    exit;
                }
                $lineTotal = $product['sale_price'] * $item['quantity'];
                $grandTotal += $lineTotal;
                $itemsToSell[] = [
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'unit' => $product['unit'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $product['sale_price'],
                    'subtotal' => $lineTotal,
                ];
            }

            $finalAmount = max(0, $grandTotal - $discount);

            $receiptNo = 'RCP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare('INSERT INTO canteen_sales (receipt_no, customer_name, total_amount, discount, final_amount, payment_method, received_amount, payment_date, sale_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), CURDATE(), NOW())');
            $stmt->execute([$receiptNo, $customer_name ?: null, $grandTotal, $discount, $finalAmount, $payment_method, $received]);
            $sale_id = $pdo->lastInsertId();

            foreach ($itemsToSell as $item) {
                $stmt = $pdo->prepare('INSERT INTO canteen_sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$sale_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['subtotal']]);

                $stmt = $pdo->prepare('UPDATE canteen_products SET stock_qty = stock_qty - ? WHERE id = ?');
                $stmt->execute([$item['quantity'], $item['product_id']]);

                $stmt = $pdo->prepare('INSERT INTO canteen_stock_log (product_id, type, quantity, reference_id, notes) VALUES (?, "sale", ?, ?, ?)');
                $stmt->execute([$item['product_id'], $item['quantity'], $sale_id, 'Sale ' . $receiptNo]);
            }

            $pdo->commit();
            header('Location: /gym/canteen/pos/index.php?msg=sale&rid=' . $receiptNo);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $posError = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card" style="border-top: 3px solid #f59e0b;">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-search text-warning me-2"></i>Products</h6>
                <div class="mb-3">
                    <input type="text" id="productSearch" class="form-control" placeholder="Search product by name..." oninput="filterProducts()">
                </div>
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="productsTable">
                        <thead class="table-dark"><tr><th>Name</th><th>Price</th><th>Stock</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                            <tr data-name="<?php echo strtolower(htmlspecialchars($p['name'])); ?>" class="product-row">
                                <td class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td>Rs.<?php echo number_format($p['sale_price'], 0); ?></td>
                                <td><?php echo $p['stock']; ?> <?php echo $p['unit']; ?></td>
                                <td><button type="button" class="btn btn-sm btn-warning add-to-cart" data-id="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>" data-price="<?php echo $p['sale_price']; ?>" data-unit="<?php echo $p['unit']; ?>"><i class="fas fa-plus"></i></button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card" style="border-top: 3px solid #10b981;">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-receipt text-success me-2"></i>Current Sale</h6>
                <?php if (!empty($posError)): ?>
                    <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($posError); ?></div>
                <?php endif; ?>
                <form method="POST" action="" id="posForm">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Customer Name (optional)</label>
                        <input type="text" name="pos_customer" class="form-control form-control-sm" placeholder="Walk-in customer">
                    </div>
                    <div class="table-responsive mb-3" style="max-height: 240px; overflow-y: auto;">
                        <table class="table table-sm align-middle mb-0" id="cartTable">
                            <thead><tr><th>Item</th><th style="width:70px;">Qty</th><th>Price</th><th>Total</th><th style="width:30px;"></th></tr></thead>
                            <tbody id="cartBody">
                                <tr><td colspan="5" class="text-center text-muted py-3"><i class="fas fa-cart-plus me-1"></i>Add products to start</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Discount (Rs.)</label>
                            <input type="number" step="1" name="pos_discount" id="posDiscount" class="form-control form-control-sm" value="0" min="0" oninput="recalcPOS()">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Payment Method</label>
                            <select name="pos_payment_method" class="form-select form-select-sm">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="online">Online</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Amount Received (Rs.)</label>
                        <input type="number" step="1" name="pos_received" id="posReceived" class="form-control form-control-sm" value="0" min="0" oninput="recalcPOS()">
                    </div>

                    <div class="bg-light rounded p-3 mt-3">
                        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Subtotal:</span><span class="fw-bold" id="posSubtotal">Rs.0</span></div>
                        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Discount:</span><span class="text-danger fw-bold" id="posDiscDisplay">- Rs.0</span></div>
                        <div class="d-flex justify-content-between mb-1"><span class="text-muted fw-bold fs-5">Total:</span><span class="fw-bold fs-5 text-success" id="posTotal">Rs.0</span></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Change:</span><span class="fw-bold text-primary" id="posChange">Rs.0</span></div>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg fw-bold w-100 mt-3" id="checkoutBtn" disabled><i class="fas fa-check-circle me-1"></i>Complete Sale</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];

function filterProducts() {
    const q = document.getElementById('productSearch').value.toLowerCase();
    document.querySelectorAll('.product-row').forEach(function(r) {
        r.style.display = r.getAttribute('data-name').includes(q) ? '' : 'none';
    });
}

document.querySelectorAll('.add-to-cart').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const id = parseInt(this.getAttribute('data-id'));
        const existing = cart.find(function(c) { return c.id === id; });
        if (existing) {
            existing.qty++;
        } else {
            cart.push({
                id: id,
                name: this.getAttribute('data-name'),
                price: parseFloat(this.getAttribute('data-price')),
                unit: this.getAttribute('data-unit'),
                qty: 1
            });
        }
        renderCart();
    });
});

function renderCart() {
    const tbody = document.getElementById('cartBody');
    if (cart.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><i class="fas fa-cart-plus me-1"></i>Add products to start</td></tr>';
    } else {
        tbody.innerHTML = '';
        cart.forEach(function(item, index) {
            const total = item.price * item.qty;
            tbody.innerHTML += '<tr>' +
                '<td class="fw-semibold small">' + item.name + '</td>' +
                '<td><input type="number" min="1" value="' + item.qty + '" class="form-control form-control-sm text-center" style="width:60px;" onchange="updateQty(' + index + ', this.value)"></td>' +
                '<td class="small">Rs.' + item.price.toLocaleString() + '</td>' +
                '<td class="fw-bold small">Rs.' + Math.round(total).toLocaleString() + '</td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="removeItem(' + index + ')"><i class="fas fa-times"></i></button></td>' +
            '</tr>';
        });
    }
    recalcPOS();
}

function updateQty(index, val) {
    const q = parseInt(val);
    if (q > 0) { cart[index].qty = q; } else { cart.splice(index, 1); }
    renderCart();
}

function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

function recalcPOS() {
    let subtotal = 0;
    cart.forEach(function(item) { subtotal += item.price * item.qty; });
    const discount = parseFloat(document.getElementById('posDiscount').value) || 0;
    const total = Math.max(0, subtotal - discount);
    const received = parseFloat(document.getElementById('posReceived').value) || 0;
    const change = Math.max(0, received - total);

    document.getElementById('posSubtotal').textContent = 'Rs.' + Math.round(subtotal).toLocaleString();
    document.getElementById('posDiscDisplay').textContent = '- Rs.' + Math.round(discount).toLocaleString();
    document.getElementById('posTotal').textContent = 'Rs.' + Math.round(total).toLocaleString();
    document.getElementById('posChange').textContent = 'Rs.' + Math.round(change).toLocaleString();

    document.getElementById('checkoutBtn').disabled = cart.length === 0 || total <= 0;
}

document.getElementById('posForm').addEventListener('submit', function(e) {
    const form = this;
    cart.forEach(function(item) {
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'pos_item_id[]';
        idInput.value = item.id;
        form.appendChild(idInput);

        const qtyInput = document.createElement('input');
        qtyInput.type = 'hidden';
        qtyInput.name = 'pos_qty[]';
        qtyInput.value = item.qty;
        form.appendChild(qtyInput);
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
