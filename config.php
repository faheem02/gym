<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'gym_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Business / Gym Profile Info
define('GYM_NAME', 'The Compound Gym');
define('GYM_PHONE', '03250133322');
define('GYM_ADDRESS', 'PLOT 1 PASCO HOUSING SOCIETY NEAR EME SOCIETY CANAL ROAD');
define('GYM_LOGO', '/gym/logo/logo.png');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

/**
 * Calculate financial summary for all members in batch.
 * Keyed by member_id => ['balance' => ..., 'gross_total' => ..., 'total_paid' => ..., 'discount' => ..., 'status' => ...]
 */
function getAllMembersFinancialSummary($pdo) {
    $members = $pdo->query("SELECT id, registration_fee, trainer_fee, kids_fee, discount, monthly_fee, join_date FROM members")->fetchAll();
    $paidMap = $pdo->query("SELECT member_id, COALESCE(SUM(amount), 0) AS paid FROM member_payments GROUP BY member_id")->fetchAll(PDO::FETCH_KEY_PAIR);
    $planMap = $pdo->query("SELECT s.member_id, COALESCE(SUM(p.price), 0) AS total_plans FROM subscriptions s JOIN plans p ON p.id = s.plan_id GROUP BY s.member_id")->fetchAll(PDO::FETCH_KEY_PAIR);

    $now = new DateTime();
    $summaries = [];

    foreach ($members as $m) {
        $id = (int)$m['id'];
        $regFee = (float)($m['registration_fee'] ?? 0);
        $trainerFee = (float)($m['trainer_fee'] ?? 0);
        $kidsFee = (float)($m['kids_fee'] ?? 0);
        $discount = (float)($m['discount'] ?? 0);
        $monthlyFee = (float)($m['monthly_fee'] ?? 0);

        $monthlyFeeTotal = 0;
        if ($monthlyFee > 0) {
            $joinDateObj = new DateTime($m['join_date']);
            $months = (($now->format('Y') - $joinDateObj->format('Y')) * 12) + ($now->format('m') - $joinDateObj->format('m')) + 1;
            if ($months < 1) $months = 1;
            $monthlyFeeTotal = $monthlyFee * $months;
        }

        $planTotal = ($monthlyFee <= 0) ? (float)($planMap[$id] ?? 0) : 0;
        $grossTotal = $regFee + $trainerFee + $kidsFee + $monthlyFeeTotal + $planTotal;
        $netPayable = max(0, $grossTotal - $discount);
        $paid = (float)($paidMap[$id] ?? 0);
        $balance = $netPayable - $paid;

        $summaries[$id] = [
            'gross_total' => $grossTotal,
            'discount'    => $discount,
            'net_payable' => $netPayable,
            'total_paid'  => $paid,
            'balance'     => $balance,
            'status'      => ($balance > 0 ? 'due' : ($balance < 0 ? 'advance' : 'cleared')),
        ];
    }

    return $summaries;
}

/**
 * Calculate financial summary for a single member.
 */
function getMemberFinancialSummary($pdo, $memberOrId) {
    if (is_numeric($memberOrId)) {
        $stmt = $pdo->prepare('SELECT id, registration_fee, trainer_fee, kids_fee, discount, monthly_fee, join_date FROM members WHERE id = ?');
        $stmt->execute([(int)$memberOrId]);
        $m = $stmt->fetch();
        if (!$m) return null;
    } else {
        $m = $memberOrId;
    }

    $id = (int)$m['id'];
    $regFee = (float)($m['registration_fee'] ?? 0);
    $trainerFee = (float)($m['trainer_fee'] ?? 0);
    $kidsFee = (float)($m['kids_fee'] ?? 0);
    $discount = (float)($m['discount'] ?? 0);
    $monthlyFee = (float)($m['monthly_fee'] ?? 0);

    $now = new DateTime();
    $monthlyFeeTotal = 0;
    if ($monthlyFee > 0) {
        $joinDateObj = new DateTime($m['join_date']);
        $months = (($now->format('Y') - $joinDateObj->format('Y')) * 12) + ($now->format('m') - $joinDateObj->format('m')) + 1;
        if ($months < 1) $months = 1;
        $monthlyFeeTotal = $monthlyFee * $months;
    }

    $planTotal = 0;
    if ($monthlyFee <= 0) {
        $stmtSub = $pdo->prepare('SELECT COALESCE(SUM(p.price), 0) AS total_plans FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.member_id = ?');
        $stmtSub->execute([$id]);
        $planTotal = (float)$stmtSub->fetch()['total_plans'];
    }

    $stmtPay = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total_paid FROM member_payments WHERE member_id = ?');
    $stmtPay->execute([$id]);
    $paid = (float)$stmtPay->fetch()['total_paid'];

    $grossTotal = $regFee + $trainerFee + $kidsFee + $monthlyFeeTotal + $planTotal;
    $netPayable = max(0, $grossTotal - $discount);
    $balance = $netPayable - $paid;

    return [
        'gross_total' => $grossTotal,
        'discount'    => $discount,
        'net_payable' => $netPayable,
        'total_paid'  => $paid,
        'balance'     => $balance,
        'status'      => ($balance > 0 ? 'due' : ($balance < 0 ? 'advance' : 'cleared')),
    ];
}
