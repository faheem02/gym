<?php
$activePage = 'plans';
$pageTitle = 'Edit Membership Plan';
include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM plans WHERE id = ?');
$stmt->execute([$id]);
$plan = $stmt->fetch();

if (!$plan) {
    echo '<div class="alert alert-warning">Plan not found. <a href="index.php">Back to plans</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $duration_days = (int)($_POST['duration_days'] ?? 0);
    $price = trim($_POST['price'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $features = trim($_POST['features'] ?? '');
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;
    $day_pass_discount = (int)($_POST['day_pass_discount'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if ($day_pass_discount < 0) $day_pass_discount = 0;
    if ($day_pass_discount > 100) $day_pass_discount = 100;

    if ($name === '' || $duration_days <= 0 || $price === '' || !is_numeric($price)) {
        $error = 'Plan name, a valid duration and price are required.';
    } else {
        $stmt = $pdo->prepare('UPDATE plans SET name = ?, duration_days = ?, price = ?, description = ?, features = ?, is_popular = ?, day_pass_discount = ?, status = ? WHERE id = ?');
        $stmt->execute([$name, $duration_days, $price, $description ?: null, $features ?: null, $is_popular, $day_pass_discount, $status, $id]);
        header('Location: /gym/plans/index.php?msg=updated');
        exit;
    }
}
?>

<div class="card form-card" style="max-width: 720px;">
    <div class="card-body">
        <h5 class="mb-4"><i class="fas fa-edit text-warning me-2"></i>Edit Plan: <?php echo htmlspecialchars($plan['name']); ?></h5>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label"><i class="fas fa-tag me-1 text-muted"></i>Plan Name *</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($plan['name']); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><i class="fas fa-toggle-on me-1 text-muted"></i>Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo ($plan['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($plan['status'] ?? 'active') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-clock me-1 text-muted"></i>Duration (days) *</label>
                    <input type="number" name="duration_days" class="form-control" min="1" value="<?php echo $plan['duration_days']; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-money-bill-wave me-1 text-muted"></i>Price (Rs.) *</label>
                    <input type="number" step="0.01" name="price" class="form-control" value="<?php echo $plan['price']; ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-align-left me-1 text-muted"></i>Description</label>
                <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($plan['description'] ?? ''); ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-list-ul me-1 text-muted"></i>Features</label>
                <textarea name="features" class="form-control" rows="5" placeholder="Enter one feature per line..."><?php echo htmlspecialchars($plan['features'] ?? ''); ?></textarea>
                <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Enter each feature on a new line</small>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="is_popular" value="1" id="popularCheck" <?php echo $plan['is_popular'] ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-semibold" for="popularCheck">
                        <i class="fas fa-star text-warning me-1"></i>Mark as Popular Plan
                    </label>
                </div>
                <small class="text-muted">Popular plans are highlighted with a special badge on the pricing cards</small>
            </div>

            <div class="section-label mb-3">
                <h6 class="fw-bold text-muted"><i class="fas fa-ticket-alt me-1"></i> Day Pass Discount</h6>
                <hr class="mt-1">
            </div>

            <div class="mb-4">
                <label class="form-label"><i class="fas fa-percent me-1 text-muted"></i>Discount for Day Passes (%)</label>
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <input type="number" name="day_pass_discount" class="form-control" id="discountInput" min="0" max="100" value="<?php echo $plan['day_pass_discount'] ?? 0; ?>" onchange="previewDiscount()">
                    </div>
                    <div class="col-md-8">
                        <input type="range" class="form-range" min="0" max="100" step="5" value="<?php echo $plan['day_pass_discount'] ?? 0; ?>" id="discountRange" oninput="syncDiscount(this.value)">
                        <div class="d-flex justify-content-between" style="margin-top:-8px;">
                            <small class="text-muted">No discount</small>
                            <small class="text-muted">100% = FREE</small>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info py-2 mt-2 mb-0" id="discountPreview" style="display:none;">
                    <i class="fas fa-info-circle me-1"></i>
                    <span id="discountPreviewText"></span>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning fw-bold"><i class="fas fa-save me-1"></i>Update Plan</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function syncDiscount(val) {
    document.getElementById('discountInput').value = val;
    previewDiscount();
}
function previewDiscount() {
    var val = parseInt(document.getElementById('discountInput').value) || 0;
    document.getElementById('discountRange').value = val;
    var preview = document.getElementById('discountPreview');
    var text = document.getElementById('discountPreviewText');
    if (val > 0) {
        preview.style.display = 'block';
        if (val >= 100) {
            text.innerHTML = '<strong>Day passes will be FREE</strong> for members with this plan.';
        } else {
            text.innerHTML = 'Members with this plan get <strong>' + val + '% off</strong> on all day passes.';
        }
    } else {
        preview.style.display = 'none';
    }
}
previewDiscount();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
