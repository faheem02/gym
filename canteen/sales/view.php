<?php
$activePage = 'canteen_sales';
$pageTitle = 'View Invoice';
include __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /gym/canteen/sales/'); exit; }

$stmt = $pdo->prepare("
    SELECT s.*, m.name AS member_name
    FROM canteen_sales s
    LEFT JOIN members m ON m.id = s.member_id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale) { header('Location: /gym/canteen/sales/'); exit; }

$stmt = $pdo->prepare("
    SELECT si.*, cp.name AS product_name, cp.unit
    FROM canteen_sale_items si
    LEFT JOIN canteen_products cp ON cp.id = si.product_id
    WHERE si.sale_id = ?
");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$methodMeta = [
    'cash'         => ['label' => 'Cash',      'badge' => 'text-bg-success'],
    'card'         => ['label' => 'Card',       'badge' => 'text-bg-primary'],
    'online'       => ['label' => 'Online',     'badge' => 'text-bg-info'],
    'easypaisa'    => ['label' => 'EasyPaisa',  'badge' => 'text-bg-warning'],
    'jazzcash'     => ['label' => 'JazzCash',   'badge' => 'text-bg-danger'],
];
$meta   = $methodMeta[$sale['payment_method']] ?? ['label' => ucfirst($sale['payment_method']), 'badge' => 'text-bg-secondary'];
$change = max(0, (float)$sale['received_amount'] - (float)$sale['final_amount']);
?>

<!-- Action bar (hidden on print) -->
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="if(window.opener||window.history.length<=1){window.close();}else{window.location='/gym/canteen/sales/';}">
        <i class="fas fa-arrow-left me-1"></i>Back to Sales
    </button>
    <div class="d-flex gap-2">
        <a href="/gym/canteen/sales/edit.php?id=<?php echo $sale['id']; ?>" class="btn btn-warning btn-sm fw-bold">
            <i class="fas fa-edit me-1"></i>Edit
        </a>
        <button type="button" onclick="window.print();" class="btn btn-danger btn-sm fw-bold">
            <i class="fas fa-print me-1"></i>Print Invoice
        </button>
        <a href="/gym/canteen/sales/delete.php?id=<?php echo $sale['id']; ?>"
           class="btn btn-outline-danger btn-sm fw-bold"
           onclick="return confirm('Delete this sale? This cannot be undone.');">
            <i class="fas fa-trash me-1"></i>Delete
        </a>
    </div>
</div>

<?php if (!empty($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-1"></i>Sale updated successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Invoice Card -->
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm" id="invoicePrint">

            <!-- Invoice Header -->
            <div class="card-header text-center py-4" style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
                <div class="mb-1"><i class="fas fa-dumbbell fa-2x" style="color:#f7b731;"></i></div>
                <h4 class="fw-bold mb-0">FITNESS GYM</h4>
                <small class="opacity-75">Canteen Sale Invoice</small>
            </div>

            <div class="card-body px-4 py-3">

                <!-- Receipt meta -->
                <div class="row mb-3">
                    <div class="col-6">
                        <p class="mb-1 small text-muted">Receipt No.</p>
                        <p class="fw-bold font-monospace mb-0"><?php echo htmlspecialchars($sale['receipt_no'] ?? ('SALE-' . $sale['id'])); ?></p>
                    </div>
                    <div class="col-6 text-end">
                        <p class="mb-1 small text-muted">Date</p>
                        <p class="fw-bold mb-0"><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></p>
                    </div>
                    <div class="col-6 mt-2">
                        <p class="mb-1 small text-muted">Customer</p>
                        <p class="fw-semibold mb-0">
                            <?php if (!empty($sale['member_id'])): ?>
                                <i class="fas fa-user-tag me-1 text-muted"></i><?php echo htmlspecialchars($sale['member_name'] ?? 'Member #' . $sale['member_id']); ?>
                            <?php else: ?>
                                <i class="fas fa-user me-1 text-muted"></i><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-6 text-end mt-2">
                        <p class="mb-1 small text-muted">Payment Method</p>
                        <span class="badge <?php echo $meta['badge']; ?>"><?php echo $meta['label']; ?></span>
                    </div>
                </div>

                <hr>

                <!-- Items Table -->
                <table class="table table-sm align-middle mb-3">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $i => $it): ?>
                        <tr>
                            <td class="text-muted small"><?php echo $i + 1; ?></td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($it['product_name'] ?? 'Deleted Product'); ?></td>
                            <td class="text-center"><?php echo $it['quantity']; ?> <small class="text-muted"><?php echo htmlspecialchars($it['unit'] ?? ''); ?></small></td>
                            <td class="text-end">Rs.<?php echo number_format($it['unit_price'], 2); ?></td>
                            <td class="text-end fw-bold">Rs.<?php echo number_format($it['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No items found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="row justify-content-end">
                    <div class="col-md-6">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td class="text-muted">Subtotal</td>
                                <td class="text-end fw-semibold">Rs.<?php echo number_format($sale['total_amount'], 2); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Discount</td>
                                <td class="text-end text-danger fw-semibold">- Rs.<?php echo number_format($sale['discount'], 2); ?></td>
                            </tr>
                            <tr class="table-success">
                                <td class="fw-bold">Net Total</td>
                                <td class="text-end fw-bold text-success fs-5">Rs.<?php echo number_format($sale['final_amount'], 2); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Amount Received</td>
                                <td class="text-end fw-semibold">Rs.<?php echo number_format($sale['received_amount'], 2); ?></td>
                            </tr>
                            <?php if ($change > 0): ?>
                            <tr>
                                <td class="text-muted">Change Returned</td>
                                <td class="text-end fw-bold text-primary">Rs.<?php echo number_format($change, 2); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <?php if (!empty($sale['notes'])): ?>
                <hr>
                <p class="small text-muted mb-0"><strong>Notes:</strong> <?php echo htmlspecialchars($sale['notes']); ?></p>
                <?php endif; ?>

            </div>

            <!-- Invoice Footer -->
            <div class="card-footer text-center text-muted small py-3">
                <i class="fas fa-heart text-danger me-1"></i>Thank you for your visit! — Fitness Gym
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print, .topbar, .sidebar, .sidebar-overlay { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    #invoicePrint { box-shadow: none !important; border: 1px solid #ddd; }
    body { background: #fff !important; }
}
</style>

<?php if (!empty($_GET['print'])): ?>
<script>
window.onload = function () {
    window.print();
    window.onafterprint = function () { window.close(); };
};
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
