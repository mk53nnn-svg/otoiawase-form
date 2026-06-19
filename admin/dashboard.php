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

// 担当者リスト
$staffs = $pdo->query('SELECT * FROM staffs ORDER BY id ASC')->fetchAll();
$staff_map = [];
foreach ($staffs as $s) $staff_map[$s['id']] = $s['name'];

// 検索・絞り込み
$status   = $_GET['status']   ?? '';
$type     = $_GET['type']     ?? '';
$q        = $_GET['q']        ?? '';
$date = $_GET['date'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per      = 10;

$where  = [];
$params = [];

if ($status)    { $where[] = 'status = :status'; $params[':status'] = $status; }
if ($type)      { $where[] = 'type = :type';     $params[':type']   = $type; }
if ($q)         { $where[] = '(garden_name LIKE :q OR contact_name LIKE :q OR inquiry_no LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
if ($date) { $where[] = 'DATE(created_at) = :date'; $params[':date'] = $date; }

$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// ステータス別件数
$status_counts = [];
foreach (['未対応','対応中'] as $s) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM inquiries WHERE status = ?');
    $st->execute([$s]);
    $status_counts[$s] = (int)$st->fetchColumn();
}

// 総件数
$count_stmt = $pdo->prepare('SELECT COUNT(*) FROM inquiries' . $where_sql);
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total / $per);

// データ取得
$offset = ($page - 1) * $per;
$sql = 'SELECT * FROM inquiries' . $where_sql . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $per,    PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

function badge(string $s): string {
    $map = ['未対応'=>'new','対応中'=>'progress','完了'=>'done','キャンセル'=>'cancelled'];
    $cls = $map[$s] ?? 'new';
    return '<span class="badge '.$cls.'">'.htmlspecialchars($s).'</span>';
}

function pager_url(array $get, int $page): string {
    $get['page'] = $page;
    return '?' . http_build_query($get);
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
.header-title{font-size:17px;font-weight:700;color:#fff;text-decoration:none}
.header-title:hover{opacity:.85}
.header-links{display:flex;gap:12px;align-items:center}
.nav-link{color:rgba(255,255,255,.85);font-size:13px;text-decoration:none;padding:6px 12px;border-radius:6px}
.nav-link:hover{background:rgba(255,255,255,.15)}
.logout{border:1px solid rgba(255,255,255,.4)}
.wrap{max-width:1200px;margin:auto;padding:24px 16px}
.search-bar{background:#fff;border-radius:10px;padding:16px 20px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.search-bar input,.search-bar select{padding:9px 12px;border:1.5px solid #d0d7e5;border-radius:8px;font-size:14px;font-family:inherit}
.search-bar input[type="text"]{flex:1;min-width:160px}
.search-bar input[type="date"]{min-width:140px}
.date-sep{font-size:13px;color:#555}
.btn{background:#0d47a1;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}
.btn:hover{background:#0a3580}
.btn-reset{background:#607d8b}
.btn-reset:hover{background:#455a64}
.summary{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;align-items:center}
.summary-badge{padding:5px 12px;border-radius:20px;font-size:13px;font-weight:700}
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
.cancelled{background:#f5f5f5;color:#757575}
.detail-link{color:#0d47a1;text-decoration:none;font-weight:700}
.detail-link:hover{text-decoration:underline}
.empty{text-align:center;padding:40px;color:#888}
.pager{display:flex;gap:6px;justify-content:center;padding:18px}
.pager a,.pager span{padding:7px 13px;border-radius:7px;font-size:14px;font-weight:700;text-decoration:none;border:1.5px solid #d0d7e5;color:#0d47a1;background:#fff}
.pager a:hover{background:#e8eef8}
.pager .current{background:#0d47a1;color:#fff;border-color:#0d47a1}
@media(max-width:700px){
  .table th:nth-child(2),.table td:nth-child(2),
  .table th:nth-child(5),.table td:nth-child(5){display:none}
}
</style>
</head>
<body>

<header>
  <a href="dashboard.php" class="header-title">管理画面 - 問い合わせ一覧</a>
  <div class="header-links">
    <a href="dashboard.php" class="nav-link">ホーム</a>
    <a href="staff.php" class="nav-link">担当者管理</a>
    <a href="logout.php" class="nav-link logout">ログアウト</a>
  </div>
</header>

<div class="wrap">

  <form class="search-bar" method="get">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="園名・担当者・問い合わせ番号で検索">
    <select name="status">
      <option value="">すべてのステータス</option>
      <?php foreach (['未対応','対応中','完了','キャンセル'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
      <option value="">すべての種別</option>
      <?php foreach (['通常発注','納期確認','修理依頼','その他'] as $t): ?>
        <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" title="日付で絞り込み">
    <button type="submit" class="btn">検索</button>
    <a href="dashboard.php" class="btn btn-reset">リセット</a>
  </form>

  <div class="summary">
    <span class="summary-badge new">未対応 <?= $status_counts['未対応'] ?> 件</span>
    <span class="summary-badge progress">対応中 <?= $status_counts['対応中'] ?> 件</span>
  </div>

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
        <th>取引先担当者</th>
        <th>ステータス</th>
        <th>社内担当者</th>
        <th>詳細</th>
      </tr>
      <?php foreach ($rows as $row): ?>
      <tr>
        <td><?= htmlspecialchars(date('m/d H:i', strtotime($row['created_at']))) ?></td>
        <td><?= htmlspecialchars($row['inquiry_no']) ?></td>
        <td><?= htmlspecialchars($row['garden_name']) ?></td>
        <td><?= htmlspecialchars($row['type']) ?><?= !empty($row['note']) ? ' 📝' : '' ?><?= ($row['type'] === '修理依頼' && !empty($row['repair_image'])) ? ' 📷' : '' ?><?= ($row['type'] === '通常発注' && !empty($row['printed_at'])) ? ' 🖨️' : '' ?></td>
        <td><?= htmlspecialchars($row['contact_name']) ?></td>
        <td><?= badge($row['status']) ?></td>
        <td><?= htmlspecialchars($staff_map[$row['staff_id']] ?? '') ?><?= !empty($row['admin_memo']) ? ' 📝' : '' ?></td>
        <td><a href="detail.php?id=<?= $row['id'] ?>" class="detail-link">詳細 →</a></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="pager">
      <?php if ($page > 1): ?>
        <a href="<?= pager_url($_GET, $page - 1) ?>">← 前へ</a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="current"><?= $i ?></span>
        <?php else: ?>
          <a href="<?= pager_url($_GET, $i) ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $total_pages): ?>
        <a href="<?= pager_url($_GET, $page + 1) ?>">次へ →</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>

</div>
</body>
</html>
