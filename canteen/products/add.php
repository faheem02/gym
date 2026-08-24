<?php
$activePage = 'canteen_products';
$pageTitle = 'Add Product';
include __DIR__ . '/../../includes/header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $unit = trim($_POST['unit'] ?? 'piece');
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    $sale_price = (float)($_POST['sale_price'] ?? 0);
    $stock_qty = (int)($_POST['stock_qty'] ?? 0);
    $min_stock = (int)($_POST['min_stock'] ?? 5);
    $status = $_POST['status'] ?? 'active';

    if ($name === '') {
        $error = 'Product name is required.';
    } elseif ($sale_price <= 0) {
        $error = 'Sale price must be greater than 0.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO canteen_products (name, category, unit, purchase_price, sale_price, stock_qty, min_stock, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $category ?: null, $unit, $purchase_price, $sale_price, $stock_qty, $min_stock, $status]);
        if ($stock_qty > 0) {
            $product_id = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO canteen_stock_log (product_id, type, quantity, notes) VALUES (?, "opening", ?, "Opening stock at product creation")');
            $stmt->execute([$product_id, $stock_qty]);
        }
        header('Location: /gym/canteen/products/index.php?msg=added');
        exit;
    }
}
?>

<div class="card form-card" style="max-width: 680px;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-box-open text-warning me-2"></i>Add New Product</h5>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-tag me-1 text-muted"></i>Product Name *</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Whey Protein, Banana, Eggs" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-layer-group me-1 text-muted"></i>Category</label>
                    <input type="text" name="category" class="form-control" list="catList" placeholder="e.g. Supplements, Fruits, Dairy">
                    <datalist id="catList">
                        <option value="Supplements">
                        <option value="Fruits">
                        <option value="Dairy">
                        <option value="Bakery">
                        <option value="Beverages">
                        <option value="Snacks">
                        <option value="Eggs & Meat">
                        <option value="Dry Fruits">
                    </datalist>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-balance-scale me-1 text-muted"></i>Unit</label>
                    <select name="unit" class="form-select">
                        <option value="piece">Piece</option>
                        <option value="kg">Kilogram (kg)</option>
                        <option value="g">Gram (g)</option>
                        <option value="liter">Liter</option>
                        <option value="ml">Milliliter (ml)</option>
                        <option value="pack">Pack</option>
                        <option value="dozen">Dozen</option>
                        <option value="box">Box</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-shopping-bag me-1 text-muted"></i>Purchase Price (Rs.)</label>
                    <input type="number" step="0.01" name="purchase_price" class="form-control" value="0" min="0">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-money-bill-wave me-1 text-muted"></i>Sale Price (Rs.) *</label>
                    <input type="number" step="0.01" name="sale_price" class="form-control" placeholder="Selling price" min="0" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-toggle-on me-1 text-muted"></i>Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-cubes me-1 text-muted"></i>Opening Stock</label>
                    <input type="number" name="stock_qty" class="form-control" value="0" min="0">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-exclamation-triangle me-1 text-muted"></i>Min Stock Alert</label>
                    <input type="number" name="min_stock" class="form-control" value="5" min="0">
                    <small class="text-muted">Alert when stock goes below this</small>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Save Product</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
