<?php
require __DIR__ . '/../../config.php';

$id = (int)($_GET['id'] ?? 0);
$rid = trim($_GET['rid'] ?? '');

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, m.name AS member_name, m.phone AS member_phone
        FROM canteen_sales s
        LEFT JOIN members m ON m.id = s.member_id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
} elseif ($rid !== '') {
    $stmt = $pdo->prepare("
        SELECT s.*, m.name AS member_name, m.phone AS member_phone
        FROM canteen_sales s
        LEFT JOIN members m ON m.id = s.member_id
        WHERE s.receipt_no = ?
    ");
    $stmt->execute([$rid]);
} else {
    http_response_code(400);
    exit('Invalid sale reference.');
}

$sale = $stmt->fetch();
if (!$sale) {
    http_response_code(404);
    exit('Sale record not found.');
}

$stmt = $pdo->prepare("
    SELECT si.*, cp.name AS product_name, cp.unit
    FROM canteen_sale_items si
    LEFT JOIN canteen_products cp ON cp.id = si.product_id
    WHERE si.sale_id = ?
");
$stmt->execute([$sale['id']]);
$items = $stmt->fetchAll();

$methodLabels = [
    'cash'   => 'Cash',
    'card'   => 'Card',
    'online' => 'Online',
];
$paymentLabel = $methodLabels[$sale['payment_method']] ?? ucfirst($sale['payment_method']);
$receivedAmount = (float)$sale['received_amount'];
$finalAmount = (float)$sale['final_amount'];
$change = max(0, $receivedAmount - $finalAmount);
$remainingBalance = max(0, $finalAmount - $receivedAmount);
$autoprint = !empty($_GET['autoprint']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?php echo htmlspecialchars($sale['receipt_no']); ?> - <?php echo htmlspecialchars(GYM_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --receipt-width: 80mm;
            --receipt-bg: #fff;
            --receipt-color: #000;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Courier Prime', Courier, monospace, sans-serif;
            background: #e5e7eb;
            color: #111;
            padding: 30px 15px;
            -webkit-font-smoothing: antialiased;
        }

        .screen-container {
            max-width: 480px;
            margin: 0 auto;
        }

        /* ── Actions Bar ── */
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
            font-family: 'Inter', sans-serif;
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
        .btn-act-pos { background: #059669; color: #fff; }
        .btn-act-pos:hover { background: #047857; }
        .btn-act-back { background: #fff; color: #374151; border-color: #d1d5db; }
        .btn-act-back:hover { background: #f3f4f6; }

        /* ── Thermal Receipt Card ── */
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

        /* ── Items Table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin: 6px 0;
        }
        .items-table th {
            text-align: left;
            padding: 4px 0;
            border-bottom: 1px dashed #000;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
        }
        .items-table th.th-qty { text-align: center; width: 35px; }
        .items-table th.th-price { text-align: right; width: 55px; }
        .items-table th.th-total { text-align: right; width: 60px; }

        .items-table td {
            padding: 4px 0;
            vertical-align: top;
        }
        .items-table td.td-qty { text-align: center; }
        .items-table td.td-price { text-align: right; }
        .items-table td.td-total { text-align: right; font-weight: 700; }
        .items-table .item-name {
            font-weight: 600;
            word-break: break-word;
        }

        /* ── Totals ── */
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
            padding: 4px 0;
            margin: 6px 0;
        }

        /* ── Footer ── */
        .receipt-footer {
            text-align: center;
            margin-top: 14px;
            font-size: 10.5px;
            line-height: 1.4;
        }
        .barcode-wrap {
            text-align: center;
            margin: 10px 0 6px;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            letter-spacing: 2px;
            font-size: 12px;
        }

        /* ── Print Media ── */
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
        <a href="/gym/canteen/pos/index.php" class="btn-act btn-act-pos">
            <i class="fas fa-plus"></i> New Sale
        </a>
        <a href="/gym/canteen/sales/" class="btn-act btn-act-back">
            <i class="fas fa-list"></i> All Sales
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
            <div class="receipt-title">POS RECEIPT</div>
        </div>

        <div class="receipt-meta">
            <div class="meta-row">
                <span class="meta-label">Receipt #:</span>
                <span><?php echo htmlspecialchars($sale['receipt_no'] ?? ('SALE-' . $sale['id'])); ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Date/Time:</span>
                <span><?php echo date('d-m-Y h:i A', strtotime($sale['created_at'] ?? $sale['sale_date'])); ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Customer:</span>
                <span>
                    <?php if (!empty($sale['member_id'])): ?>
                        <?php echo htmlspecialchars($sale['member_name'] ?? 'Member #' . $sale['member_id']); ?> (Member)
                    <?php else: ?>
                        <?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Payment Mode:</span>
                <span><?php echo htmlspecialchars($paymentLabel); ?></span>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="th-qty">Qty</th>
                    <th class="th-price">Rate</th>
                    <th class="th-total">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                    <td class="item-name"><?php echo htmlspecialchars($it['product_name'] ?? 'Item'); ?></td>
                    <td class="td-qty"><?php echo $it['quantity']; ?></td>
                    <td class="td-price"><?php echo number_format($it['unit_price'], 0); ?></td>
                    <td class="td-total"><?php echo number_format($it['subtotal'], 0); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <div class="totals-row">
                <span>Subtotal:</span>
                <span>Rs. <?php echo number_format($sale['total_amount'], 2); ?></span>
            </div>
            <?php if ((float)$sale['discount'] > 0): ?>
            <div class="totals-row">
                <span>Discount:</span>
                <span>- Rs. <?php echo number_format($sale['discount'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="totals-row grand-total">
                <span>NET TOTAL:</span>
                <span>Rs. <?php echo number_format($finalAmount, 2); ?></span>
            </div>
            <div class="totals-row">
                <span>Amount Paid:</span>
                <span>Rs. <?php echo number_format($receivedAmount, 2); ?></span>
            </div>
            <?php if ($remainingBalance > 0): ?>
            <div class="totals-row" style="font-weight:700; color:#b91c1c; border-top:1px dashed #000; padding-top:4px; margin-top:4px;">
                <span>REMAINING BALANCE:</span>
                <span>Rs. <?php echo number_format($remainingBalance, 2); ?></span>
            </div>
            <?php elseif ($change > 0): ?>
            <div class="totals-row">
                <span>Change Returned:</span>
                <span>Rs. <?php echo number_format($change, 2); ?></span>
            </div>
            <?php else: ?>
            <div class="totals-row" style="font-weight:600;">
                <span>Remaining Balance:</span>
                <span>Rs. 0.00 (Cleared)</span>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($sale['notes'])): ?>
        <hr class="divider-dashed">
        <div style="font-size:10px; margin: 4px 0;">
            <strong>Note:</strong> <?php echo htmlspecialchars($sale['notes']); ?>
        </div>
        <?php endif; ?>

        <div class="receipt-footer">
            <hr style="border:none; border-top:1px solid #000; margin:10px 0 6px;">
            <p style="font-weight:600; font-size:10px; margin-bottom:4px;">
                It's provisional bill and above mentioned price is subject to GST
            </p>
            <p style="font-size:9.5px; margin-bottom:2px;">
                Date: <strong><?php echo date('Y-m-d', strtotime($sale['created_at'] ?? $sale['sale_date'])); ?></strong> | Time: <strong><?php echo date('H:i:s', strtotime($sale['created_at'] ?? 'now')); ?></strong>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadThermalPDF() {
    var element = document.getElementById('thermalReceiptArea');
    var opt = {
        margin:       [4, 2, 4, 2],
        filename:     'Receipt_<?php echo htmlspecialchars($sale['receipt_no'] ?? $sale['id']); ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2.5, useCORS: true, letterRendering: true },
        jsPDF:        { unit: 'mm', format: [80, 200], orientation: 'portrait' }
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
