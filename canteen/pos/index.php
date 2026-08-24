<?php
$activePage = 'canteen_pos';
$pageTitle = 'POS / Billing';
include __DIR__ . '/../../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'sale') echo '<div class="alert alert-success py-2 d-flex justify-content-between align-items-center"><span><i class="fas fa-check-circle me-1"></i>Sale completed successfully! Receipt #' . htmlspecialchars($_GET['rid'] ?? '') . '</span><a href="/gym/canteen/sales/" class="btn btn-sm btn-outline-success fw-bold"><i class="fas fa-receipt me-1"></i>View Sales</a></div>';
if ($msg === 'insufficient') echo '<div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i>Insufficient stock for one or more items.</div>';

$products = $pdo->query("SELECT id, name, category, unit, sale_price, stock_qty AS stock FROM canteen_products WHERE status = 'active' AND stock_qty > 0 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_ids = $_POST['pos_item_id'] ?? [];
    $item_qtys = $_POST['pos_qty'] ?? [];
    $discount = (float)($_POST['pos_discount'] ?? 0);
    $payment_method = $_POST['pos_payment_method'] ?? 'cash';
    $received = (float)($_POST['pos_received'] ?? 0);
    $member_id = (int)($_POST['pos_member_id'] ?? 0);
    $walkin_name = trim($_POST['pos_customer'] ?? '');

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

            $customerName = null;
            if ($member_id > 0) {
                $stmt = $pdo->prepare('SELECT name FROM members WHERE id = ?');
                $stmt->execute([$member_id]);
                $mrow = $stmt->fetch();
                if ($mrow) {
                    $customerName = $mrow['name'];
                } else {
                    $member_id = 0;
                }
            }
            if ($customerName === null && $walkin_name !== '') {
                $customerName = $walkin_name;
            }

            $receiptNo = 'RCP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare('INSERT INTO canteen_sales (member_id, receipt_no, customer_name, total_amount, discount, final_amount, payment_method, received_amount, payment_date, sale_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURDATE(), NOW())');
            $stmt->execute([$member_id > 0 ? $member_id : null, $receiptNo, $customerName, $grandTotal, $discount, $finalAmount, $payment_method, $received]);
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
                        <label class="form-label small fw-bold">Customer Type</label>
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check" name="customer_type" value="member" id="ctMember" checked onchange="toggleCustomerType()">
                            <label class="btn btn-outline-primary" for="ctMember"><i class="fas fa-user-tag me-1"></i>Member</label>
                            <input type="radio" class="btn-check" name="customer_type" value="walkin" id="ctWalkin" onchange="toggleCustomerType()">
                            <label class="btn btn-outline-warning" for="ctWalkin"><i class="fas fa-walking me-1"></i>Walk-in</label>
                        </div>
                    </div>
                    <div class="mb-2 position-relative" id="memberFieldWrap">
                        <label class="form-label small fw-bold">Select Member</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="memberSearch" class="form-control" placeholder="Type name or phone to search members..." autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="memberClear" title="Clear" style="display:none;"><i class="fas fa-times"></i></button>
                        </div>
                        <input type="hidden" name="pos_member_id" id="posMemberId" value="0">
                        <div id="memberResults" class="list-group position-absolute w-100 shadow" style="z-index:1050; display:none; max-height:220px; overflow-y:auto;"></div>
                        <small class="text-muted">Start typing — matching members will appear.</small>
                    </div>
                    <div class="mb-2" id="walkinFieldWrap" style="display:none;">
                        <label class="form-label small fw-bold">Customer Name (optional)</label>
                        <input type="text" id="walkinName" class="form-control form-control-sm" placeholder="e.g. random customer ka naam..." autocomplete="off">
                        <small class="text-muted">Leave empty for anonymous walk-in.</small>
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

const memberInput = document.getElementById('memberSearch');
const memberResults = document.getElementById('memberResults');
const memberIdInput = document.getElementById('posMemberId');
let memberTimer = null;

memberInput.addEventListener('input', function() {
    const q = this.value.trim();
    clearTimeout(memberTimer);
    if (q.length < 1) {
        memberResults.style.display = 'none';
        memberResults.innerHTML = '';
        return;
    }
    memberTimer = setTimeout(function() {
        fetch('/gym/canteen/pos/search_member.php?q=' + encodeURIComponent(q))
            .then(function(r) { return r.json(); })
            .then(function(members) {
                memberResults.innerHTML = '';
                if (members.length === 0) {
                    memberResults.innerHTML = '<span class="list-group-item small text-muted"><i class="fas fa-user-slash me-1"></i>No members found</span>';
                } else {
                    members.forEach(function(m) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action small py-1';
                        btn.innerHTML = '<i class="fas fa-user me-2 text-muted"></i>' + m.name + ' <small class="text-muted">(' + (m.phone || '-') + ')</small>';
                        btn.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            memberIdInput.value = m.id;
                            memberInput.value = m.name;
                            document.getElementById('memberClear').style.display = '';
                            memberResults.style.display = 'none';
                        });
                        memberResults.appendChild(btn);
                    });
                }
                memberResults.style.display = 'block';
            });
    }, 250);
});

document.getElementById('memberClear').addEventListener('click', function() {
    memberIdInput.value = 0;
    memberInput.value = '';
    this.style.display = 'none';
});

function toggleCustomerType() {
    const isWalkin = document.getElementById('ctWalkin').checked;
    document.getElementById('walkinFieldWrap').style.display = isWalkin ? '' : 'none';
    document.getElementById('memberFieldWrap').style.display = isWalkin ? 'none' : '';
    if (isWalkin) {
        memberIdInput.value = 0;
        memberInput.value = '';
        document.getElementById('memberClear').style.display = 'none';
        memberResults.style.display = 'none';
    } else {
        document.getElementById('walkinName').value = '';
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#memberResults') && e.target !== memberInput) {
        memberResults.style.display = 'none';
    }
});

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

    if (document.getElementById('ctWalkin').checked) {
        const cName = document.getElementById('walkinName').value.trim();
        if (cName !== '') {
            const cInput = document.createElement('input');
            cInput.type = 'hidden';
            cInput.name = 'pos_customer';
            cInput.value = cName;
            form.appendChild(cInput);
        }
        memberIdInput.value = 0;
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
