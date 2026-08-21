<?php
$activePage = 'canteen_products';
$pageTitle = 'Canteen Products';
include __DIR__ . '/../../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Product added successfully.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Product updated.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Product deleted.</div>';

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM canteen_products';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE name LIKE ? OR category LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like];
}
$sql .= ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query('SELECT DISTINCT category FROM canteen_products WHERE category IS NOT NULL AND category != "" ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="search-bar">
    <div class="row g-2 align-items-center">
        <div class="col-md-7 col-lg-8">
            <form method="GET" action="" class="d-flex">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control me-2" placeholder="Search products...">
                <button class="btn btn-dark" type="submit"><i class="fas fa-search me-1"></i>Search</button>
            </form>
        </div>
        <div class="col-md-5 col-lg-4 text-md-end">
            <a href="add.php" class="btn btn-warning fw-bold"><i class="fas fa-plus me-1"></i>Add Product</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Purchase Price</th>
                    <th>Sale Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-box-open me-1"></i>No products found.</td></tr>
                <?php endif; ?>
                <?php foreach ($products as $p): ?>
                    <?php
                        $stockClass = 'text-success';
                        if ($p['stock_qty'] <= 0) $stockClass = 'text-danger fw-bold';
                        elseif ($p['stock_qty'] <= $p['min_stock']) $stockClass = 'text-warning fw-bold';
                    ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></td>
                        <td><span class="badge text-bg-dark"><?php echo htmlspecialchars($p['category'] ?? '-'); ?></span></td>
                        <td><?php echo htmlspecialchars($p['unit']); ?></td>
                        <td>Rs.<?php echo number_format($p['purchase_price'], 0); ?></td>
                        <td class="fw-bold">Rs.<?php echo number_format($p['sale_price'], 0); ?></td>
                        <td class="<?php echo $stockClass; ?>"><?php echo $p['stock_qty']; ?> <?php echo htmlspecialchars($p['unit']); ?></td>
                        <td>
                            <span class="badge <?php echo $p['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo ucfirst($p['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                            <a href="delete.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this product?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
