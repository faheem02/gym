<?php
$activePage = 'expenses';
$pageTitle = 'Expenses';
include __DIR__ . '/../includes/header.php';

$msg = $_GET['msg'] ?? '';
if ($msg === 'added') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Expense added.</div>';
if ($msg === 'updated') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Expense updated.</div>';
if ($msg === 'deleted') echo '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i>Expense deleted.</div>';

$filterCat = $_GET['category'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$search = trim($_GET['search'] ?? '');

$categories = $pdo->query("SELECT id, name FROM expense_categories WHERE status = 'active' ORDER BY name")->fetchAll();

$sql = "SELECT e.*, ec.name AS category_name FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id WHERE 1=1";
$params = [];
if ($filterCat !== '') { $sql .= " AND e.category_id = ?"; $params[] = $filterCat; }
if ($filterDateFrom !== '') { $sql .= " AND e.expense_date >= ?"; $params[] = $filterDateFrom; }
if ($filterDateTo !== '') { $sql .= " AND e.expense_date <= ?"; $params[] = $filterDateTo; }
if ($search !== '') { $sql .= " AND (e.description LIKE ? OR e.receipt_no LIKE ?)"; $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%'; }
$sql .= " ORDER BY e.expense_date DESC, e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$totalAmount = 0;
foreach ($expenses as $e) $totalAmount += (float)$e['amount'];

$thisMonth = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())")->fetchColumn();
$today = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = CURDATE()")->fetchColumn();
?>

<div class="page-header">
    <div></div>
    <div class="d-flex gap-2">
        <a href="categories/" class="btn btn-outline-warning fw-bold"><i class="fas fa-tags me-1"></i>Categories</a>
        <a href="add.php" class="btn btn-warning fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-plus me-1"></i>Add Expense</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-danger"><i class="fas fa-receipt"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($totalAmount, 0); ?></h5><small class="text-muted">Filtered Total</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-warning"><i class="fas fa-calendar-alt"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($thisMonth, 0); ?></h5><small class="text-muted">This Month</small></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-success"><i class="fas fa-clock"></i></div>
            <div><h5 class="mb-0 fw-bold">Rs.<?php echo number_format($today, 0); ?></h5><small class="text-muted">Today</small></div>
        </div></div>
    </div>
</div>

<div class="card" style="border-top:3px solid #f7b731;">
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $filterCat == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDateFrom); ?>"></div>
            <div class="col-md-2"><input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDateTo); ?>"></div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm flex-fill fw-bold" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;border:none;"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Date</th><th>Category</th><th>Description</th><th>Method</th><th class="text-end">Amount</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-receipt me-1"></i>No expenses found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($expenses as $i => $e): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo date('d M Y', strtotime($e['expense_date'])); ?></td>
                            <td><span class="badge" style="background:linear-gradient(135deg,#f7b731,#f5a623);color:#fff;"><?php echo htmlspecialchars($e['category_name'] ?? 'Unknown'); ?></span></td>
                            <td class="small"><?php echo htmlspecialchars($e['description'] ?? '-'); ?></td>
                            <td><span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('_', ' ', $e['payment_method'])); ?></span></td>
                            <td class="text-end fw-bold text-danger">Rs.<?php echo number_format($e['amount'], 0); ?></td>
                            <td class="text-end">
                                <a href="edit.php?id=<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <a href="delete.php?id=<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this expense?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
                        <td colspan="5" class="fw-bold"><?php echo count($expenses); ?> expense(s)</td>
                        <td class="text-end fw-bold">Rs.<?php echo number_format($totalAmount, 0); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
