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

// 担当者リスト取得
$staffs = $pdo->query('SELECT * FROM staffs ORDER BY id ASC')->fetchAll();

// 保存処理
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = $_POST['status']     ?? '';
    $admin_memo = $_POST['admin_memo'] ?? '';
    $staff_id   = (int)($_POST['staff_id'] ?? 0);
    $allowed    = ['未対応','確認中','対応中','完了'];
    if (in_array($new_status, $allowed, true)) {
        $stmt = $pdo->prepare('UPDATE inquiries SET status=:status, admin_memo=:memo, staff_id=:staff_id WHERE id=:id');
        $stmt->execute([
            ':status'   => $new_status,
            ':memo'     => $admin_memo,
            ':staff_id' => $staff_id ?: null,
            ':id'       => $id,
        ]);
        $saved = true;
    }
}

// データ取得
$stmt = $pdo->prepare('SELECT * FROM inquiries WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) { header('Location: dashboard.php'); exit; }

$items = [];
if ($row['items']) $items = json_decode($row['items'], true) ?? [];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>詳細 - <?= htmlspecialchars($row['inquiry_no']) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Noto Sans JP',-apple-system,sans-serif}
body{background:#f2f5fb;color:#1a1a2e;min-height:100vh}
header{background:#0d47a1;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between}
header h1{font-size:17px;font-weight:700}
.header-links{display:flex;gap:12px;align-items:center}
.back{color:rgba(255,255,255,.85);font-size:13px;text-decoration:none}
.back:hover{text-decoration:underline}
.logout{color:rgba(255,255,255,.8);font-size:13px;text-decoration:none;border:1px solid rgba(255,255,255,.4);padding:6px 12px;border-radius:6px}
.wrap{max-width:860px;margin:auto;padding:24px 16px}
.card{background:#fff;border-radius:10px;padding:24px 28px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:18px}
.card-title{font-size:16px;font-weight:700;color:#0d47a1;padding-bottom:12px;border-bottom:2px solid #e8eef8;margin-bottom:18px}
.info-grid{display:grid;grid-template-columns:140px 1fr;gap:10px 16px;font-size:14px}
.info-label{color:#555;font-weight:700}
.info-value{color:#1a1a2e}
.badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;display:inline-block}
.new{background:#e3f2fd;color:#1565c0}
.wait{background:#f3e5f5;color:#7b1fa2}
.progress{background:#fff8e1;color:#ef6c00}
.done{background:#e8f5e9;color:#2e7d32}
label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;margin-top:16px}
select,textarea{width:100%;padding:11px 13px;border:1.5px solid #d0d7e5;border-radius:8px;font-size:14px;font-family:inherit}
select:focus,textarea:focus{outline:none;border-color:#1976d2;box-shadow:0 0 0 3px rgba(25,118,210,.12)}
textarea{min-height:120px;resize:vertical}
.btn{background:#0d47a1;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;margin-top:16px}
.btn:hover{background:#0a3580}
.saved{background:#e8f5e9;color:#2e7d32;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:14px}
.items-table{width:100%;border-collapse:collapse;margin-top:8px}
.items-table th{background:#e8eef8;color:#0d47a1;font-size:13px;padding:8px 12px;text-align:left}
.items-table td{padding:8px 12px;border-bottom:1px solid #eef0f5;font-size:14px}
@media(max-width:600px){.info-grid{grid-template-columns:1fr}.card{padding:18px 16px}}
</style>
</head>
<body>

<header>
  <h1>問い合わせ詳細</h1>
  <div class="header-links">
    <a href="dashboard.php" class="back">← 一覧に戻る</a>
    <a href="logout.php" class="logout">ログアウト</a>
  </div>
</header>

<div class="wrap">

  <?php if ($saved): ?>
    <div class="saved">✓ 保存しました</div>
  <?php endif; ?>

  <div class="card">
    <div class="card-title">基本情報</div>
    <div class="info-grid">
      <span class="info-label">問い合わせ番号</span>
      <span class="info-value"><?= htmlspecialchars($row['inquiry_no']) ?></span>
      <span class="info-label">受付日時</span>
      <span class="info-value"><?= date('Y年m月d日 H:i', strtotime($row['created_at'])) ?></span>
      <span class="info-label">種別</span>
      <span class="info-value"><?= htmlspecialchars($row['type']) ?></span>
      <span class="info-label">ステータス</span>
      <span class="info-value">
        <?php
        $map = ['未対応'=>'new','確認中'=>'wait','対応中'=>'progress','完了'=>'done'];
        $cls = $map[$row['status']] ?? 'new';
        echo '<span class="badge '.$cls.'">'.htmlspecialchars($row['status']).'</span>';
        ?>
      </span>
    </div>
  </div>

  <div class="card">
    <div class="card-title">取引先情報</div>
    <div class="info-grid">
      <span class="info-label">園名</span>
      <span class="info-value"><?= htmlspecialchars($row['garden_name']) ?></span>
      <span class="info-label">担当者</span>
      <span class="info-value"><?= htmlspecialchars($row['contact_name']) ?></span>
      <span class="info-label">電話番号</span>
      <span class="info-value"><?= htmlspecialchars($row['phone']) ?></span>
      <span class="info-label">メール</span>
      <span class="info-value"><?= htmlspecialchars($row['email']) ?></span>
    </div>
  </div>

  <div class="card">
    <div class="card-title">問い合わせ内容</div>
    <?php if (!empty($items)): ?>
      <table class="items-table">
        <tr><th>商品名</th><th>商品コード</th><th>数量</th></tr>
        <?php foreach ($items as $item): ?>
        <tr>
          <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
          <td><?= htmlspecialchars($item['code'] ?? '') ?></td>
          <td><?= htmlspecialchars($item['qty']  ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
    <?php if ($row['repair_symptom']): ?>
      <div class="info-grid" style="margin-top:12px">
        <span class="info-label">症状・状態</span>
        <span class="info-value"><?= nl2br(htmlspecialchars($row['repair_symptom'])) ?></span>
      </div>
    <?php endif; ?>
    <?php if ($row['repair_image']): ?>
      <div class="info-grid" style="margin-top:12px">
        <span class="info-label">添付写真</span>
        <span class="info-value">
          <img src="../uploads/<?= htmlspecialchars($row['repair_image']) ?>"
               style="max-width:300px;border-radius:8px;border:1px solid #ddd">
        </span>
      </div>
    <?php endif; ?>
    <?php if ($row['note']): ?>
      <div class="info-grid" style="margin-top:12px">
        <span class="info-label">備考</span>
        <span class="info-value"><?= nl2br(htmlspecialchars($row['note'])) ?></span>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-title">対応管理</div>
    <form method="post">
      <label>ステータス変更</label>
      <select name="status">
        <?php foreach (['未対応','確認中','対応中','完了'] as $s): ?>
          <option value="<?= $s ?>" <?= $row['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>

      <label>社内担当者</label>
      <select name="staff_id">
        <option value="">未割当</option>
        <?php foreach ($staffs as $staff): ?>
          <option value="<?= $staff['id'] ?>" <?= $row['staff_id'] == $staff['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($staff['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>社内メモ</label>
      <textarea name="admin_memo" placeholder="対応内容・連絡事項などを記入"><?= htmlspecialchars($row['admin_memo'] ?? '') ?></textarea>

      <button type="submit" class="btn">保存する</button>
    </form>
  </div>

</div>
</body>
</html>
