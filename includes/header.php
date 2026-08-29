<?php
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars(GYM_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/gym/assets/style.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="layout-wrapper">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo">
                    <img src="<?php echo GYM_LOGO; ?>" alt="<?php echo htmlspecialchars(GYM_NAME); ?>">
                </div>
                <h5><?php echo htmlspecialchars(GYM_NAME); ?></h5>
                <small>Management System</small>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" href="/gym/index.php">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo in_array($activePage, ['members','member_payments','member_ledger']) ? 'active' : ''; ?> sidebar-dropdown-toggle" href="#memberMenu" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo in_array($activePage, ['members','member_payments','member_ledger']) ? 'true' : 'false'; ?>">
                        <i class="fas fa-users"></i>
                        <span>Members</span>
                        <i class="fas fa-chevron-down ms-auto small"></i>
                    </a>
                    <div class="collapse <?php echo in_array($activePage, ['members','member_payments','member_ledger']) ? 'show' : ''; ?>" id="memberMenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'members' ? 'active' : ''; ?>" href="/gym/members/">
                                    <i class="fas fa-users me-1"></i><span>All Members</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'member_payments' ? 'active' : ''; ?>" href="/gym/members/payments.php">
                                    <i class="fas fa-money-check-alt me-1"></i><span>Payments</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'member_ledger' ? 'active' : ''; ?>" href="/gym/members/ledger.php">
                                    <i class="fas fa-book me-1"></i><span>Member Ledger</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'plans' ? 'active' : ''; ?>" href="/gym/plans/">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Plans</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'subscriptions' ? 'active' : ''; ?>" href="/gym/subscriptions/">
                        <i class="fas fa-id-card"></i>
                        <span>Subscriptions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'attendance' ? 'active' : ''; ?>" href="/gym/attendance/">
                        <i class="fas fa-calendar-check"></i>
                        <span>Attendance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'trainers' ? 'active' : ''; ?>" href="/gym/trainers/">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Trainers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo in_array($activePage, ['staff','staff_attendance','staff_salaries','staff_ledger']) ? 'active' : ''; ?> sidebar-dropdown-toggle" href="#staffMenu" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo in_array($activePage, ['staff','staff_attendance','staff_salaries','staff_ledger']) ? 'true' : 'false'; ?>">
                        <i class="fas fa-id-badge"></i>
                        <span>Staff</span>
                        <i class="fas fa-chevron-down ms-auto small"></i>
                    </a>
                    <div class="collapse <?php echo in_array($activePage, ['staff','staff_attendance','staff_salaries','staff_ledger']) ? 'show' : ''; ?>" id="staffMenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'staff' ? 'active' : ''; ?>" href="/gym/staff/">
                                    <i class="fas fa-users me-1"></i><span>All Staff</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'staff_attendance' ? 'active' : ''; ?>" href="/gym/staff/attendance.php">
                                    <i class="fas fa-calendar-check me-1"></i><span>Attendance</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'staff_salaries' ? 'active' : ''; ?>" href="/gym/staff/salaries.php">
                                    <i class="fas fa-money-bill-wave me-1"></i><span>Salaries</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'staff_ledger' ? 'active' : ''; ?>" href="/gym/staff/ledger.php">
                                    <i class="fas fa-book me-1"></i><span>Salary Ledger</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'day_passes' ? 'active' : ''; ?>" href="/gym/day_passes/">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Day Passes</span>
                    </a>
                </li>
            </ul>

            <div class="nav-section">CANTEEN</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'canteen_pos' ? 'active' : ''; ?>" href="/gym/canteen/pos/">
                        <i class="fas fa-cash-register"></i>
                        <span>POS / Billing</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'canteen_sales' ? 'active' : ''; ?>" href="/gym/canteen/sales/">
                        <i class="fas fa-receipt"></i>
                        <span>Sales</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'canteen_products' ? 'active' : ''; ?>" href="/gym/canteen/products/">
                        <i class="fas fa-box-open"></i>
                        <span>Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo in_array($activePage, ['canteen_suppliers','canteen_payments','canteen_ledger']) ? 'active' : ''; ?> sidebar-dropdown-toggle" href="#supplierMenu" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo in_array($activePage, ['canteen_suppliers','canteen_payments','canteen_ledger']) ? 'true' : 'false'; ?>">
                        <i class="fas fa-truck"></i>
                        <span>Suppliers</span>
                        <i class="fas fa-chevron-down ms-auto small"></i>
                    </a>
                    <div class="collapse <?php echo in_array($activePage, ['canteen_suppliers','canteen_payments','canteen_ledger']) ? 'show' : ''; ?>" id="supplierMenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'canteen_suppliers' ? 'active' : ''; ?>" href="/gym/canteen/suppliers/">
                                    <i class="fas fa-users me-1"></i><span>All Suppliers</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'canteen_payments' ? 'active' : ''; ?>" href="/gym/canteen/suppliers/payments.php">
                                    <i class="fas fa-money-check-alt me-1"></i><span>Payments</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'canteen_ledger' ? 'active' : ''; ?>" href="/gym/canteen/suppliers/ledger.php">
                                    <i class="fas fa-book me-1"></i><span>Supplier Ledger</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'canteen_purchases' ? 'active' : ''; ?>" href="/gym/canteen/purchases/">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Purchases</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'canteen_stock' ? 'active' : ''; ?>" href="/gym/canteen/stock/">
                        <i class="fas fa-boxes"></i>
                        <span>Stock Report</span>
                    </a>
                </li>
            </ul>

            <div class="nav-section">EXPENSES</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo in_array($activePage, ['expenses','expense_categories','expense_ledger']) ? 'active' : ''; ?> sidebar-dropdown-toggle" href="#expenseMenu" data-bs-toggle="collapse" role="button" aria-expanded="<?php echo in_array($activePage, ['expenses','expense_categories','expense_ledger']) ? 'true' : 'false'; ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Expenses</span>
                        <i class="fas fa-chevron-down ms-auto small"></i>
                    </a>
                    <div class="collapse <?php echo in_array($activePage, ['expenses','expense_categories','expense_ledger']) ? 'show' : ''; ?>" id="expenseMenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'expenses' ? 'active' : ''; ?>" href="/gym/expenses/">
                                    <i class="fas fa-receipt me-1"></i><span>All Expenses</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'expense_categories' ? 'active' : ''; ?>" href="/gym/expenses/categories/">
                                    <i class="fas fa-tags me-1"></i><span>Categories</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'expense_ledger' ? 'active' : ''; ?>" href="/gym/expenses/ledger.php">
                                    <i class="fas fa-book me-1"></i><span>Expense Ledger</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            </ul>

            <div class="nav-section">ACCOUNTS</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'cashbook' ? 'active' : ''; ?>" href="/gym/cashbook/">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Cash Book</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'bankbook' ? 'active' : ''; ?>" href="/gym/bankbook/">
                        <i class="fas fa-university"></i>
                        <span>Bank Book</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'profit_loss' ? 'active' : ''; ?>" href="/gym/reports/profit_loss.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Profit &amp; Loss</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a class="nav-link" href="/gym/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <div class="main-content">
            <div class="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h4 class="mb-0"><?php echo htmlspecialchars($pageTitle); ?></h4>
                </div>
                <div class="topbar-right">
                    <div class="admin-badge">
                        <div class="avatar"><?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?></div>
                        <span><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                    </div>
                </div>
            </div>
            <div class="content">
