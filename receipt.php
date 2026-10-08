<?php
require_once __DIR__ . '/dbconfig.php';

$shop = [
    'name_mm' => 'ကောင်းကံလင်း',
    'name_en' => 'Kaung Kan Lin Candle Shop',
    'phone'   => '',
    'address' => '',
];

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$st->execute([$id]);
$order = $st->fetch();
if (!$order) {
    http_response_code(404);
    exit('<div style="font-family:sans-serif;padding:2rem">Order not found. <a href="orders.php">Back to orders</a></div>');
}

$it = $pdo->prepare('SELECT oi.product_id, oi.quantity, oi.unit_price_mmk, oi.subtotal_revenue_mmk,
                            p.name_en, p.name_mm, p.description_en, p.description_mm
                     FROM order_items oi JOIN products p ON p.id = oi.product_id
                     WHERE oi.order_id = ? ORDER BY oi.id');
$it->execute([$order['id']]);
$items = $it->fetchAll();

$units = array_sum(array_column($items, 'quantity'));
$dateStr = date('d M Y, H:i', strtotime($order['order_date']));
$status = $order['status'];
$printMode = isset($_GET['print']);
$size = ($_GET['size'] ?? '') === 'thermal' ? 'thermal' : 'a5';

$txt = [];
$txt[] = $shop['name_mm'] . ' ' . $shop['name_en'];
if ($shop['phone'] !== '') {
    $txt[] = $shop['phone'];
}
$txt[] = 'Receipt ' . $order['order_number'];
$txt[] = $dateStr;
$txt[] = 'Customer: ' . $order['customer_name'] . ' (' . $order['customer_phone'] . ')';
$txt[] = '------------------------';
foreach ($items as $i) {
    $txt[] = $i['name_en'] . ' (' . $i['description_en'] . ')';
    $txt[] = '  ' . (int)$i['quantity'] . ' x ' . number_format((float)$i['unit_price_mmk']) . ' = ' . mmk($i['subtotal_revenue_mmk']);
}
$txt[] = '------------------------';
$txt[] = 'Total: ' . mmk($order['total_revenue_mmk']);
if ($status !== 'Completed') {
    $txt[] = 'Status: ' . $status;
}
$txt[] = 'ကျေးဇူးတင်ပါသည် / Thank you!';
$receiptText = implode("\n", $txt);
?>
<!DOCTYPE html>
<html lang="my">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?= e($order['order_number']) ?> | <?= e($shop['name_mm']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Myanmar:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page {
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #FAF7F5;
            color: #231F20;
            font-family: "Plus Jakarta Sans", "Noto Sans Myanmar", sans-serif;
            line-height: 1.5;
        }

        .toolbar {
            max-width: 560px;
            margin: 0 auto;
            padding: 1rem 1rem 0;
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            align-items: center;
        }

        .toolbar .spacer {
            flex: 1;
        }

        .btn {
            background: #C86D7C;
            color: #fff;
            border: 1px solid #C86D7C;
            border-radius: .75rem;
            padding: .5rem 1rem;
            font: 600 .875rem "Plus Jakarta Sans", "Noto Sans Myanmar", sans-serif;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            cursor: pointer;
            text-decoration: none;
        }

        .btn:hover {
            background: #B35B6A;
        }

        .btn-ghost {
            background: #fff;
            color: #C86D7C;
            border-color: #E8B4B8;
        }

        .btn-ghost:hover {
            background: #FBE8EC;
        }

        .btn:focus-visible,
        .seg button:focus-visible {
            outline: 2px solid #B35B6A;
            outline-offset: 2px;
        }

        .seg {
            display: inline-flex;
            background: #fff;
            border: 1px solid #FBE8EC;
            border-radius: 999px;
            padding: .2rem;
        }

        .seg button {
            border: 0;
            background: transparent;
            color: #6F6164;
            font: 600 .8rem "Plus Jakarta Sans", sans-serif;
            padding: .35rem .8rem;
            border-radius: 999px;
            cursor: pointer;
        }

        .seg button[aria-pressed="true"] {
            background: #C86D7C;
            color: #fff;
        }

        .sheet {
            max-width: 560px;
            margin: 1rem auto 2rem;
            background: #fff;
            border: 1px solid #FBE8EC;
            border-radius: 1rem;
            padding: 2rem 2rem 1.5rem;
        }

        .head {
            text-align: center;
        }

        .mark {
            display: block;
            margin: 0 auto .5rem;
        }

        .shop-mm {
            font-size: 1.5rem;
            font-weight: 700;
            color: #C86D7C;
            line-height: 1.4;
        }

        .shop-en {
            font-size: .85rem;
            color: #6F6164;
            font-weight: 500;
        }

        .shop-meta {
            font-size: .78rem;
            color: #6F6164;
        }

        .rule {
            border: 0;
            border-top: 1.5px dashed #E8B4B8;
            margin: 1.25rem 0;
        }

        .banner {
            text-align: center;
            font-weight: 700;
            color: #B35B6A;
            border: 2px solid #B35B6A;
            border-radius: .5rem;
            padding: .25rem;
            margin-bottom: 1rem;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .meta td {
            font-size: .85rem;
            padding: .1rem 0;
        }

        .meta td:first-child {
            color: #6F6164;
            padding-right: 1rem;
        }

        .v {
            text-align: right;
            font-weight: 600;
        }

        .lines {
            font-size: .875rem;
        }

        .lines th {
            text-align: left;
            font-size: .75rem;
            font-weight: 600;
            color: #6F6164;
            padding: 0 0 .5rem;
        }

        .lines th.num,
        .lines td.num {
            text-align: right;
            white-space: nowrap;
        }

        .lines td {
            padding: .55rem 0;
            border-top: 1px solid #FBE8EC;
            vertical-align: top;
        }

        .lines td.qty {
            padding-right: .75rem;
        }

        .item-name {
            font-weight: 600;
        }

        .item-sub {
            font-size: .75rem;
            color: #6F6164;
        }

        .total {
            margin-top: .25rem;
            border-top: 2px solid #231F20;
        }

        .total td {
            padding-top: .9rem;
        }

        .total .label {
            font-weight: 600;
        }

        .total .amount {
            text-align: right;
            font-size: 1.6rem;
            font-weight: 700;
            color: #C86D7C;
        }

        .units {
            font-size: .78rem;
            color: #6F6164;
            text-align: right;
        }

        .note {
            margin-top: 1rem;
            padding: .6rem .8rem;
            border: 1px solid #E8B4B8;
            border-radius: .6rem;
            font-size: .8rem;
            color: #B35B6A;
        }

        .thanks {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .9rem;
            font-weight: 600;
        }

        .thanks small {
            display: block;
            font-weight: 400;
            color: #6F6164;
        }

        .narrow {
            display: none;
        }

        .n-item {
            padding: .45rem 0;
            border-top: 1px solid #FBE8EC;
        }

        .n-item table td {
            padding: 0;
        }

        body.thermal .wide {
            display: none;
        }

        body.thermal .narrow {
            display: block;
        }

        body.thermal .sheet {
            max-width: 80mm;
            padding: 1rem .8rem;
            font-size: 12px;
            border-radius: .5rem;
        }

        body.thermal .shop-mm {
            font-size: 1.25rem;
        }

        body.thermal .rule {
            margin: .8rem 0;
        }

        body.thermal .total .amount {
            font-size: 1.3rem;
        }

        body.thermal .thanks {
            margin-top: 1rem;
        }

        @media print {

            html,
            body {
                background: #fff;
            }

            .no-print {
                display: none !important;
            }

            .sheet {
                margin: 0 auto;
                border: 0;
                border-radius: 0;
                max-width: none;
                padding: 0;
            }

            body.thermal .sheet {
                max-width: 80mm;
                margin: 0;
            }
        }
    </style>
</head>

<body class="<?= $size === 'thermal' ? 'thermal' : '' ?>">
    <div class="toolbar no-print">
        <?php if ($printMode): ?>
            <a class="btn btn-ghost" href="receipt.php?id=<?= (int)$order['id'] ?>"><i class="fa-solid fa-arrow-left"></i> Back</a>
            <span class="spacer"></span>
            <button type="button" class="btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
        <?php else: ?>
            <a class="btn btn-ghost" href="orders.php"><i class="fa-solid fa-arrow-left"></i> Orders</a>
            <span class="spacer"></span>
            <div class="seg" role="group" aria-label="Paper size">
                <button type="button" id="sizeA5" aria-pressed="true" onclick="setSize('a5')">Standard</button>
                <button type="button" id="sizeThermal" aria-pressed="false" onclick="setSize('thermal')">80 mm roll</button>
            </div>
            <button type="button" class="btn btn-ghost" id="copyBtn" onclick="copyText()"><i class="fa-regular fa-copy"></i> Copy text</button>
            <button type="button" class="btn btn-ghost" id="imgBtn" onclick="saveImage()"><i class="fa-regular fa-image"></i> Save image</button>
            <a class="btn" id="printLink" href="receipt.php?id=<?= (int)$order['id'] ?>&amp;print=1&amp;size=a5"><i class="fa-solid fa-print"></i> Print / Save PDF</a>
        <?php endif; ?>
    </div>

    <main class="sheet" id="receipt">
        <?php if ($status === 'Cancelled'): ?><div class="banner">Cancelled</div><?php endif; ?>

        <header class="head">
            <svg class="mark" width="44" height="44" viewBox="0 0 44 44" aria-hidden="true">
                <circle cx="22" cy="22" r="22" fill="#C86D7C" />
                <path d="M22 9c0 0-5 5-5 10 0 2.8 2.2 5 5 5s5-2.2 5-5c0-5-5-10-5-10z" fill="#FFF7AD" stroke="#fff" stroke-width="1.5" stroke-linejoin="round" />
                <path d="M22 25v9M17 34h10" stroke="#fff" stroke-width="2" stroke-linecap="round" fill="none" />
            </svg>
            <div class="shop-mm"><?= e($shop['name_mm']) ?></div>
            <div class="shop-en"><?= e($shop['name_en']) ?></div>
            <?php if ($shop['address'] !== ''): ?><div class="shop-meta"><?= e($shop['address']) ?></div><?php endif; ?>
            <?php if ($shop['phone'] !== ''): ?><div class="shop-meta"><?= e($shop['phone']) ?></div><?php endif; ?>
        </header>

        <hr class="rule">

        <table class="meta">
            <tr>
                <td>Receipt no.</td>
                <td class="v"><?= e($order['order_number']) ?></td>
            </tr>
            <tr>
                <td>Date</td>
                <td class="v"><?= e($dateStr) ?></td>
            </tr>
            <tr>
                <td>Customer</td>
                <td class="v"><?= e($order['customer_name']) ?></td>
            </tr>
            <tr>
                <td>Phone</td>
                <td class="v"><?= e($order['customer_phone']) ?></td>
            </tr>
            <tr>
                <td>Ordered via</td>
                <td class="v"><?= e($order['channel']) ?></td>
            </tr>
        </table>

        <hr class="rule">

        <table class="lines wide">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th class="num">Price</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i): ?>
                    <tr>
                        <td>
                            <div class="item-name"><?= e($i['name_en']) ?></div>
                            <div class="item-sub"><?= e($i['name_mm']) ?> · <?= e($i['description_mm'] ?: $i['description_en']) ?></div>
                        </td>
                        <td class="num qty"><?= (int)$i['quantity'] ?></td>
                        <td class="num"><?= number_format((float)$i['unit_price_mmk']) ?></td>
                        <td class="num"><?= number_format((float)$i['subtotal_revenue_mmk']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="narrow">
            <?php foreach ($items as $i): ?>
                <div class="n-item">
                    <div class="item-name"><?= e($i['name_en']) ?></div>
                    <div class="item-sub"><?= e($i['name_mm']) ?> · <?= e($i['description_mm'] ?: $i['description_en']) ?></div>
                    <table>
                        <tr>
                            <td><?= (int)$i['quantity'] ?> × <?= number_format((float)$i['unit_price_mmk']) ?></td>
                            <td class="v"><?= number_format((float)$i['subtotal_revenue_mmk']) ?></td>
                        </tr>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>

        <table class="total">
            <tr>
                <td class="label">Total</td>
                <td class="amount"><?= e(mmk($order['total_revenue_mmk'])) ?></td>
            </tr>
        </table>
        <div class="units"><?= (int)$units ?> <?= $units === 1 ? 'pack' : 'packs' ?> · <?= count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?></div>

        <?php if ($status === 'Pending'): ?>
            <div class="note">This order is still pending. The receipt is final once the order is completed.</div>
        <?php elseif ($status === 'Cancelled'): ?>
            <div class="note">This order was cancelled.</div>
        <?php endif; ?>

        <div class="thanks">ကျေးဇူးတင်ပါသည်<small>Thank you for your order.</small></div>
    </main>

    <?php if (!$printMode): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <?php endif; ?>
    <script>
        const RECEIPT_TEXT = <?= json_encode($receiptText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const ORDER_NO = <?= json_encode($order['order_number']) ?>;
        const ORDER_ID = <?= (int)$order['id'] ?>;
        const PRINT_MODE = <?= $printMode ? 'true' : 'false' ?>;

        function setSize(mode) {
            document.body.classList.toggle('thermal', mode === 'thermal');
            document.getElementById('sizeA5').setAttribute('aria-pressed', mode === 'a5');
            document.getElementById('sizeThermal').setAttribute('aria-pressed', mode === 'thermal');
            document.getElementById('printLink').href = 'receipt.php?id=' + ORDER_ID + '&print=1&size=' + mode;
            try {
                localStorage.setItem('receiptSize', mode);
            } catch (e) {}
        }

        async function copyText() {
            const btn = document.getElementById('copyBtn');
            const original = btn.innerHTML;
            try {
                await navigator.clipboard.writeText(RECEIPT_TEXT);
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
            } catch (e) {
                const ta = document.createElement('textarea');
                ta.value = RECEIPT_TEXT;
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy');
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
                } catch (e2) {
                    btn.innerHTML = 'Copy failed';
                }
                ta.remove();
            }
            setTimeout(() => btn.innerHTML = original, 1800);
        }

        async function saveImage() {
            const btn = document.getElementById('imgBtn');
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Preparing…';
            try {
                if (document.fonts && document.fonts.ready) {
                    await document.fonts.ready;
                }
                const canvas = await html2canvas(document.getElementById('receipt'), {
                    scale: 2,
                    backgroundColor: '#ffffff'
                });
                const blob = await new Promise(res => canvas.toBlob(res, 'image/png'));
                const name = ORDER_NO + '.png';
                const file = new File([blob], name, {
                    type: 'image/png'
                });
                if (navigator.canShare && navigator.canShare({
                        files: [file]
                    })) {
                    try {
                        await navigator.share({
                            files: [file],
                            title: name
                        });
                        btn.innerHTML = original;
                        return;
                    } catch (e) {
                        if (e.name === 'AbortError') {
                            btn.innerHTML = original;
                            return;
                        }
                    }
                }
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = name;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(a.href), 2000);
            } catch (e) {
                alert('Could not create the image. Try Print / Save PDF instead.');
            }
            btn.innerHTML = original;
        }

        if (PRINT_MODE) {
            const ready = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
            ready.then(() => setTimeout(() => window.print(), 300));
        } else {
            let saved = 'a5';
            try {
                saved = localStorage.getItem('receiptSize') === 'thermal' ? 'thermal' : 'a5';
            } catch (e) {}
            setSize(saved);
        }
    </script>
</body>

</html>