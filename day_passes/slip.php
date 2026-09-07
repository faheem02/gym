<?php
require __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid pass ID.');
}

$stmt = $pdo->prepare(
    "SELECT dp.*, m.name AS member_name, m.phone AS member_phone
     FROM day_passes dp
     LEFT JOIN members m ON m.id = dp.member_id
     WHERE dp.id = ?"
);
$stmt->execute([$id]);
$pass = $stmt->fetch();

if (!$pass) {
    http_response_code(404);
    exit('Day pass not found.');
}

$duration = '';
if ($pass['check_out_time']) {
    $mins = (strtotime($pass['check_out_time']) - strtotime($pass['check_in_time'])) / 60;
    $hrs = floor($mins / 60);
    $mins = $mins % 60;
    $duration = ($hrs > 0 ? $hrs . 'h ' : '') . $mins . 'm';
}

$typeLabels = [
    'gym' => 'Gym Access',
    'kids_play' => 'Kids Play Area',
    'both' => 'Gym + Kids Play',
];
$typeLabel = $typeLabels[$pass['pass_type']] ?? ucfirst($pass['pass_type']);
$autoprint = !empty($_GET['autoprint']);
$passNo = 'DP-' . str_pad($pass['id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Day Pass <?php echo htmlspecialchars($passNo); ?> - <?php echo htmlspecialchars(GYM_NAME); ?></title>
    <link rel="stylesheet" href="/gym/assets/vendor/fontawesome/css/all.min.css">
    <style>
        :root {
            --receipt-width: 80mm;
            --receipt-bg: #fff;
            --receipt-color: #000;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Courier New', 'Consolas', monospace, sans-serif;
            background: #e5e7eb;
            color: #111;
            padding: 30px 15px;
            -webkit-font-smoothing: antialiased;
        }

        .screen-container {
            max-width: 480px;
            margin: 0 auto;
        }

        /* Actions Bar */
        .actions-bar {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }
        .btn-act {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            font-family: 'Segoe UI', -apple-system, 'Inter', sans-serif;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }
        .btn-act-print { background: #111827; color: #fff; }
        .btn-act-print:hover { background: #000; }
        .btn-act-pdf { background: #0284c7; color: #fff; }
        .btn-act-pdf:hover { background: #0369a1; }
        .btn-act-new { background: #f59e0b; color: #fff; }
        .btn-act-new:hover { background: #d97706; }
        .btn-act-back { background: #fff; color: #374151; border-color: #d1d5db; }
        .btn-act-back:hover { background: #f3f4f6; }

        /* Thermal Receipt Card */
        .thermal-receipt {
            width: var(--receipt-width);
            max-width: 100%;
            margin: 0 auto;
            background: var(--receipt-bg);
            color: var(--receipt-color);
            padding: 16px 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-radius: 4px;
            font-size: 12px;
            line-height: 1.35;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 12px;
        }
        .receipt-header .logo-img {
            max-height: 55px;
            max-width: 170px;
            width: auto;
            display: inline-block;
            margin-bottom: 6px;
            object-fit: contain;
            filter: brightness(0);
            -webkit-filter: brightness(0);
        }
        .receipt-header .gym-name {
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .receipt-header .gym-info {
            font-size: 10.5px;
            margin-top: 2px;
            line-height: 1.3;
        }
        .receipt-header .receipt-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 6px;
            padding: 2px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
        }

        .receipt-meta {
            margin: 10px 0;
            font-size: 11px;
        }
        .receipt-meta .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }
        .receipt-meta .meta-label {
            font-weight: 600;
        }

        .divider-dashed {
            border: none;
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .divider-double {
            border: none;
            border-top: 2px solid #000;
            margin: 8px 0;
        }

        .pass-details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin: 6px 0;
        }
        .pass-details-table td {
            padding: 4px 0;
            vertical-align: top;
        }
        .pass-details-table td.lbl {
            font-weight: 600;
            width: 42%;
        }
        .pass-details-table td.val {
            text-align: right;
            font-weight: 500;
        }

        /* Totals */
        .totals-section {
            margin: 8px 0;
            font-size: 11.5px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .totals-row.grand-total {
            font-size: 13px;
            font-weight: 700;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 5px 0;
            margin: 6px 0;
        }

        .status-badge {
            font-family: 'Segoe UI', -apple-system, 'Inter', sans-serif;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 1px 6px;
            border: 1px solid #000;
            display: inline-block;
        }

        /* Footer */
        .receipt-footer {
            text-align: center;
            margin-top: 12px;
            font-size: 10.5px;
            line-height: 1.4;
        }
        .barcode-wrap {
            text-align: center;
            margin: 10px 0 6px;
            font-family: 'Segoe UI', -apple-system, 'Inter', sans-serif;
            font-weight: 700;
            letter-spacing: 2px;
            font-size: 12px;
        }

        /* Print Media */
        @media print {
            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }
            .actions-bar {
                display: none !important;
            }
            .screen-container {
                max-width: 100%;
                margin: 0;
            }
            .thermal-receipt {
                width: 100%;
                max-width: 100%;
                box-shadow: none;
                border-radius: 0;
                padding: 0;
            }
            @page {
                size: 80mm auto;
                margin: 3mm 4mm;
            }
        }
    </style>
</head>
<body>

<div class="screen-container">

    <!-- Actions Bar (Screen only) -->
    <div class="actions-bar">
        <button type="button" class="btn-act btn-act-print" onclick="window.print();">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <button type="button" class="btn-act btn-act-pdf" onclick="downloadThermalPDF();">
            <i class="fas fa-file-pdf"></i> Download PDF
        </button>
        <a href="/gym/day_passes/add.php" class="btn-act btn-act-new">
            <i class="fas fa-plus"></i> Issue Pass
        </a>
    </div>

    <!-- Thermal Receipt Printable Area -->
    <div class="thermal-receipt" id="thermalReceiptArea">

        <div class="receipt-header">
            <div class="logo-wrap" style="text-align: center; margin-bottom: 6px;">
                <img src="<?php echo GYM_LOGO; ?>" alt="<?php echo htmlspecialchars(GYM_NAME); ?>" class="logo-img" onerror="this.onerror=null; this.src='/gym/logo/The%20Compound%20Logo-01.png';">
            </div>
            <div class="gym-name"><?php echo htmlspecialchars(GYM_NAME); ?></div>
            <div class="gym-info"><?php echo htmlspecialchars(GYM_PHONE); ?></div>
            <div class="gym-info"><?php echo htmlspecialchars(GYM_ADDRESS); ?></div>
            <div class="receipt-title">DAY PASS RECEIPT</div>
        </div>

        <div class="receipt-meta">
            <div class="meta-row">
                <span class="meta-label">Pass #:</span>
                <span><?php echo htmlspecialchars($passNo); ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Date:</span>
                <span><?php echo date('d-m-Y', strtotime($pass['pass_date'])); ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Check-in Time:</span>
                <span><?php echo date('h:i A', strtotime($pass['check_in_time'])); ?></span>
            </div>
            <?php if ($pass['check_out_time']): ?>
            <div class="meta-row">
                <span class="meta-label">Check-out Time:</span>
                <span><?php echo date('h:i A', strtotime($pass['check_out_time'])); ?> (<?php echo $duration; ?>)</span>
            </div>
            <?php endif; ?>
        </div>

        <hr class="divider-dashed">

        <table class="pass-details-table">
            <tr>
                <td class="lbl">Visitor Name:</td>
                <td class="val" style="font-weight:700;"><?php echo htmlspecialchars($pass['visitor_name']); ?></td>
            </tr>
            <?php if (!empty($pass['phone'])): ?>
            <tr>
                <td class="lbl">Phone:</td>
                <td class="val"><?php echo htmlspecialchars($pass['phone']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="lbl">Pass Type:</td>
                <td class="val" style="font-weight:700;"><?php echo htmlspecialchars($typeLabel); ?></td>
            </tr>
            <tr>
                <td class="lbl">Visitor Type:</td>
                <td class="val">
                    <?php if (!empty($pass['member_name'])): ?>
                        Guest of <?php echo htmlspecialchars($pass['member_name']); ?>
                    <?php else: ?>
                        Walk-in Visitor
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Status:</td>
                <td class="val">
                    <?php if ($pass['check_out_time']): ?>
                        <span class="status-badge">Completed</span>
                    <?php else: ?>
                        <span class="status-badge">Active (Inside)</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <div class="totals-section">
            <div class="totals-row grand-total">
                <span>AMOUNT PAID:</span>
                <span>Rs. <?php echo number_format($pass['amount'], 2); ?></span>
            </div>
        </div>

        <?php if (!empty($pass['notes'])): ?>
        <hr class="divider-dashed">
        <div style="font-size:10.5px; margin: 4px 0;">
            <strong>Note:</strong> <?php echo htmlspecialchars($pass['notes']); ?>
        </div>
        <?php endif; ?>

        <div class="receipt-footer">
            <hr style="border:none; border-top:1px solid #000; margin:10px 0 6px;">
            <p style="font-weight:600; font-size:10px; margin-bottom:4px;">
                It's provisional bill and above mentioned price is subject to GST
            </p>
            <p style="font-size:9.5px; margin-bottom:2px;">
                Date: <strong><?php echo date('Y-m-d', strtotime($pass['created_at'] ?? $pass['pass_date'])); ?></strong> | Time: <strong><?php echo date('H:i:s', strtotime($pass['created_at'] ?? 'now')); ?></strong>
            </p>
            <p style="font-size:9.5px; margin-bottom:4px;">
                Cashier Name: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong>
            </p>
            <hr style="border:none; border-top:1px dashed #000; margin:6px 0 6px;">
            <p style="margin-bottom:2px;">Thank you for your visit!</p>
            <p style="margin-bottom:4px;">Please keep this receipt for your records.</p>
            <p style="font-size:9px; color:#444; margin-top:2px;">
                Powered by <?php echo htmlspecialchars(GYM_NAME); ?>
            </p>
        </div>

    </div>

</div>

<!-- Include html2pdf.js CDN -->
<script src="/gym/assets/vendor/html2pdf/html2pdf.bundle.min.js"></script>
<script>
function downloadThermalPDF() {
    var element = document.getElementById('thermalReceiptArea');
    var opt = {
        margin:       [4, 2, 4, 2],
        filename:     'DayPass_<?php echo htmlspecialchars($passNo); ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2.5, useCORS: true, letterRendering: true },
        jsPDF:        { unit: 'mm', format: [80, 180], orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}

<?php if ($autoprint): ?>
window.addEventListener('DOMContentLoaded', function () {
    setTimeout(function() {
        window.print();
    }, 400);
});
<?php endif; ?>
</script>

</body>
</html>
