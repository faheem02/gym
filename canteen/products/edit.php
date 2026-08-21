<?php
$activePage = 'canteen_products';
$pageTitle = 'Edit Product';
include __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM canteen_products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    echo '<div class="alert alert-warning">Product not found. <a href="index.php">Back</a></div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

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
        $stmt = $pdo->prepare('UPDATE canteen_products SET name=?, category=?, unit=?, purchase_price=?, sale_price=?, stock_qty=?, min_stock=?, status=? WHERE id=?');
        $stmt->execute([$name, $category ?: null, $unit, $purchase_price, $sale_price, $stock_qty, $min_stock, $status, $id]);
        header('Location: /gym/canteen/products/index.php?msg=updated');
        exit;
    }
}
?>

<div class="card form-card" style="max-width: 680px;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-edit text-warning me-2"></i>Edit Product: <?php echo htmlspecialchars($product['name']); ?></h5>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-tag me-1 text-muted"></i>Product Name *</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-layer-group me-1 text-muted"></i>Category</label>
                    <input type="text" name="category" class="form-control" list="catList" value="<?php echo htmlspecialchars($product['category'] ?? ''); ?>">
                    <datalist id="catList">
                        <option value="Supplements"><option value="Fruits"><option value="Dairy"><option value="Bakery"><option value="Beverages"><option value="Snacks"><option value="Eggs & Meat"><option value="Dry Fruits">
                    </datalist>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-balance-scale me-1 text-muted"></i>Unit</label>
                    <select name="unit" class="form-select">
                        <?php foreach (['piece'=>'Piece','kg'=>'Kilogram (kg)','g'=>'Gram (g)','liter'=>'Liter','ml'=>'Milliliter (ml)','pack'=>'Pack','dozen'=>'Dozen','box'=>'Box'] as $val => $lbl): ?>
                            <option value="<?php echo $val; ?>" <?php echo $product['unit'] === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-shopping-bag me-1 text-muted"></i>Purchase Price (Rs.)</label>
                    <input type="number" step="0.01" name="purchase_price" class="form-control" value="<?php echo $product['purchase_price']; ?>" min="0">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-money-bill-wave me-1 text-muted"></i>Sale Price (Rs.) *</label>
                    <input type="number" step="0.01" name="sale_price" class="form-control" value="<?php echo $product['sale_price']; ?>" min="0" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-toggle-on me-1 text-muted"></i>Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-cubes me-1 text-muted"></i>Stock Qty</label>
                    <input type="number" name="stock_qty" class="form-control" value="<?php echo $product['stock_qty']; ?>" min="0">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-exclamation-triangle me-1 text-muted"></i>Min Stock Alert</label>
                    <input type="number" name="min_stock" class="form-control" value="<?php echo $product['min_stock']; ?>" min="0">
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Update Product</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
