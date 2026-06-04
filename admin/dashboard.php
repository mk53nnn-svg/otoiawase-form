<?php
session_start();
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// DB接続
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('DB接続エラー：' . $e->getMessage());
}

// 検索・絞り込み
$status = $_GET['status'] ?? '';
$type   = $_GET['type']   ?? '';
$q      = $_GET['q']      ?? '';

$where  = [];
$params = [];

if ($status) {
    $where[]          = 'status = :status';
    $params[':status'] = $status;
}
if ($type) {
    $where[]        = 'type = :type';
    $params[':type'] = $type;
}
if ($q) {
    $where[]    = '(garden_name LIKE :q OR contact_name LIKE :q OR inquiry_no LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

$sql = 'SELECT * FROM inquiries';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ステータスバッジ
function badge(string $s): string {
    $map = [
        '未対応' => 'new',
        '確認中' => 'wait',
        '対応中' => 'progress',
        '完了'   => 'done',
    ];
    $cls = $map[$s] ?? 'new';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($s) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>管理画面 - 問い合わせ一覧</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Noto Sans JP',-apple-system,sans-serif}
body{background:#f2f5fb;color:#1a1a2e;min-height:100vh}
header{background:#0d47a1;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between}
header h1{font-size:17px;font-weight:700}
.logout{color:rgba(255,255,255,.8);font-size:13px;text-decoration:none;border:1px solid rgba(255,255,255,.4);padding:6px 12px;border-radius:6px}
.logout:hover{background:rgba(255,255,255,.1)}
.wrap{max-width:1200px;margin:auto;padding:24px 16px}
.search-bar{background:#fff;border-radius:10px;padding:16px 20px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.search-bar input,.search-bar select{padding:9px 12px;border:1.5px solid #d0d7e5;border-radius:8px;font-size:14px;font-family:inherit}
.search-bar input{flex:1;min-width:180px}
.btn{background:#0d47a1;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer}
.btn:hover{background:#0a3580}
.btn-reset{background:#607d8b}
.btn-reset:hover{background:#455a64}
.card{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden}
.table{width:100%;border-collapse:collapse}
.table th{background:#e8eef8;color:#0d47a1;font-size:13px;font-weight:700;padding:12px 14px;text-align:left}
.table td{padding:12px 14px;border-bottom:1px solid #eef0f5;font-size:14px;vertical-align:middle}
.table tr:last-child td{border-bottom:none}
.table tr:hover td{background:#f8fafc}
.badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;display:inline-block}
.new{background:#e3f2fd;color:#1565c0}
.wait{background:#f3e5f5;color:#7b1fa2}
.progress{background:#fff8e1;color:#ef6c00}
.done{background:#e8f5e9;color:#2e7d32}
.detail-link{color:#0d47a1;text-decoration:none;font-weight:700}
.detail-link:hover{text-decoration:underline}
.count{font-size:13px;color:#555;margin-bottom:10px}
.empty{text-align:center;padding:40px;color:#888}
@media(max-width:700px){
  .table th:nth-child(3),.table td:nth-child(3),
  .table th:nth-child(5),.table td:nth-child(5){display:none}
}
</style>
</head>
<body>

<header>
  <h1>管理画面 - 問い合わせ一覧</h1>
  <a href="logout.php" class="logout">ログアウト</a>
</header>

<div class="wrap">

  <!-- 検索バー -->
  <form class="search-bar" method="get">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="園名・担当者・問い合わせ番号で検索">
    <select name="status">
      <option value="">すべてのステータス</option>
      <?php foreach (['未対応','確認中','対応中','完了'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <select name="type">
      <option value="">すべての種別</option>
      <?php foreach (['通常発注','納期確認','修理依頼','その他'] as $t): ?>
        <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn">検索</button>
    <a href="dashboard.php" class="btn btn-reset" style="text-decoration:none">リセット</a>
  </form>

  <p class="count">全 <?= count($rows) ?> 件</p>

  <div class="card">
    <?php if (empty($rows)): ?>
      <p class="empty">該当する問い合わせがありません</p>
    <?php else: ?>
    <table class="table">
      <tr>
        <th>受付日時</th>
        <th>問い合わせ番号</th>
        <th>園名</th>
        <th>種別</th>
        <th>担当者</th>
        <th>ステータス</th>
        <th>詳細</th>
      </tr>
      <?php foreach ($rows as $row): ?>
      <tr>
        <td><?= htmlspecialchars(date('m/d H:i', strtotime($row['created_at']))) ?></td>
        <td><?= htmlspecialchars($row['inquiry_no']) ?></td>
        <td><?= htmlspecialchars($row['garden_name']) ?></td>
        <td><?= htmlspecialchars($row['type']) ?></td>
        <td><?= htmlspecialchars($row['contact_name']) ?></td>
        <td><?= badge($row['status']) ?></td>
        <td><a href="detail.php?id=<?= $row['id'] ?>" class="detail-link">詳細 →</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
