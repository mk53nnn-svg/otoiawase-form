<?php
session_start();
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('DB接続エラー：' . $e->getMessage());
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: dashboard.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM inquiries WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) { header('Location: dashboard.php'); exit; }

$items = [];
if ($row['items']) $items = json_decode($row['items'], true) ?? [];

// 受注日
$order_date = date('Y年m月d日', strtotime($row['created_at']));
$order_date_short = date('n月j日', strtotime($row['created_at']));

// 商品を左9行・右9行に分割
$left_items  = array_slice($items, 0, 9);
$right_items = array_slice($items, 9, 9);

// 左右それぞれ9行に満たない分を空行で埋める
while (count($left_items)  < 9) $left_items[]  = ['name'=>'','code'=>'','qty'=>''];
while (count($right_items) < 9) $right_items[] = ['name'=>'','code'=>'','qty'=>''];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>売上受領伝票 - <?= htmlspecialchars($row['inquiry_no']) ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
  font-family: 'MS Mincho', 'Yu Mincho', serif;
  font-size: 11px;
  background: #fff;
}

/* 印刷ボタンエリア（印刷時は非表示） */
.print-controls {
  padding: 12px 20px;
  background: #f2f5fb;
  display: flex;
  gap: 10px;
  align-items: center;
}
.btn-print {
  background: #0d47a1;
  color: #fff;
  border: none;
  padding: 10px 24px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}
.btn-back {
  background: #607d8b;
  color: #fff;
  border: none;
  padding: 10px 24px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
}

/* 伝票本体 */
.slip {
  width: 210mm;
  min-height: 148mm;
  padding: 6mm 8mm;
  margin: 10px auto;
  background: #fff;
  border: 1px solid #ccc;
}

/* ヘッダー部 */
.slip-header {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 0;
  margin-bottom: 2mm;
}
.slip-title {
  text-align: center;
  font-size: 18px;
  font-weight: 700;
  letter-spacing: .1em;
  padding-top: 2mm;
}
.header-left {
  font-size: 11px;
}
.header-right {
  display: grid;
  grid-template-columns: 1fr 1fr;
  border: 1px solid #000;
  font-size: 11px;
}
.header-right-cell {
  padding: 1mm 2mm;
  border-right: 1px solid #000;
  font-weight: 700;
}
.header-right-cell:last-child { border-right: none; }
.header-right-val {
  padding: 1mm 2mm;
  border-right: 1px solid #000;
  min-height: 12mm;
}
.header-right-val:last-child { border-right: none; }

/* 園コード */
.garden-code {
  font-size: 10px;
  margin-bottom: 1mm;
}
.garden-code-line {
  border-bottom: 1px solid #000;
  width: 40mm;
  height: 6mm;
  display: inline-block;
}

/* 宛名エリア */
.address-area {
  display: grid;
  grid-template-columns: 1fr 1fr;
  margin-bottom: 1mm;
  gap: 0;
}
.address-left {
  padding: 1mm 0;
}
.garden-types {
  font-size: 10px;
  line-height: 1.8;
  text-align: center;
}
.garden-name-line {
  font-size: 13px;
  font-weight: 700;
  border-bottom: 1px solid #000;
  min-width: 50mm;
  display: inline-block;
  padding-bottom: 1mm;
}
.sama { font-size: 13px; margin-left: 1mm; }

/* 日付・受発注エリア */
.date-order-area {
  display: grid;
  grid-template-columns: 1fr 1fr;
  border-top: 1px solid #000;
  border-bottom: 1px solid #000;
  margin-bottom: 1mm;
}
.date-area {
  padding: 1mm 2mm;
  font-size: 12px;
  letter-spacing: .2em;
  border-right: 1px solid #000;
  display: flex;
  align-items: center;
  gap: 3mm;
}
.date-blank {
  border-bottom: 1px solid #000;
  display: inline-block;
  width: 8mm;
  height: 5mm;
}
.order-area {
  font-size: 10px;
}
.order-row {
  display: grid;
  grid-template-columns: 12mm 1fr 20mm 20mm;
  border-bottom: 1px solid #000;
  align-items: center;
}
.order-row:last-child { border-bottom: none; }
.order-label {
  padding: 1mm 1mm;
  border-right: 1px solid #000;
  font-weight: 700;
  text-align: center;
}
.order-val {
  padding: 1mm 2mm;
  border-right: 1px solid #000;
}
.order-staff-label {
  padding: 1mm 1mm;
  border-right: 1px solid #000;
  font-weight: 700;
  text-align: center;
}
.order-staff-val {
  padding: 1mm 1mm;
  min-width: 16mm;
}

/* 商品テーブル */
.items-area {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
  border: 1px solid #000;
}
.items-col {
  border-right: 1px solid #000;
}
.items-col:last-child { border-right: none; }
.items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 10px;
}
.items-table th {
  border-bottom: 1px solid #000;
  border-right: 1px solid #000;
  padding: 1mm;
  text-align: center;
  font-weight: 700;
  letter-spacing: .1em;
  background: #fff;
}
.items-table th:last-child { border-right: none; }
.items-table td {
  border-bottom: 1px solid #000;
  border-right: 1px solid #000;
  padding: 1mm 1mm;
  height: 7mm;
  vertical-align: middle;
}
.items-table td:last-child { border-right: none; }
.items-table tr:last-child td { border-bottom: none; }
.item-name { width: 42%; }
.item-qty  { width: 12%; text-align: center; }
.item-price{ width: 18%; }
.item-total{ width: 18%; }

/* フッター */
.slip-footer {
  display: grid;
  grid-template-columns: 1fr auto;
  border: 1px solid #000;
  border-top: none;
  margin-top: 0;
}
.footer-note {
  padding: 1mm 2mm;
  font-size: 10px;
  border-right: 1px solid #000;
}
.footer-total {
  padding: 1mm 4mm;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .1em;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 8mm;
}
.total-line {
  border-bottom: 1px solid #000;
  width: 25mm;
  display: inline-block;
}

@media print {
  .print-controls { display: none; }
  body { margin: 0; }
  .slip {
    border: none;
    margin: 0;
    padding: 5mm 7mm;
    width: 210mm;
    min-height: 148mm;
  }
  @page {
    size: A5 landscape;
    margin: 0;
  }
}
</style>
</head>
<body>

<div class="print-controls">
  <button class="btn-print" onclick="window.print()">🖨️ 印刷する</button>
  <a href="detail.php?id=<?= $id ?>" class="btn-back">← 詳細に戻る</a>
  <span style="font-size:13px;color:#555">印刷ダイアログで用紙サイズ「A5」・向き「横」を選択してください</span>
</div>

<div class="slip">

  <!-- ヘッダー -->
  <div class="slip-header">
    <div class="header-left">
      <div class="garden-code">園コード</div>
      <div class="garden-code-line"></div>
    </div>
    <div class="slip-title">売上受領伝票</div>
    <div class="header-right">
      <div class="header-right-cell">伝票NO</div>
      <div class="header-right-cell">受　領　印</div>
      <div class="header-right-val"></div>
      <div class="header-right-val"></div>
    </div>
  </div>

  <!-- 宛名 -->
  <div class="address-area">
    <div class="address-left">
      <div class="garden-types">
        幼稚園<br>保育園<br>こども園
      </div>
    </div>
    <div style="display:flex;align-items:flex-end;padding-bottom:1mm">
      <span class="garden-name-line"><?= htmlspecialchars($row['garden_name']) ?></span>
      <span class="sama">様</span>
    </div>
  </div>

  <!-- 日付・受発注 -->
  <div class="date-order-area">
    <div class="date-area">
      <span>年</span>
      <span>月</span>
      <span>日</span>
    </div>
    <div class="order-area">
      <div class="order-row">
        <div class="order-label">受注</div>
        <div class="order-val"><?= $order_date_short ?></div>
        <div class="order-staff-label" style="font-size:9px">TEL・FAX<br>Mail・担当</div>
        <div class="order-staff-val"></div>
      </div>
      <div class="order-row">
        <div class="order-label">発注</div>
        <div class="order-val"></div>
        <div class="order-staff-label">担当者</div>
        <div class="order-staff-val"></div>
      </div>
      <div class="order-row">
        <div class="order-label"></div>
        <div class="order-val"></div>
        <div class="order-staff-label">記帳</div>
        <div class="order-staff-val"></div>
      </div>
    </div>
  </div>

  <!-- 商品テーブル -->
  <div class="items-area">
    <!-- 左列（1〜9品目） -->
    <div class="items-col">
      <table class="items-table">
        <tr>
          <th class="item-name">品　　名</th>
          <th class="item-qty">数量</th>
          <th class="item-price">単価</th>
          <th class="item-total">金額</th>
        </tr>
        <?php foreach ($left_items as $item): ?>
        <tr>
          <td class="item-name">
            <?php if (!empty($item['name'])): ?>
              <?= htmlspecialchars($item['name']) ?><br>
              <span style="font-size:9px;color:#333"><?= htmlspecialchars($item['code'] ?? '') ?></span>
            <?php endif; ?>
          </td>
          <td class="item-qty"><?= htmlspecialchars($item['qty'] ?? '') ?></td>
          <td class="item-price"></td>
          <td class="item-total"></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <!-- 右列（10〜18品目） -->
    <div class="items-col">
      <table class="items-table">
        <tr>
          <th class="item-name">品　　名</th>
          <th class="item-qty">数量</th>
          <th class="item-price">単価</th>
          <th class="item-total">金額</th>
        </tr>
        <?php foreach ($right_items as $item): ?>
        <tr>
          <td class="item-name">
            <?php if (!empty($item['name'])): ?>
              <?= htmlspecialchars($item['name']) ?><br>
              <span style="font-size:9px;color:#333"><?= htmlspecialchars($item['code'] ?? '') ?></span>
            <?php endif; ?>
          </td>
          <td class="item-qty"><?= htmlspecialchars($item['qty'] ?? '') ?></td>
          <td class="item-price"></td>
          <td class="item-total"></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- フッター -->
  <div class="slip-footer">
    <div class="footer-note"></div>
    <div class="footer-total">
      税込合計　<span class="total-line"></span>
    </div>
  </div>

</div>

</body>
</html>
