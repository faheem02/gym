<?php
$activePage = 'canteen_products';
$pageTitle = 'Product Details';
include __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM canteen_products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    echo '<div class="alert alert-warning">Product not found. <a href="index.php">Back to products</a></div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$stockValue = (float)$product['purchase_price'] * (int)$product['stock_qty'];

// Sales stats
$stmt = $pdo->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(quantity),0) AS qty, COALESCE(SUM(subtotal),0) AS total FROM canteen_sale_items WHERE product_id = ?');
$stmt->execute([$id]);
$salesStats = $stmt->fetch();

// Purchase stats
$stmt = $pdo->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(qty),0) AS qty, COALESCE(SUM(total),0) AS total FROM canteen_purchase_items WHERE product_id = ?');
$stmt->execute([$id]);
$purchaseStats = $stmt->fetch();

// Recent sales of this product
$stmt = $pdo->prepare('SELECT si.quantity, si.unit_price, si.subtotal, s.receipt_no, s.sale_date, s.customer_name FROM canteen_sale_items si JOIN canteen_sales s ON s.id = si.sale_id WHERE si.product_id = ? ORDER BY s.sale_date DESC, s.id DESC LIMIT 10');
$stmt->execute([$id]);
$recentSales = $stmt->fetchAll();

// Recent purchases of this product
$stmt = $pdo->prepare('SELECT pi.qty, pi.unit_price, pi.total, p.id AS purchase_id, p.purchase_date FROM canteen_purchase_items pi JOIN canteen_purchases p ON p.id = pi.purchase_id WHERE pi.product_id = ? ORDER BY p.purchase_date DESC, p.id DESC LIMIT 10');
$stmt->execute([$id]);
$recentPurchases = $stmt->fetchAll();
?>

<div class="mb-4">
    <a href="index.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon <?php echo (int)$product['stock_qty'] <= 0 ? 'bg-danger' : ((int)$product['stock_qty'] <= (int)$product['min_stock'] ? 'bg-warning' : 'bg-success'); ?>"><i class="fas fa-boxes"></i></div>
            <div>
                <h5 class="mb-0 fw-bold"><?php echo (int)$product['stock_qty']; ?> <?php echo htmlspecialchars($product['unit']); ?></h5>
                <small class="text-muted">Current Stock</small>
                <?php if ((int)$product['stock_qty'] <= 0): ?>
                    <div class="small text-danger fw-bold">Out of stock</div>
                <?php elseif ((int)$product['stock_qty'] <= (int)$product['min_stock']): ?>
                    <div class="small text-warning fw-bold">Low stock (min: <?php echo (int)$product['min_stock']; ?>)</div>
                <?php endif; ?>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-primary"><i class="fas fa-coins"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($stockValue, 0); ?></h5><small class="text-muted">Stock Value</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-success"><i class="fas fa-shopping-cart"></i></div>
            <div><h5 class="mb-0 fw-bold"><?php echo (int)$salesStats['qty']; ?></h5><small class="text-muted">Sold (<?php echo (int)$salesStats['cnt']; ?> orders)</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-info"><i class="fas fa-truck"></i></div>
            <div><h5 class="mb-0 fw-bold"><?php echo (int)$purchaseStats['qty']; ?></h5><small class="text-muted">Purchased (<?php echo (int)$purchaseStats['cnt']; ?> orders)</small></div>
        </div></div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card h-100" style="border-top:3px solid #f7b731;">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="stat-icon" style="width:64px;height:64px;font-size:1.6rem;background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;box-shadow:0 4px 15px rgba(247,183,49,0.3);">
                        <i class="fas fa-box"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <span class="badge <?php echo $product['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>"><?php echo ucfirst($product['status']); ?></span>
                        <span class="badge bg-dark ms-1">#<?php echo $product['id']; ?></span>
                        <?php if (!empty($product['category'])): ?><span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($product['category']); ?></span><?php endif; ?>
                    </div>
                </div>
                <ul class="list-unstyled mb-0">
                    <li class="mb-3"><i class="fas fa-ruler text-muted me-2"></i><span class="text-muted">Unit:</span> <span class="fw-semibold"><?php echo htmlspecialchars($product['unit']); ?></span></li>
                    <li class="mb-3"><i class="fas fa-arrow-down text-muted me-2"></i><span class="text-muted">Purchase Price:</span> <span class="fw-semibold">Rs.<?php echo number_format($product['purchase_price'], 2); ?></span></li>
                    <li class="mb-3"><i class="fas fa-arrow-up text-muted me-2"></i><span class="text-muted">Sale Price:</span> <span class="fw-semibold text-success">Rs.<?php echo number_format($product['sale_price'], 2); ?></span></li>
                    <li class="mb-3"><i class="fas fa-chart-line text-muted me-2"></i><span class="text-muted">Profit / Unit:</span> <span class="fw-semibold text-success">Rs.<?php echo number_format((float)$product['sale_price'] - (float)$product['purchase_price'], 2); ?></span></li>
                    <li class="mb-3"><i class="fas fa-exclamation-circle text-muted me-2"></i><span class="text-muted">Min Stock Alert:</span> <span class="fw-semibold"><?php echo (int)$product['min_stock']; ?> <?php echo htmlspecialchars($product['unit']); ?></span></li>
                    <li class="mb-0"><i class="fas fa-calendar text-muted me-2"></i><span class="text-muted">Added On:</span> <span class="fw-semibold"><?php echo date('d M Y', strtotime($product['created_at'])); ?></span></li>
                </ul>
                <div class="d-flex gap-2 mt-4 flex-wrap">
                    <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary fw-bold"><i class="fas fa-pen me-1"></i>Edit</a>
                    <a href="/gym/canteen/purchases/" class="btn btn-sm btn-outline-dark fw-bold"><i class="fas fa-truck me-1"></i>Purchases</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100" style="border-top:3px solid #10b981;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-shopping-cart me-2" style="color:#10b981;"></i>Recent Sales</h6>
                    <span class="badge bg-success">Total Sold: Rs.<?php echo number_format((float)$salesStats['total'], 0); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Date</th><th>Receipt #</th><th>Customer</th><th class="text-end">Qty</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            <?php if (empty($recentSales)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No sales yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recentSales as $rs): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($rs['sale_date'])); ?></td>
                                    <td class="font-monospace small"><?php echo htmlspecialchars($rs['receipt_no'] ?? '#' . ''); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($rs['customer_name'] ?? 'Walk-in'); ?></td>
                                    <td class="text-end fw-semibold"><?php echo (int)$rs['quantity']; ?></td>
                                    <td class="text-end fw-bold text-success">Rs.<?php echo number_format($rs['subtotal'], 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card" style="border-top:3px solid #8b5cf6;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-truck me-2" style="color:#8b5cf6;"></i>Recent Purchases</h6>
                    <span class="badge bg-primary">Total Purchased: Rs.<?php echo number_format((float)$purchaseStats['total'], 0); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Date</th><th>Purchase #</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            <?php if (empty($recentPurchases)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No purchases yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recentPurchases as $rp): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($rp['purchase_date'])); ?></td>
                                    <td class="font-monospace small">#<?php echo $rp['purchase_id']; ?></td>
                                    <td class="text-end fw-semibold"><?php echo (int)$rp['qty']; ?></td>
                                    <td class="text-end">Rs.<?php echo number_format($rp['unit_price'], 2); ?></td>
                                    <td class="text-end fw-bold text-danger">Rs.<?php echo number_format($rp['total'], 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
