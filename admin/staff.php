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

$msg = '';
$msg_type = 'success';

// 追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_name'])) {
    $name = trim($_POST['add_name']);
    if ($name !== '') {
        $stmt = $pdo->prepare('INSERT INTO staffs (name) VALUES (:name)');
        $stmt->execute([':name' => $name]);
        $msg = '「' . htmlspecialchars($name) . '」を追加しました';
    }
}

// 削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = (int)$_POST['delete_id'];
    $pdo->prepare('DELETE FROM staffs WHERE id = :id')->execute([':id' => $del_id]);
    $msg = '担当者を削除しました';
}

$staffs = $pdo->query('SELECT * FROM staffs ORDER BY id ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>担当者管理</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Noto Sans JP',-apple-system,sans-serif}
body{background:#f2f5fb;color:#1a1a2e;min-height:100vh}
header{background:#0d47a1;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between}
header h1{font-size:17px;font-weight:700}
.header-links{display:flex;gap:12px;align-items:center}
.back{color:rgba(255,255,255,.85);font-size:13px;text-decoration:none}
.back:hover{text-decoration:underline}
.logout{color:rgba(255,255,255,.8);font-size:13px;text-decoration:none;border:1px solid rgba(255,255,255,.4);padding:6px 12px;border-radius:6px}
.wrap{max-width:600px;margin:auto;padding:24px 16px}
.card{background:#fff;border-radius:10px;padding:24px 28px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:18px}
.card-title{font-size:16px;font-weight:700;color:#0d47a1;padding-bottom:12px;border-bottom:2px solid #e8eef8;margin-bottom:18px}
.add-form{display:flex;gap:10px;margin-bottom:4px}
.add-form input{flex:1;padding:11px 13px;border:1.5px solid #d0d7e5;border-radius:8px;font-size:14px;font-family:inherit}
.add-form input:focus{outline:none;border-color:#1976d2;box-shadow:0 0 0 3px rgba(25,118,210,.12)}
.btn{background:#0d47a1;color:#fff;border:none;padding:11px 20px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer}
.btn:hover{background:#0a3580}
.btn-del{background:none;border:1.5px solid #e0e0e0;color:#c62828;padding:6px 14px;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer}
.btn-del:hover{background:#ffebee;border-color:#c62828}
.staff-list{list-style:none}
.staff-item{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #eef0f5;font-size:15px}
.staff-item:last-child{border-bottom:none}
.msg{padding:10px 16px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:16px;background:#e8f5e9;color:#2e7d32}
.empty{color:#888;font-size:14px;padding:16px 0}

</style>
</head>
<body>

<header>
  <h1>担当者管理</h1>
  <div class="header-links">
    <a href="dashboard.php" class="back">← ホームに戻る</a>
    <a href="logout.php" class="logout">ログアウト</a>
  </div>
</header>

<div class="wrap">

  <?php if ($msg): ?>
    <div class="msg">✓ <?= $msg ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-title">担当者を追加</div>
    <form method="post" class="add-form">
      <input type="text" name="add_name" placeholder="担当者名を入力" required>
      <button type="submit" class="btn">追加</button>
    </form>
  </div>

  <div class="card">
    <div class="card-title">担当者一覧</div>
    <?php if (empty($staffs)): ?>
      <p class="empty">担当者が登録されていません</p>
    <?php else: ?>
    <ul class="staff-list">
      <?php foreach ($staffs as $staff): ?>
      <li class="staff-item">
        <span><?= htmlspecialchars($staff['name']) ?></span>
        <form method="post" onsubmit="return confirm('「<?= htmlspecialchars($staff['name']) ?>」を削除しますか？')">
          <input type="hidden" name="delete_id" value="<?= $staff['id'] ?>">
          <button type="submit" class="btn-del">削除</button>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
