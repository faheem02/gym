<?php
$activePage = 'canteen_suppliers';
$pageTitle = 'Suppliers';
include __DIR__ . '/../../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Supplier added.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Supplier updated.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Supplier deleted.</div>';
if ($msg === 'payment') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Payment recorded.</div>';

$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM canteen_suppliers WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (name LIKE ? OR phone LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sql .= " ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();
?>

<div class="page-header">
    <div></div>
    <div class="d-flex gap-2">
        <a href="payments.php" class="btn btn-outline-success fw-bold"><i class="fas fa-money-check-alt me-1"></i>Payments</a>
        <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>Add Supplier</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="" class="d-flex align-items-center gap-2">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by name or phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <?php if ($search !== ''): ?>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm fw-bold"><i class="fas fa-filter me-1"></i>Search</button>
        </form>
    </div>
</div>

<div class="card" style="border-top:3px solid #3b82f6;">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Balance (Rs.)</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-truck me-1"></i>No suppliers found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($suppliers as $s): ?>
                        <?php $bal = (float)$s['balance']; ?>
                        <tr>
                            <td><?php echo $s['id']; ?></td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($s['name']); ?></td>
                            <td><?php echo htmlspecialchars($s['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($s['email'] ?? '-'); ?></td>
                            <td>
                                <?php if ($bal > 0): ?>
                                    <span class="text-danger fw-bold">Rs.<?php echo number_format($bal, 0); ?></span>
                                    <small class="text-muted">(due)</small>
                                <?php elseif ($bal < 0): ?>
                                    <span class="text-success fw-bold">Rs.<?php echo number_format(abs($bal), 0); ?></span>
                                    <small class="text-muted">(advance)</small>
                                <?php else: ?>
                                    <span class="text-muted">Rs.0</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo $s['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>"><?php echo ucfirst($s['status']); ?></span></td>
                            <td class="text-end">
                                <a href="ledger.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-success" title="Ledger"><i class="fas fa-book"></i></a>
                                <a href="edit.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                                <a href="delete.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this supplier?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
