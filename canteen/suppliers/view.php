<?php
$activePage = 'canteen_suppliers';
$pageTitle = 'Supplier Profile';
include __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM canteen_suppliers WHERE id = ?');
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    echo '<div class="alert alert-warning">Supplier not found. <a href="index.php">Back to suppliers</a></div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$balance = (float)$supplier['balance'];

// Purchase totals
$stmt = $pdo->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total_billed, COALESCE(SUM(paid_amount),0) AS total_paid_at_purchase FROM canteen_purchases WHERE supplier_id = ?');
$stmt->execute([$id]);
$purchaseTotals = $stmt->fetch();
$purchaseCount = (int)$purchaseTotals['cnt'];
$totalBilled = (float)$purchaseTotals['total_billed'];

// Payment totals
$stmt = $pdo->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM canteen_supplier_payments WHERE supplier_id = ?');
$stmt->execute([$id]);
$paymentTotals = $stmt->fetch();
$paymentCount = (int)$paymentTotals['cnt'];
$totalPaidSeparate = (float)$paymentTotals['total'];

$totalPaidAll = (float)$purchaseTotals['total_paid_at_purchase'] + $totalPaidSeparate;

// Recent purchases
$stmt = $pdo->prepare('SELECT * FROM canteen_purchases WHERE supplier_id = ? ORDER BY purchase_date DESC, id DESC LIMIT 10');
$stmt->execute([$id]);
$purchases = $stmt->fetchAll();

// Recent payments
$stmt = $pdo->prepare('SELECT * FROM canteen_supplier_payments WHERE supplier_id = ? ORDER BY payment_date DESC, id DESC LIMIT 10');
$stmt->execute([$id]);
$payments = $stmt->fetchAll();
?>

<div class="mb-4">
    <a href="index.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning"><i class="fas fa-shopping-cart"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalBilled, 0); ?></h5>
                    <small class="text-muted">Total Purchases (<?php echo $purchaseCount; ?>)</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="fas fa-hand-holding-usd"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalPaidAll, 0); ?></h5>
                    <small class="text-muted">Total Paid (<?php echo $paymentCount; ?> payments)</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon <?php echo $balance > 0 ? 'bg-danger' : 'bg-primary'; ?>"><i class="fas fa-balance-scale"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Rs.<?php echo number_format(abs($balance), 0); ?></h5>
                    <small class="text-muted"><?php echo $balance > 0 ? 'Outstanding Due' : ($balance < 0 ? 'Advance Paid' : 'Settled'); ?></small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info"><i class="fas fa-truck"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo ucfirst($supplier['status']); ?></h5>
                    <small class="text-muted">Supplier Status</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card h-100" style="border-top:3px solid #f7b731;">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="stat-icon" style="width:64px;height:64px;font-size:1.6rem;background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;box-shadow:0 4px 15px rgba(247,183,49,0.3);">
                        <?php echo strtoupper(substr($supplier['name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($supplier['name']); ?></h5>
                        <span class="badge <?php echo $supplier['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>"><?php echo ucfirst($supplier['status']); ?></span>
                        <span class="badge bg-dark ms-1">#<?php echo $supplier['id']; ?></span>
                        <?php if ($balance > 0): ?>
                            <span class="badge bg-danger ms-1">Due Rs.<?php echo number_format($balance, 0); ?></span>
                        <?php elseif ($balance < 0): ?>
                            <span class="badge bg-success ms-1">Advance Rs.<?php echo number_format(abs($balance), 0); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <ul class="list-unstyled mb-0">
                    <li class="mb-3"><i class="fas fa-phone text-muted me-2"></i><span class="text-muted">Phone:</span> <span class="fw-semibold"><?php echo htmlspecialchars($supplier['phone'] ?? '-'); ?></span></li>
                    <li class="mb-3"><i class="fas fa-envelope text-muted me-2"></i><span class="text-muted">Email:</span> <span class="fw-semibold"><?php echo htmlspecialchars($supplier['email'] ?? '-'); ?></span></li>
                    <li class="mb-3"><i class="fas fa-map-marker-alt text-muted me-2"></i><span class="text-muted">Address:</span> <span class="fw-semibold"><?php echo htmlspecialchars($supplier['address'] ?? '-'); ?></span></li>
                    <li class="mb-0"><i class="fas fa-calendar text-muted me-2"></i><span class="text-muted">Added On:</span> <span class="fw-semibold"><?php echo date('d M Y', strtotime($supplier['created_at'])); ?></span></li>
                </ul>
                <div class="d-flex gap-2 mt-4 flex-wrap">
                    <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary fw-bold"><i class="fas fa-pen me-1"></i>Edit</a>
                    <a href="ledger.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-dark fw-bold"><i class="fas fa-book me-1"></i>Ledger</a>
                    <a href="payments.php?supplier_id=<?php echo $id; ?>" class="btn btn-sm btn-success fw-bold"><i class="fas fa-money-check-alt me-1"></i>Record Payment</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100" style="border-top:3px solid #3b82f6;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-shopping-cart me-2" style="color:#3b82f6;"></i>Recent Purchases</h6>
                    <a href="ledger.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-dark">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Date</th><th>Purchase #</th><th class="text-end">Total</th><th class="text-end">Paid at Purchase</th></tr></thead>
                        <tbody>
                            <?php if (empty($purchases)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No purchases found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($purchases as $p): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($p['purchase_date'])); ?></td>
                                    <td class="fw-semibold font-monospace small">#<?php echo $p['id']; ?></td>
                                    <td class="text-end fw-bold text-danger">Rs.<?php echo number_format($p['total_amount'], 0); ?></td>
                                    <td class="text-end fw-bold text-success">Rs.<?php echo number_format($p['paid_amount'], 0); ?></td>
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
    <div class="col-lg-6">
        <div class="card h-100" style="border-top:3px solid #10b981;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-hand-holding-usd me-2" style="color:#10b981;"></i>Recent Payments</h6>
                    <a href="payments.php?supplier_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Date</th><th>Method</th><th>Notes</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No payments found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                                    <td><small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></small></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($p['notes'] ?? '-'); ?></small></td>
                                    <td class="text-end fw-bold text-success">Rs.<?php echo number_format($p['amount'], 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100" style="border-top:3px solid #8b5cf6;">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-calculator me-2" style="color:#8b5cf6;"></i>Account Summary</h6>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td>Total Purchases</td>
                            <td class="text-end fw-bold">Rs.<?php echo number_format($totalBilled, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Total Paid (with purchases)</td>
                            <td class="text-end fw-bold text-success">Rs.<?php echo number_format((float)$purchaseTotals['total_paid_at_purchase'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Total Payments (separate)</td>
                            <td class="text-end fw-bold text-success">Rs.<?php echo number_format($totalPaidSeparate, 2); ?></td>
                        </tr>
                        <tr style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
                            <td class="fw-bold"><?php echo $balance > 0 ? 'Outstanding Due' : ($balance < 0 ? 'Advance Paid' : 'Balance (Settled)'); ?></td>
                            <td class="text-end fw-bold">Rs.<?php echo number_format(abs($balance), 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
