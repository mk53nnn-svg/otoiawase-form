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
 
$staffs = $pdo->query('SELECT * FROM staffs ORDER BY id ASC')->fetchAll();
 
$saved      = isset($_GET['saved']);
$mail_sent  = isset($_GET['mail_sent']);
$mail_error = isset($_GET['mail_error']);
 
// ===== 対応管理 保存 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $new_status = $_POST['status']     ?? '';
    $admin_memo = $_POST['admin_memo'] ?? '';
    $staff_id   = (int)($_POST['staff_id'] ?? 0);
    $allowed    = ['未対応','対応中','完了','キャンセル'];
    if (in_array($new_status, $allowed, true)) {
        $stmt = $pdo->prepare('UPDATE inquiries SET status=:status, admin_memo=:memo, staff_id=:staff_id WHERE id=:id');
        $stmt->execute([
            ':status'   => $new_status,
            ':memo'     => $admin_memo,
            ':staff_id' => $staff_id ?: null,
            ':id'       => $id,
        ]);
        header('Location: detail.php?id=' . $id . '&saved=1');
        exit;
    }
}
 
// ===== 返信メール送信 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    $subject = trim($_POST['reply_subject'] ?? '');
    $body    = trim($_POST['reply_body']    ?? '');
 
    $stmt = $pdo->prepare('SELECT * FROM inquiries WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $inquiry = $stmt->fetch();
 
    if ($subject && $body && $inquiry) {
        $to        = $inquiry['email'];
        $from      = defined('MAIL_REPLY') ? MAIL_REPLY : MAIL_FROM;
        $from_name = mb_encode_mimeheader(MAIL_FROM_NAME, 'UTF-8', 'B');
        $headers   = implode("\r\n", [
            "From: {$from_name} <{$from}>",
            "Reply-To: {$from}",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "X-Mailer: PHP/" . PHP_VERSION,
        ]);
        $subject_enc = mb_encode_mimeheader($subject, 'UTF-8', 'B');
        $body_enc    = chunk_split(base64_encode($body));
        $result = mail($to, $subject_enc, $body_enc, $headers);
 
        if ($result) {
            // 送信履歴を保存
            $log = $pdo->prepare('INSERT INTO reply_logs (inquiry_id, subject, body) VALUES (:iid, :subject, :body)');
            $log->execute([':iid' => $id, ':subject' => $subject, ':body' => $body]);
            header('Location: detail.php?id=' . $id . '&mail_sent=1');
        } else {
            header('Location: detail.php?id=' . $id . '&mail_error=1');
        }
        exit;
    }
}
 
// ===== 発注完了メール送信 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete') {
    // データ取得
    $stmt2 = $pdo->prepare('SELECT * FROM inquiries WHERE id = :id');
    $stmt2->execute([':id' => $id]);
    $row2 = $stmt2->fetch();
 
    $stmt = $pdo->prepare('UPDATE inquiries SET status=:status WHERE id=:id');
    $stmt->execute([':status' => '完了', ':id' => $id]);
 
    $complete_subject = '【ご注文確認】' . $row2['inquiry_no'];
    $complete_body = $row2['contact_name'] . ' 様
 
この度はご注文いただきありがとうございます。
ご注文を承りました。通常7日～10日程度でお届けいたします。
 
ご不明点がございましたらお気軽にお問い合わせください。
 
━━━━━━━━━━━━━━━━━━
〒336-0932
埼玉県さいたま市緑区中尾1507-1
埼玉保育教販株式会社
TEL：048-873-3333
FAX：048-873-3335
━━━━━━━━━━━━━━━━━━';
 
    $from      = defined('MAIL_REPLY') ? MAIL_REPLY : MAIL_FROM;
    $from_name = mb_encode_mimeheader(MAIL_FROM_NAME, 'UTF-8', 'B');
    $headers   = implode("\r\n", [
        "From: {$from_name} <{$from}>",
        "Reply-To: {$from}",
        "Content-Type: text/plain; charset=UTF-8",
        "Content-Transfer-Encoding: base64",
        "X-Mailer: PHP/" . PHP_VERSION,
    ]);
    $subject_enc = mb_encode_mimeheader($complete_subject, 'UTF-8', 'B');
    $body_enc    = chunk_split(base64_encode($complete_body));
    $result = mail($row2['email'], $subject_enc, $body_enc, $headers);
 
    if ($result) {
        $log = $pdo->prepare('INSERT INTO reply_logs (inquiry_id, subject, body) VALUES (:iid, :subject, :body)');
        $log->execute([':iid' => $id, ':subject' => $complete_subject, ':body' => $complete_body]);
        header('Location: detail.php?id=' . $id . '&mail_sent=1');
    } else {
        header('Location: detail.php?id=' . $id . '&mail_error=1');
    }
    exit;
}
$stmt = $pdo->prepare('SELECT * FROM inquiries WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) { header('Location: dashboard.php'); exit; }
 
$items = [];
if ($row['items']) $items = json_decode($row['items'], true) ?? [];
 
// 返信履歴取得
$logs = $pdo->prepare('SELECT * FROM reply_logs WHERE inquiry_id = :id ORDER BY sent_at DESC');
$logs->execute([':id' => $id]);
$reply_logs = $logs->fetchAll();
 
// デフォルト件名
$default_subject = '【' . $row['inquiry_no'] . '】お問い合わせへのご回答';
 
// 備考引用テキスト
$note_quote = '';
if (!empty($row['note'])) {
    $note_quote = "\n\n--- お客様からのメッセージ ---\n" . $row['note'];
}
 
// 発注完了メール本文
$order_complete_subject = '【ご注文確認】' . $row['inquiry_no'];
$order_complete_body = $row['contact_name'] . ' 様
 
この度はご注文いただきありがとうございます。
ご注文を承りました。通常7日～10日程度でお届けいたします。
 
ご不明点がございましたらお気軽にお問い合わせください。
 
━━━━━━━━━━━━━━━━━━
〒336-0932
埼玉県さいたま市緑区中尾1507-1
埼玉保育教販株式会社
TEL：048-873-3333
FAX：048-873-3335
━━━━━━━━━━━━━━━━━━';
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
.wrap{max-width:980px;margin:auto;padding:24px 16px}
.card{background:#fff;border-radius:10px;padding:24px 28px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:18px}
.card-title{font-size:16px;font-weight:700;color:#0d47a1;padding-bottom:12px;border-bottom:2px solid #e8eef8;margin-bottom:18px}
.top-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}
@media(max-width:700px){.top-grid{grid-template-columns:1fr}}
.top-grid .card{margin-bottom:0}
.info-grid{display:grid;grid-template-columns:120px 1fr;gap:8px 12px;font-size:14px}
.info-label{color:#555;font-weight:700}
.info-value{color:#1a1a2e}
.badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;display:inline-block}
.new{background:#e3f2fd;color:#1565c0}
.progress{background:#fff8e1;color:#ef6c00}
.done{background:#e8f5e9;color:#2e7d32}
.cancelled{background:#f5f5f5;color:#757575}
label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;margin-top:16px}
input[type="text"],select,textarea{width:100%;padding:11px 13px;border:1.5px solid #d0d7e5;border-radius:8px;font-size:14px;font-family:inherit}
input[type="text"]:focus,select:focus,textarea:focus{outline:none;border-color:#1976d2;box-shadow:0 0 0 3px rgba(25,118,210,.12)}
textarea{min-height:120px;resize:vertical}
.btn{background:#0d47a1;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;margin-top:16px}
.btn:hover{background:#0a3580}
.btn-green{background:#2e7d32}
.btn-green:hover{background:#1b5e20}
.saved{background:#e8f5e9;color:#2e7d32;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:14px}
.mail-sent{background:#e3f2fd;color:#1565c0;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:14px}
.mail-error{background:#ffebee;color:#c62828;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:14px}
.items-table{width:100%;border-collapse:collapse;margin-top:8px}
.items-table th{background:#e8eef8;color:#0d47a1;font-size:13px;padding:8px 12px;text-align:left}
.items-table td{padding:8px 12px;border-bottom:1px solid #eef0f5;font-size:14px;vertical-align:middle}
.copy-btn{background:none;border:1.5px solid #b0bec5;color:#555;padding:3px 10px;border-radius:5px;font-size:12px;cursor:pointer;margin-left:6px;white-space:nowrap}
.copy-btn:hover{background:#e8eef8;border-color:#0d47a1;color:#0d47a1}
.copy-btn.copied{border-color:#2e7d32;color:#2e7d32}
.log-item{padding:12px 0;border-bottom:1px solid #eef0f5;font-size:14px}
.log-item:last-child{border-bottom:none}
.log-date{font-size:12px;color:#888;margin-bottom:4px}
.log-subject{font-weight:700;margin-bottom:4px}
.log-body{color:#555;white-space:pre-wrap;font-size:13px;background:#f8fafc;padding:10px;border-radius:6px;margin-top:6px}
.to-address{font-size:13px;color:#555;margin-bottom:4px}
@media(max-width:600px){.card{padding:18px 16px}.info-grid{grid-template-columns:1fr}}
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
  <?php if ($mail_sent): ?>
    <div class="mail-sent">✉ 返信メールを送信しました</div>
  <?php endif; ?>
  <?php if ($mail_error): ?>
    <div class="mail-error">⚠ メール送信に失敗しました。設定を確認してください。</div>
  <?php endif; ?>
 
  <!-- 基本情報と取引先情報を横並び -->
  <div class="top-grid">
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
          $map = ['未対応'=>'new','対応中'=>'progress','完了'=>'done','キャンセル'=>'cancelled'];
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
  </div>
 
  <!-- 問い合わせ内容 -->
  <div class="card">
    <div class="card-title">問い合わせ内容</div>
    <?php if (!empty($items)): ?>
      <table class="items-table">
        <tr><th>商品名</th><th>商品コード</th><th>数量</th></tr>
        <?php foreach ($items as $item): ?>
        <tr>
          <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
          <td>
            <?= htmlspecialchars($item['code'] ?? '') ?>
            <?php if (!empty($item['code'])): ?>
              <button type="button" class="copy-btn" onclick="copyCode(this, '<?= htmlspecialchars($item['code'], ENT_QUOTES) ?>')">コピー</button>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($item['qty'] ?? '') ?></td>
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
 
  <!-- 返信メール送信 -->
  <div class="card">
    <div class="card-title">返信メールを送る</div>
    <p class="to-address">送信先：<?= htmlspecialchars($row['contact_name']) ?> 様　&lt;<?= htmlspecialchars($row['email']) ?>&gt;</p>
    <form method="post">
      <input type="hidden" name="action" value="reply">
      <label>件名</label>
      <input type="text" name="reply_subject" value="<?= htmlspecialchars($default_subject) ?>" required>
      <label>本文</label>
      <textarea name="reply_body" style="min-height:180px" required>この度はお問い合わせいただきありがとうございます。
埼玉保育教販株式会社 でございます。
 
 
 
ご不明点やご質問がございましたら、このメールへの返信またはお電話にてお気軽にお問い合わせください。
 
━━━━━━━━━━━━━━━━━━
〒336-0932
埼玉県さいたま市緑区中尾1507-1
埼玉保育教販株式会社
TEL：048-873-3333
FAX：048-873-3335
━━━━━━━━━━━━━━━━━━<?= htmlspecialchars($note_quote) ?></textarea>
      <button type="submit" class="btn btn-green">✉ 送信する</button>
    </form>
  </div>
 
  <!-- 返信履歴 -->
  <?php if (!empty($reply_logs)): ?>
  <div class="card">
    <div class="card-title">返信履歴</div>
    <?php foreach ($reply_logs as $log): ?>
      <div class="log-item">
        <div class="log-date"><?= date('Y年m月d日 H:i', strtotime($log['sent_at'])) ?></div>
        <div class="log-subject">件名：<?= htmlspecialchars($log['subject']) ?></div>
        <div class="log-body"><?= htmlspecialchars($log['body']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
 
  <!-- 対応管理 -->
  <div class="card">
    <div class="card-title">対応管理</div>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <label>ステータス変更</label>
      <select name="status">
        <?php foreach (['未対応','対応中','完了','キャンセル'] as $s): ?>
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
 
      <button type="submit" class="btn" id="normalSaveBtn" <?= $row['status'] === '完了' ? 'style="display:none"' : '' ?>>保存する</button>
    </form>
 
    <?php if ($row['type'] === '通常発注'): ?>
    <hr style="margin:20px 0;border:none;border-top:1px solid #eef0f5">
    <p style="font-size:13px;color:#555;margin-bottom:12px" id="completeBtnLabel" <?= $row['status'] !== '完了' ? 'style="display:none"' : '' ?>>発注完了時はこちらから完了メールを送信できます</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap" id="completeBtns" <?= $row['status'] !== '完了' ? 'style="display:none"' : '' ?>>
      <form method="post" onsubmit="return confirmComplete()">
        <input type="hidden" name="action" value="complete">
        <button type="submit" class="btn btn-green">✉ 発注完了メールを送信して保存</button>
      </form>
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="status" value="完了">
        <input type="hidden" name="staff_id" value="<?= $row['staff_id'] ?>">
        <input type="hidden" name="admin_memo" value="<?= htmlspecialchars($row['admin_memo'] ?? '') ?>">
        <button type="submit" class="btn" style="background:#607d8b">メール送信せずに完了保存</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
 
</div>
 
<script>
function copyCode(btn, code) {
  navigator.clipboard.writeText(code).then(() => {
    btn.textContent = 'コピー済';
    btn.classList.add('copied');
    setTimeout(() => {
      btn.textContent = 'コピー';
      btn.classList.remove('copied');
    }, 2000);
  });
}
function confirmComplete() {
  return confirm('発注完了メールをお客様に送信します。よろしいですか？');
}
 
// ステータス切替でボタン表示を制御
document.addEventListener('DOMContentLoaded', function() {
  const statusSelect = document.querySelector('select[name="status"]');
  if (!statusSelect) return;
 
  function toggleButtons() {
    const isComplete = statusSelect.value === '完了';
    const normalBtn  = document.getElementById('normalSaveBtn');
    const completeLabel = document.getElementById('completeBtnLabel');
    const completeBtns  = document.getElementById('completeBtns');
    if (normalBtn)      normalBtn.style.display      = isComplete ? 'none'  : '';
    if (completeLabel)  completeLabel.style.display  = isComplete ? ''      : 'none';
    if (completeBtns)   completeBtns.style.display   = isComplete ? 'flex'  : 'none';
  }
 
  statusSelect.addEventListener('change', toggleButtons);
});
</script>
</body>
</html>
