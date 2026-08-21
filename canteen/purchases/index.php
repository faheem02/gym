<?php
$activePage = 'canteen_purchases';
$pageTitle = 'Purchases';
include __DIR__ . '/../../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Purchase recorded successfully.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Purchase deleted.</div>';

$purchases = $pdo->query("
    SELECT p.*, s.name AS supplier_name
    FROM canteen_purchases p
    LEFT JOIN canteen_suppliers s ON s.id = p.supplier_id
    ORDER BY p.purchase_date DESC, p.id DESC
")->fetchAll();
?>

<div class="page-header">
    <div></div>
    <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>New Purchase</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Total (Rs.)</th>
                    <th>Paid (Rs.)</th>
                    <th>Due (Rs.)</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($purchases)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-shopping-cart me-1"></i>No purchases found.</td></tr>
                <?php endif; ?>
                <?php foreach ($purchases as $p): ?>
                    <?php
                    $total = (float)$p['total_amount'];
                    $paid = (float)$p['paid_amount'];
                    $due = $total - $paid;
                    $status = $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
                    ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td><?php echo date('d M Y', strtotime($p['purchase_date'])); ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($p['supplier_name'] ?? 'Unknown'); ?></td>
                        <td class="fw-bold">Rs.<?php echo number_format($total, 0); ?></td>
                        <td class="text-success">Rs.<?php echo number_format($paid, 0); ?></td>
                        <td class="<?php echo $due > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>">Rs.<?php echo number_format($due, 0); ?></td>
                        <td>
                            <span class="badge <?php echo $status === 'paid' ? 'badge-active' : ($status === 'partial' ? 'bg-warning' : 'badge-inactive'); ?>">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#detail<?php echo $p['id']; ?>" title="View Items"><i class="fas fa-eye"></i></button>
                            <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            <a href="delete.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this purchase? Stock will be reversed.');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($purchases as $p): ?>
<?php
$stmt = $pdo->prepare("SELECT pi.*, cp.name AS product_name, cp.unit
    FROM canteen_purchase_items pi
    LEFT JOIN canteen_products cp ON cp.id = pi.product_id
    WHERE pi.purchase_id = ?");
$stmt->execute([$p['id']]);
$items = $stmt->fetchAll();
?>
<div class="modal fade" id="detail<?php echo $p['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="fas fa-shopping-cart me-1"></i>Purchase #<?php echo $p['id']; ?> — Items</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($it['product_name']); ?></td>
                            <td class="text-end"><?php echo $it['qty']; ?> <?php echo $it['unit']; ?></td>
                            <td class="text-end">Rs.<?php echo number_format($it['unit_price'], 0); ?></td>
                            <td class="text-end fw-bold">Rs.<?php echo number_format($it['total'], 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($p['notes']): ?>
                    <div class="mt-3 p-2 bg-light rounded"><small class="text-muted"><i class="fas fa-sticky-note me-1"></i><?php echo htmlspecialchars($p['notes']); ?></small></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
