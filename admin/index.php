<?php
session_start();
require_once __DIR__ . '/../config.php';

// すでにログイン済みならダッシュボードへ
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = $_POST['admin_id']   ?? '';
    $pass = $_POST['admin_pass'] ?? '';

    if ($id === ADMIN_ID && $pass === ADMIN_PASS) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'IDまたはパスワードが正しくありません';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>管理画面ログイン</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Noto Sans JP',-apple-system,sans-serif}
body{background:#f2f5fb;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border-radius:12px;padding:40px 36px;box-shadow:0 4px 24px rgba(13,71,161,.10);width:100%;max-width:380px}
h1{font-size:20px;color:#0d47a1;text-align:center;margin-bottom:28px;font-weight:700}
label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;margin-top:16px}
input{width:100%;padding:11px 13px;border:1.5px solid #d0d7e5;border-radius:8px;font-size:15px}
input:focus{outline:none;border-color:#1976d2;box-shadow:0 0 0 3px rgba(25,118,210,.12)}
.btn{width:100%;background:#0d47a1;color:#fff;border:none;padding:13px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;margin-top:24px}
.btn:hover{background:#0a3580}
.error{background:#ffebee;color:#c62828;padding:10px 14px;border-radius:8px;font-size:13px;margin-top:14px;text-align:center}
</style>
</head>
<body>
<div class="card">
  <h1>管理画面ログイン</h1>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <label>管理者ID</label>
    <input type="text" name="admin_id" autocomplete="username" required>
    <label>パスワード</label>
    <input type="password" name="admin_pass" autocomplete="current-password" required>
    <button type="submit" class="btn">ログイン</button>
  </form>
</div>
</body>
</html>
