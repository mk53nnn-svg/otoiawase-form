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

// 受注日を令和に変換
function to_reiwa(string $date_str): string {
    $ts   = strtotime($date_str);
    $year = (int)date('Y', $ts);
    $m    = date('n', $ts);
    $d    = date('j', $ts);
    $reiwa = $year - 2018;
    return "令和{$reiwa}年{$m}月{$d}日";
}
$order_date_reiwa = to_reiwa($row['created_at']);

// 商品を左9行・右9行に分割、空行で埋める
$left_items  = array_slice($items, 0, 9);
$right_items = array_slice($items, 9, 9);
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
  font-family: 'MS Mincho', 'Yu Mincho', 'Hiragino Mincho ProN', serif;
  font-size: 11px;
  background: #f2f5fb;
}

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
.hint { font-size: 13px; color: #555; }

/* 伝票本体 */
.slip {
  width: 206mm;
  min-height: 144mm;
  padding: 5mm 6mm;
  margin: 8px auto;
  background: #fff;
  border: 1px solid #aaa;
}

/* === ヘッダー行 === */
.slip-header {
  display: grid;
  grid-template-columns: 55mm 1fr 70mm;
  align-items: start;
  margin-bottom: 2mm;
}
.garden-code-area { font-size: 10px; }
.garden-code-label { margin-bottom: 1mm; }
.garden-code-line {
  border-bottom: 2px solid #000;
  width: 44mm;
  height: 5mm;
  display: block;
}
.slip-title {
  text-align: center;
  font-size: 16px;
  font-weight: 700;
  letter-spacing: .15em;
  padding-top: 1mm;
}
/* 右上：伝票NO・受領印 */
.header-right {
  border: 1.5px solid #000;
  display: grid;
  grid-template-columns: 1fr 1fr;
  font-size: 10px;
}
.hr-label {
  padding: 1mm 2mm;
  border-right: 1.5px solid #000;
  border-bottom: 1.5px solid #000;
  font-weight: 700;
  text-align: center;
  background: #f9f9f9;
}
.hr-label-r {
  padding: 1mm 2mm;
  border-bottom: 1.5px solid #000;
  font-weight: 700;
  text-align: center;
  background: #f9f9f9;
}
.hr-val {
  border-right: 1.5px solid #000;
  height: 14mm;
}
.hr-val-r {
  height: 14mm;
}

/* === 宛名行 === */
.address-row {
  display: grid;
  grid-template-columns: 55mm 1fr 70mm;
  align-items: end;
  margin-bottom: 1mm;
  border-bottom: 1.5px solid #000;
  padding-bottom: 1mm;
}
.garden-name-wrap {
  display: flex;
  align-items: flex-end;
  gap: 1mm;
}
.garden-name-box {
  height: 10mm;
  width: 48mm;
  display: flex;
  align-items: center;
  padding: 0 1mm;
  font-size: 18px;
  font-weight: 700;
  overflow: hidden;
  white-space: nowrap;
}
.sama-l { font-size: 14px; }
.contact-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.5mm;
}
.contact-label { font-size: 9px; color: #333; }
.contact-name-box {
  height: 8mm;
  width: 32mm;
  display: flex;
  align-items: center;
  padding: 0 1mm;
  font-size: 13px;
  font-weight: 700;
}
.sama-r { font-size: 13px; margin-left: 1mm; align-self: flex-end; }

/* === 日付・受発注行 === */
.date-order-row {
  display: grid;
  grid-template-columns: 1fr 80mm;
  border: 1.5px solid #000;
  margin-bottom: 0;
}
.date-area {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 8mm;
  font-size: 13px;
  letter-spacing: .2em;
  border-right: 1.5px solid #000;
  padding: 2mm 6mm;
}
/* 受発注エリア */
.order-area {
  display: grid;
  grid-template-rows: 1fr 1fr;
}
.order-row {
  display: grid;
  grid-template-columns: 12mm 1fr 18mm 22mm;
  border-bottom: 1.5px solid #000;
  min-height: 8mm;
}
.order-row:last-child { border-bottom: none; }
.order-label {
  display: flex;
  align-items: center;
  justify-content: center;
  border-right: 1.5px solid #000;
  font-weight: 700;
  font-size: 11px;
}
.order-val {
  display: flex;
  align-items: center;
  padding: 0 2mm;
  border-right: 1.5px solid #000;
  font-size: 11px;
  font-weight: 700;
}
.order-sub-label {
  display: flex;
  align-items: center;
  justify-content: center;
  border-right: 1.5px solid #000;
  font-size: 10px;
  font-weight: 700;
  text-align: center;
}
.order-sub-val {
  display: flex;
  align-items: center;
  padding: 0 1mm;
}

/* === 商品テーブル === */
.items-area {
  display: grid;
  grid-template-columns: 1fr 1fr;
  border: 1.5px solid #000;
  border-top: 1.5px solid #000;
}
.items-col { border-right: 1.5px solid #000; }
.items-col:last-child { border-right: none; }
.items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 10px;
}
.items-table th {
  border-bottom: 1.5px solid #000;
  border-right: 1px solid #000;
  padding: 1mm;
  text-align: center;
  font-weight: 700;
  letter-spacing: .05em;
}
.items-table th:last-child { border-right: none; }
.items-table td {
  border-bottom: 1px solid #000;
  border-right: 1px solid #000;
  padding: 0.5mm 1mm;
  height: 7.5mm;
  vertical-align: middle;
}
.items-table td:last-child { border-right: none; }
.items-table tr:last-child td { border-bottom: none; }
.col-name  { width: 44%; }
.col-qty   { width: 12%; text-align: center; }
.col-price { width: 22%; }
.col-total { width: 22%; }

/* === フッター === */
.slip-footer {
  border: 1.5px solid #000;
  border-top: none;
  display: grid;
  grid-template-columns: 1fr auto;
}
.footer-note {
  padding: 2mm 3mm;
  font-size: 10px;
  border-right: 1.5px solid #000;
  min-height: 10mm;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
}
.footer-note-label {
  font-size: 9px;
  color: #555;
  margin-bottom: 1mm;
}
.footer-note-text {
  font-size: 10px;
  line-height: 1.8;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.footer-total {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  padding: 3mm 4mm;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: .1em;
  min-height: 10mm;
  white-space: nowrap;
  min-width: 55mm;
}
}

@media print {
  .print-controls { display: none; }
  body { background: #fff; }
  .slip {
    border: none;
    margin: 0;
    padding: 5mm 6mm;
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
  <span class="hint">印刷ダイアログで用紙サイズ「A5」・向き「横」を選択してください</span>
</div>

<div class="slip">

  <!-- ヘッダー行 -->
  <div class="slip-header">
    <div class="garden-code-area">
      <div class="garden-code-label">園コード</div>
      <span class="garden-code-line"></span>
    </div>
    <div class="slip-title">売上受領伝票</div>
    <div class="header-right">
      <div class="hr-label">伝票NO</div>
      <div class="hr-label-r">受　領　印</div>
      <div class="hr-val"></div>
      <div class="hr-val-r"></div>
    </div>
  </div>

  <!-- 宛名行 -->
  <div class="address-row">
    <div class="garden-name-wrap">
      <div class="garden-name-box"><?= htmlspecialchars($row['garden_name']) ?></div>
      <span class="sama-l">様</span>
    </div>
    <div></div>
    <div style="display:flex;align-items:flex-end;gap:1mm">
      <div class="contact-wrap">
        <span class="contact-label">ご担当者名</span>
        <div class="contact-name-box"><?= htmlspecialchars($row['contact_name']) ?></div>
      </div>
      <span class="sama-r">様</span>
    </div>
  </div>

  <!-- 日付・受発注行 -->
  <div class="date-order-row">
    <div class="date-area">
      <span>年</span>
      <span>月</span>
      <span>日</span>
    </div>
    <div class="order-area">
      <div class="order-row">
        <div class="order-label">受注</div>
        <div class="order-val"><?= $order_date_reiwa ?></div>
        <div class="order-sub-label">担当者</div>
        <div class="order-sub-val"></div>
      </div>
      <div class="order-row">
        <div class="order-label">発注</div>
        <div class="order-val"></div>
        <div class="order-sub-label">記帳</div>
        <div class="order-sub-val"></div>
      </div>
    </div>
  </div>

  <!-- 商品テーブル -->
  <div class="items-area">
    <div class="items-col">
      <table class="items-table">
        <tr>
          <th class="col-name">品　　名</th>
          <th class="col-qty">数量</th>
          <th class="col-price">単価</th>
          <th class="col-total">金額</th>
        </tr>
        <?php foreach ($left_items as $item): ?>
        <tr>
          <td class="col-name">
            <?php if (!empty($item['name'])): ?>
              <?= htmlspecialchars($item['name']) ?>
              <?php if (!empty($item['code'])): ?>
                <br><span style="font-size:9px"><?= htmlspecialchars($item['code']) ?></span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td class="col-qty"><?= htmlspecialchars($item['qty'] ?? '') ?></td>
          <td class="col-price"></td>
          <td class="col-total"></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <div class="items-col">
      <table class="items-table">
        <tr>
          <th class="col-name">品　　名</th>
          <th class="col-qty">数量</th>
          <th class="col-price">単価</th>
          <th class="col-total">金額</th>
        </tr>
        <?php foreach ($right_items as $item): ?>
        <tr>
          <td class="col-name">
            <?php if (!empty($item['name'])): ?>
              <?= htmlspecialchars($item['name']) ?>
              <?php if (!empty($item['code'])): ?>
                <br><span style="font-size:9px"><?= htmlspecialchars($item['code']) ?></span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td class="col-qty"><?= htmlspecialchars($item['qty'] ?? '') ?></td>
          <td class="col-price"></td>
          <td class="col-total"></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- フッター -->
  <div class="slip-footer">
    <div class="footer-note">
      <?php if (!empty($row['note'])): ?>
        <span class="footer-note-text"><?= htmlspecialchars(mb_strimwidth($row['note'], 0, 60, '…')) ?></span>
      <?php endif; ?>
    </div>
    <div class="footer-total">税込合計</div>
  </div>

</div>
</body>
</html>
