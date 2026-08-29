<?php
require __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid salary payment ID.');
}

$stmt = $pdo->prepare('SELECT sp.*, s.name AS staff_name, s.role, s.phone AS staff_phone, s.salary AS staff_salary FROM staff_salaries sp JOIN staff s ON s.id = sp.staff_id WHERE sp.id = ?');
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(404);
    exit('Salary payment not found.');
}

// Total paid to this staff member (all time)
$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS total FROM staff_salaries WHERE staff_id = ?');
$stmt->execute([$payment['staff_id']]);
$totalPaid = (float)$stmt->fetch()['total'];

// Total salary earned from join date
$stmt2 = $pdo->prepare('SELECT join_date FROM staff WHERE id = ?');
$stmt2->execute([$payment['staff_id']]);
$joinDate = $stmt2->fetchColumn();
$monthsWorked = 0;
if ($joinDate) {
    $start = new DateTime($joinDate);
    $end = new DateTime();
    $monthsWorked = ($end->format('Y') - $start->format('Y')) * 12 + ($end->format('m') - $start->format('m'));
    if ($monthsWorked < 1) $monthsWorked = 1;
}
$totalEarned = $payment['staff_salary'] * $monthsWorked;
$balance = $totalEarned - $totalPaid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - <?php echo htmlspecialchars($payment['staff_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #1a1a2e; padding: 30px; }
        .slip { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }

        .slip-header { background: linear-gradient(135deg, #1a1a2e, #16213e); color: #fff; text-align: center; padding: 28px 20px 22px; }
        .slip-header .gym-logo img { height: 80px; width: auto; object-fit: contain; margin-bottom: 10px; }
        .slip-header .gym-name { font-size: 22px; font-weight: 800; letter-spacing: 3px; }
        .slip-header .gym-contact { font-size: 11px; opacity: 0.85; margin-top: 6px; }
        .slip-header .slip-title { font-size: 14px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin-top: 14px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.2); color: #f7b731; }

        .slip-body { padding: 24px; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .info-item { padding: 12px 14px; background: #f8f9fa; border-radius: 10px; border-left: 3px solid #6c5ce7; }
        .info-item .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 4px; }
        .info-item .value { font-size: 15px; font-weight: 700; color: #1a1a2e; }
        .info-item.highlight { border-left-color: #00b894; background: #f0fdf4; }
        .info-item.accent { border-left-color: #f7b731; background: #fffdf0; }

        .amount-box { text-align: center; padding: 20px; margin: 20px 0; background: linear-gradient(135deg, #00b894, #00a381); border-radius: 12px; color: #fff; }
        .amount-box .amt-label { font-size: 11px; text-transform: uppercase; letter-spacing: 2px; opacity: 0.9; }
        .amount-box .amt-value { font-size: 32px; font-weight: 800; margin-top: 4px; }
        .amount-box .amt-words { font-size: 11px; opacity: 0.8; margin-top: 6px; }

        .balance-row { display: flex; justify-content: space-between; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; }
        .balance-row.due { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
        .balance-row.settled { background: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; }
        .balance-row.advance { background: #eff6ff; border: 1px solid #bfdbfe; color: #2563eb; }

        .divider { border: none; border-top: 1px dashed #ddd; margin: 16px 0; }

        .signatures { display: flex; justify-content: space-between; margin-top: 30px; padding-top: 20px; }
        .sig-box { text-align: center; width: 40%; }
        .sig-line { border-top: 1px solid #333; margin-top: 50px; padding-top: 6px; font-size: 11px; font-weight: 600; color: #555; }

        .slip-footer { text-align: center; padding: 14px; background: #f8f9fa; font-size: 10px; color: #999; border-top: 1px solid #eee; }

        .actions { text-align: center; margin-top: 20px; }
        .btn-print { background: #6c5ce7; color: #fff; border: none; padding: 12px 32px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .btn-print:hover { background: #5a4bd1; }

        @media print {
            body { background: #fff; padding: 0; }
            .slip { box-shadow: none; border-radius: 0; }
            .actions { display: none; }
            @page { size: A4; margin: 15mm; }
        }
    </style>
</head>
<body>
    <div class="slip">
        <div class="slip-header">
            <div class="gym-logo"><img src="<?php echo GYM_LOGO; ?>" alt="<?php echo htmlspecialchars(GYM_NAME); ?>" onerror="this.onerror=null; this.src='/gym/logo/The%20Compound%20Logo-01.png';"></div>
            <div class="gym-contact"><?php echo htmlspecialchars(GYM_PHONE); ?></div>
            <div class="gym-contact"><?php echo htmlspecialchars(GYM_ADDRESS); ?></div>
            <div class="slip-title">Salary Payment Slip</div>
        </div>

        <div class="slip-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Staff Name</div>
                    <div class="value"><?php echo htmlspecialchars($payment['staff_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Role</div>
                    <div class="value"><?php echo ucfirst(htmlspecialchars($payment['role'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Phone</div>
                    <div class="value"><?php echo htmlspecialchars($payment['staff_phone'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Monthly Salary</div>
                    <div class="value">Rs.<?php echo number_format($payment['staff_salary'], 0); ?></div>
                </div>
                <div class="info-item accent">
                    <div class="label">Salary For</div>
                    <div class="value"><?php echo date('F Y', strtotime($payment['salary_month'] . '-01')); ?></div>
                </div>
                <div class="info-item highlight">
                    <div class="label">Payment Date</div>
                    <div class="value"><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></div>
                </div>
            </div>

            <div class="amount-box">
                <div class="amt-label"><?php echo ($payment['payment_type'] ?? 'salary') === 'advance' ? 'Advance Amount' : 'Amount Paid'; ?></div>
                <div class="amt-value">Rs.<?php echo number_format($payment['amount'], 0); ?></div>
                <div class="amt-words">via <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></div>
            </div>

            <div class="balance-row <?php echo $balance > 0 ? 'due' : ($balance < 0 ? 'advance' : 'settled'); ?>">
                <span><strong>Lifetime Total Earned:</strong> Rs.<?php echo number_format($totalEarned, 0); ?></span>
                <span><strong>Lifetime Total Paid:</strong> Rs.<?php echo number_format($totalPaid, 0); ?></span>
                <span><strong><?php echo $balance > 0 ? 'Outstanding: Rs.' . number_format($balance, 0) : ($balance < 0 ? 'Advance: Rs.' . number_format(abs($balance), 0) : 'Settled'); ?></strong></span>
            </div>

            <?php if (!empty($payment['notes'])): ?>
                <hr class="divider">
                <div style="font-size:13px;color:#555;"><strong>Note:</strong> <?php echo htmlspecialchars($payment['notes']); ?></div>
            <?php endif; ?>

            <div class="signatures">
                <div class="sig-box">
                    <div class="sig-line">Staff Signature</div>
                </div>
                <div class="sig-box">
                    <div class="sig-line">Authorized By</div>
                </div>
            </div>
        </div>

        <div class="slip-footer">
            Printed on <?php echo date('d M Y, h:i A'); ?> | <?php echo htmlspecialchars(GYM_NAME); ?> Management System
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <div class="actions" style="display:flex; justify-content:center; gap:10px; flex-wrap:wrap;">
        <button class="btn-print" onclick="window.print();"><i class="fas fa-print me-1"></i>Print Salary Slip</button>
        <button class="btn-print" style="background:#0284c7;" onclick="downloadSalarySlipPDF();"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
        <a href="/gym/staff/salaries.php" class="btn-print" style="background:#fff; color:#333; border:1px solid #ccc; text-decoration:none;"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    function downloadSalarySlipPDF() {
        var element = document.querySelector('.slip');
        if (!element) return;

        var btn = event && event.target ? event.target.closest('button') : null;
        var originalHTML = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating PDF...';
        }

        var opt = {
            margin:       [8, 8, 8, 8],
            filename:     'Salary_Slip_<?php echo preg_replace('/[^A-Za-z0-9_-]/', '_', $payment['staff_name']) . '_' . $payment['salary_month']; ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save().then(function() {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }).catch(function(err) {
            console.error('PDF error:', err);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        });
    }
    </script>
</body>
</html>
