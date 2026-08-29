<?php
$activePage = 'canteen_pos';
$pageTitle = 'POS / Billing';
include __DIR__ . '/../../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'sale') {
    $saleIdParam = (int)($_GET['id'] ?? 0);
    $ridParam = htmlspecialchars($_GET['rid'] ?? '');
    echo '<div class="alert alert-success py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">';
    echo '<span><i class="fas fa-check-circle me-1"></i>Sale completed successfully! Receipt #' . $ridParam . '</span>';
    echo '<div class="d-flex gap-2">';
    if ($saleIdParam > 0) {
        echo '<a href="/gym/canteen/sales/thermal_receipt.php?id=' . $saleIdParam . '&autoprint=1" class="btn btn-sm btn-dark fw-bold"><i class="fas fa-print me-1"></i>Print Receipt</a>';
    }
    echo '<a href="/gym/canteen/sales/" class="btn btn-sm btn-outline-success fw-bold"><i class="fas fa-receipt me-1"></i>View Sales</a>';
    echo '</div></div>';
}
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
            header('Location: /gym/canteen/sales/thermal_receipt.php?id=' . $sale_id . '&autoprint=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $posError = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<style>
.pos-product-card {
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    border: 1.5px solid #e5e7eb;
    border-radius: 12px;
    background: #ffffff;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 12px 10px;
    user-select: none;
    position: relative;
    overflow: hidden;
}
.pos-product-card:hover {
    border-color: #f59e0b;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.15);
}
.pos-product-card:active {
    transform: scale(0.97);
}
.pos-product-card .product-title {
    font-weight: 700;
    font-size: 0.9rem;
    color: #1f2937;
    line-height: 1.25;
    min-height: 2.3em;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 4px;
}
.pos-product-card .product-cat {
    font-size: 0.7rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 4px;
}
.pos-product-card .price-tag {
    font-size: 1rem;
    font-weight: 800;
    color: #059669;
}
.pos-product-card .stock-tag {
    font-size: 0.72rem;
    padding: 2px 6px;
    border-radius: 6px;
}
.category-pill {
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
    background: #f3f4f6;
    color: #4b5563;
    border: 1px solid #e5e7eb;
    transition: all 0.15s;
    white-space: nowrap;
}
.category-pill:hover, .category-pill.active {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff;
    border-color: #f59e0b;
}
.qty-btn {
    width: 24px;
    height: 24px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    font-size: 11px;
}
</style>

<?php
$categories = array_unique(array_filter(array_column($products, 'category')));
sort($categories);
?>

<div class="row g-3">
    <!-- Left Column: Product Selection Grid -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100" style="border-top: 3px solid #f59e0b;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0"><i class="fas fa-th-large text-warning me-2"></i>Select Products</h6>
                    <span class="badge bg-light text-dark border"><?php echo count($products); ?> Items Available</span>
                </div>

                <!-- Search & Category Filters -->
                <div class="mb-2">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="productSearch" class="form-control" placeholder="Search product name..." oninput="filterProducts()" autocomplete="off" spellcheck="false">
                        <button type="button" class="btn btn-outline-secondary" id="clearProdSearch" style="display:none;" onclick="clearProductSearch()"><i class="fas fa-times"></i></button>
                    </div>
                </div>

                <!-- Category Pills -->
                <?php if (!empty($categories)): ?>
                <div class="d-flex gap-1 overflow-auto pb-2 mb-2" style="scrollbar-width: thin;">
                    <span class="category-pill active" data-cat="all" onclick="filterCategory('all', this)">All Items</span>
                    <?php foreach ($categories as $cat): ?>
                        <span class="category-pill" data-cat="<?php echo htmlspecialchars(strtolower($cat)); ?>" onclick="filterCategory('<?php echo htmlspecialchars(strtolower($cat)); ?>', this)">
                            <?php echo htmlspecialchars($cat); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Product Cards Grid -->
                <div class="overflow-auto pe-1" style="max-height: 520px;" id="productsGridWrap">
                    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-3 row-cols-xl-3 g-2" id="productsGrid">
                        <?php if (empty($products)): ?>
                            <div class="col-12 text-center text-muted py-5">
                                <i class="fas fa-box-open fa-2x mb-2 text-warning"></i>
                                <p>No active products with stock available.</p>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($products as $p): ?>
                        <div class="col product-col" data-name="<?php echo strtolower(htmlspecialchars($p['name'])); ?>" data-cat="<?php echo strtolower(htmlspecialchars($p['category'] ?? '')); ?>">
                            <div class="pos-product-card add-to-cart" data-id="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>" data-price="<?php echo $p['sale_price']; ?>" data-unit="<?php echo htmlspecialchars($p['unit']); ?>" data-stock="<?php echo $p['stock']; ?>">
                                <div>
                                    <?php if (!empty($p['category'])): ?>
                                        <div class="product-cat"><?php echo htmlspecialchars($p['category']); ?></div>
                                    <?php endif; ?>
                                    <div class="product-title" title="<?php echo htmlspecialchars($p['name']); ?>">
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-end mt-2 pt-2 border-top">
                                    <div class="price-tag">Rs.<?php echo number_format($p['sale_price'], 0); ?></div>
                                    <div class="stock-tag <?php echo $p['stock'] <= 5 ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success-emphasis'; ?>">
                                        <i class="fas fa-layer-group me-1"></i><?php echo $p['stock']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Current Sale / Cart -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100" style="border-top: 3px solid #10b981;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0"><i class="fas fa-shopping-cart text-success me-2"></i>Current Sale</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger py-0" id="clearCartBtn" style="display:none;" onclick="clearEntireCart()"><i class="fas fa-trash me-1"></i>Clear</button>
                </div>

                <?php if (!empty($posError)): ?>
                    <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($posError); ?></div>
                <?php endif; ?>

                <form method="POST" action="" id="posForm">
                    <!-- Customer Selection -->
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1">Customer Type</label>
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check" name="customer_type" value="member" id="ctMember" checked onchange="toggleCustomerType()">
                            <label class="btn btn-outline-primary" for="ctMember"><i class="fas fa-user-tag me-1"></i>Member</label>
                            <input type="radio" class="btn-check" name="customer_type" value="walkin" id="ctWalkin" onchange="toggleCustomerType()">
                            <label class="btn btn-outline-warning" for="ctWalkin"><i class="fas fa-walking me-1"></i>Walk-in</label>
                        </div>
                    </div>

                    <div class="mb-2 position-relative" id="memberFieldWrap">
                        <label class="form-label small fw-bold mb-1">Search Member</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" id="memberSearch" class="form-control" placeholder="Type member name or phone..." autocomplete="off" spellcheck="false">
                            <button type="button" class="btn btn-outline-secondary" id="memberClear" title="Clear" style="display:none;"><i class="fas fa-times"></i></button>
                        </div>
                        <input type="hidden" name="pos_member_id" id="posMemberId" value="0">
                        <div id="memberResults" class="list-group position-absolute w-100 shadow" style="z-index:1050; display:none; max-height:200px; overflow-y:auto; border-radius:6px;"></div>
                    </div>

                    <div class="mb-2" id="walkinFieldWrap" style="display:none;">
                        <label class="form-label small fw-bold mb-1">Customer Name (Optional)</label>
                        <input type="text" id="walkinName" class="form-control form-control-sm" placeholder="e.g. Walk-in customer name..." autocomplete="off">
                    </div>

                    <!-- Cart Items Table -->
                    <div class="table-responsive mb-2" style="max-height: 230px; min-height: 120px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <table class="table table-sm align-middle mb-0" id="cartTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center" style="width:105px;">Qty</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Total</th>
                                    <th style="width:25px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cartBody">
                                <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-cart-plus me-1"></i>Click product cards on the left to add</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Payment Controls -->
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Discount (Rs.)</label>
                            <input type="number" step="1" name="pos_discount" id="posDiscount" class="form-control form-control-sm" value="0" min="0" oninput="recalcPOS()">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Payment Method</label>
                            <select name="pos_payment_method" class="form-select form-select-sm">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="online">Online</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1">Amount Received (Rs.)</label>
                        <input type="number" step="1" name="pos_received" id="posReceived" class="form-control form-control-sm" value="0" min="0" oninput="recalcPOS()">
                    </div>

                    <!-- Summary Card -->
                    <div class="bg-light rounded p-2.5 mt-2 border">
                        <div class="d-flex justify-content-between mb-1 small"><span class="text-muted">Subtotal:</span><span class="fw-bold" id="posSubtotal">Rs.0</span></div>
                        <div class="d-flex justify-content-between mb-1 small"><span class="text-muted">Discount:</span><span class="text-danger fw-bold" id="posDiscDisplay">- Rs.0</span></div>
                        <div class="d-flex justify-content-between mb-1"><span class="fw-bold fs-6">Net Payable:</span><span class="fw-bold fs-5 text-success" id="posTotal">Rs.0</span></div>
                        <div class="d-flex justify-content-between small"><span class="text-muted">Change:</span><span class="fw-bold text-primary" id="posChange">Rs.0</span></div>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg fw-bold w-100 mt-2.5 shadow-sm" id="checkoutBtn" disabled>
                        <i class="fas fa-print me-1"></i>Complete &amp; Print Sale
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];
let currentCategory = 'all';

// Product Filtering
function filterProducts() {
    const q = document.getElementById('productSearch').value.trim().toLowerCase();
    const clearBtn = document.getElementById('clearProdSearch');
    clearBtn.style.display = q.length > 0 ? 'inline-block' : 'none';

    document.querySelectorAll('.product-col').forEach(function(col) {
        const nameMatch = col.getAttribute('data-name').includes(q);
        const catMatch = (currentCategory === 'all' || col.getAttribute('data-cat') === currentCategory);
        col.style.display = (nameMatch && catMatch) ? '' : 'none';
    });
}

function clearProductSearch() {
    document.getElementById('productSearch').value = '';
    filterProducts();
    document.getElementById('productSearch').focus();
}

function filterCategory(cat, el) {
    currentCategory = cat;
    document.querySelectorAll('.category-pill').forEach(function(p) { p.classList.remove('active'); });
    el.classList.add('active');
    filterProducts();
}

// Add to cart on product tile click
document.querySelectorAll('.add-to-cart').forEach(function(card) {
    card.addEventListener('click', function() {
        const id = parseInt(this.getAttribute('data-id'));
        const name = this.getAttribute('data-name');
        const price = parseFloat(this.getAttribute('data-price'));
        const unit = this.getAttribute('data-unit');
        const stock = parseInt(this.getAttribute('data-stock'));

        const existing = cart.find(function(c) { return c.id === id; });
        if (existing) {
            if (existing.qty < stock) {
                existing.qty++;
            } else {
                alert('Maximum available stock for ' + name + ' is ' + stock);
            }
        } else {
            cart.push({
                id: id,
                name: name,
                price: price,
                unit: unit,
                stock: stock,
                qty: 1
            });
        }
        renderCart();
    });
});

function renderCart() {
    const tbody = document.getElementById('cartBody');
    const clearCartBtn = document.getElementById('clearCartBtn');

    if (cart.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-cart-plus me-1"></i>Click product cards on the left to add</td></tr>';
        clearCartBtn.style.display = 'none';
    } else {
        tbody.innerHTML = '';
        clearCartBtn.style.display = 'inline-block';
        cart.forEach(function(item, index) {
            const total = item.price * item.qty;
            tbody.innerHTML += '<tr class="cart-item-row">' +
                '<td class="fw-semibold small py-1.5">' + escapeHtml(item.name) + '</td>' +
                '<td class="text-center py-1.5">' +
                    '<div class="d-inline-flex align-items-center gap-1">' +
                        '<button type="button" class="btn btn-outline-secondary qty-btn" onclick="stepQty(' + index + ', -1)"><i class="fas fa-minus"></i></button>' +
                        '<input type="number" min="1" max="' + item.stock + '" value="' + item.qty + '" class="form-control form-control-sm text-center px-1" style="width:38px;height:24px;font-size:12px;" onchange="updateQty(' + index + ', this.value)">' +
                        '<button type="button" class="btn btn-outline-secondary qty-btn" onclick="stepQty(' + index + ', 1)"><i class="fas fa-plus"></i></button>' +
                    '</div>' +
                '</td>' +
                '<td class="text-end small py-1.5">Rs.' + item.price.toLocaleString() + '</td>' +
                '<td class="text-end fw-bold small text-success py-1.5">Rs.' + Math.round(total).toLocaleString() + '</td>' +
                '<td class="text-center py-1.5"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 border-0" onclick="removeItem(' + index + ')" title="Remove item"><i class="fas fa-trash-alt"></i></button></td>' +
            '</tr>';
        });
    }
    recalcPOS();
}

function escapeHtml(text) {
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return (text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
}

function stepQty(index, delta) {
    const item = cart[index];
    const newQty = item.qty + delta;
    if (newQty <= 0) {
        removeItem(index);
    } else if (newQty > item.stock) {
        alert('Maximum available stock is ' + item.stock);
    } else {
        item.qty = newQty;
        renderCart();
    }
}

function updateQty(index, val) {
    const q = parseInt(val);
    const item = cart[index];
    if (q > 0) {
        if (q > item.stock) {
            alert('Maximum available stock is ' + item.stock);
            item.qty = item.stock;
        } else {
            item.qty = q;
        }
    } else {
        cart.splice(index, 1);
    }
    renderCart();
}

function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

function clearEntireCart() {
    if (confirm('Are you sure you want to clear the current sale?')) {
        cart = [];
        renderCart();
    }
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

// Member Search Autocomplete
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
        document.getElementById('memberClear').style.display = 'none';
        return;
    }
    document.getElementById('memberClear').style.display = 'inline-block';
    memberTimer = setTimeout(function() {
        fetch('/gym/canteen/pos/search_member.php?q=' + encodeURIComponent(q))
            .then(function(r) { return r.json(); })
            .then(function(members) {
                memberResults.innerHTML = '';
                if (members.length === 0) {
                    memberResults.innerHTML = '<span class="list-group-item small text-muted py-2 text-center"><i class="fas fa-user-slash me-1"></i>No members found</span>';
                } else {
                    members.forEach(function(m) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action small py-2 d-flex justify-content-between align-items-center';
                        btn.innerHTML = '<div><strong>' + escapeHtml(m.name) + '</strong><br><small class="text-muted"><i class="fas fa-phone me-1"></i>' + (m.phone || 'No phone') + '</small></div><span class="badge bg-light text-dark border">Select</span>';
                        btn.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            memberIdInput.value = m.id;
                            memberInput.value = m.name + (m.phone ? ' (' + m.phone + ')' : '');
                            document.getElementById('memberClear').style.display = 'inline-block';
                            memberResults.style.display = 'none';
                        });
                        memberResults.appendChild(btn);
                    });
                }
                memberResults.style.display = 'block';
            });
    }, 200);
});

document.getElementById('memberClear').addEventListener('click', function() {
    memberIdInput.value = 0;
    memberInput.value = '';
    this.style.display = 'none';
    memberResults.style.display = 'none';
    memberResults.innerHTML = '';
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

// Form Submission
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
