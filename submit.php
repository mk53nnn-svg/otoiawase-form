<?php
// ============================================================
//  submit.php  フォーム受信 → DB保存 → メール送信
// ============================================================
require_once __DIR__ . '/config.php';
 
header('Content-Type: application/json; charset=utf-8');
 
// CSRF簡易対策: POSTのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}
 
// ---------- 入力値取得・サニタイズ ----------
function h(string $v): string {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}
 
$type         = h($_POST['type']         ?? '');
$garden_name  = h($_POST['garden_name']  ?? '');
$contact_name = h($_POST['contact_name'] ?? '');
$phone        = h($_POST['phone']        ?? '');
$email        = h($_POST['email']        ?? '');
$note         = h($_POST['note']         ?? '');
 
// バリデーション
$errors = [];
if (empty($garden_name))  $errors[] = '園名は必須です';
if (empty($contact_name)) $errors[] = '担当者名は必須です';
if (empty($phone))        $errors[] = '電話番号は必須です';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '有効なメールアドレスを入力してください';
}
$allowed_types = ['通常発注', '納期確認', '修理依頼', 'その他'];
if (!in_array($type, $allowed_types, true)) {
    $errors[] = '種別が不正です';
}
 
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('／', $errors)]);
    exit;
}
 
// ---------- 商品情報（通常発注・納期確認） ----------
$items_json = null;
if (in_array($type, ['通常発注', '納期確認'])) {
    $items_raw = $_POST['items'] ?? [];
    $items = [];
    foreach ($items_raw as $item) {
        $name = trim($item['name'] ?? '');
        $code = trim($item['code'] ?? '');
        $qty  = trim($item['qty']  ?? '');
        if ($name !== '' || $code !== '' || $qty !== '') {
            $items[] = [
                'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                'code' => htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
                'qty'  => htmlspecialchars($qty,  ENT_QUOTES, 'UTF-8'),
            ];
        }
    }
    $items_json = !empty($items) ? json_encode($items, JSON_UNESCAPED_UNICODE) : null;
}
 
// ---------- 修理写真アップロード ----------
$repair_symptom = null;
$repair_image   = null;
if ($type === '修理依頼') {
    $repair_symptom = h($_POST['repair_symptom'] ?? '');
 
    if (isset($_FILES['repair_image']) && $_FILES['repair_image']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['repair_image'];
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mime     = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
 
        if (!in_array($mime, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => '写真はJPEG/PNG/GIF/WEBPのみ対応しています']);
            exit;
        }
        if ($file['size'] > UPLOAD_MAX_MB * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => '写真のサイズは' . UPLOAD_MAX_MB . 'MB以内にしてください']);
            exit;
        }
 
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        $ext          = pathinfo($file['name'], PATHINFO_EXTENSION);
        $repair_image = uniqid('img_', true) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $repair_image);
    }
}
 
// ---------- 問い合わせ番号生成 ----------
function generate_inquiry_no(PDO $pdo): string {
    $date   = date('Ymd');
    $prefix = 'INQ-' . $date . '-';
    $stmt   = $pdo->prepare(
        "SELECT COUNT(*) FROM inquiries WHERE inquiry_no LIKE ?"
    );
    $stmt->execute([$prefix . '%']);
    $count = (int)$stmt->fetchColumn();
    return $prefix . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}
 
// ---------- DB保存 ----------
try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
 
    $inquiry_no = generate_inquiry_no($pdo);
 
    $stmt = $pdo->prepare("
        INSERT INTO inquiries
            (inquiry_no, type, garden_name, contact_name, phone, email,
             items, repair_symptom, repair_image, note)
        VALUES
            (:inquiry_no, :type, :garden_name, :contact_name, :phone, :email,
             :items, :repair_symptom, :repair_image, :note)
    ");
    $stmt->execute([
        ':inquiry_no'      => $inquiry_no,
        ':type'            => $type,
        ':garden_name'     => $garden_name,
        ':contact_name'    => $contact_name,
        ':phone'           => $phone,
        ':email'           => $email,
        ':items'           => $items_json,
        ':repair_symptom'  => $repair_symptom,
        ':repair_image'    => $repair_image,
        ':note'            => $note,
    ]);
 
} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'システムエラーが発生しました。しばらく経ってから再度お試しください。']);
    exit;
}
 
// ---------- メール本文生成 ----------
function build_items_text(?string $items_json): string {
    if (empty($items_json)) return '';
    $items = json_decode($items_json, true);
    if (!$items) return '';
    $lines = [];
    foreach ($items as $i => $item) {
        $lines[] = sprintf(
            '  商品%d: %s（コード：%s）　数量：%s',
            $i + 1,
            $item['name'] ?: '－',
            $item['code'] ?: '－',
            $item['qty']  ?: '－'
        );
    }
    return implode("\n", $lines);
}
 
$items_text = build_items_text($items_json);
 
$body_common = <<<EOT
■ 問い合わせ番号：{$inquiry_no}
■ 種別：{$type}
■ 園名：{$garden_name}
■ 担当者：{$contact_name}
■ 電話：{$phone}
■ メール：{$email}
EOT;
 
if ($items_text) {
    $body_common .= "\n■ 商品情報：\n" . $items_text;
}
if ($repair_symptom) {
    $body_common .= "\n■ 症状・状態：{$repair_symptom}";
}
if ($note) {
    $body_common .= "\n■ 備考：{$note}";
}
 
// ---------- 自動返信メール（お客様宛） ----------
$subject_client = "【受付完了】お問い合わせ番号 {$inquiry_no}";
$body_client = <<<EOT
{$contact_name} 様
 
このたびはお問い合わせいただきありがとうございます。
以下の内容で受け付けました。
 
────────────────────────────
{$body_common}
────────────────────────────
 
内容を確認のうえ、担当者よりご連絡いたします。
今しばらくお待ちくださいますようお願いいたします。
 
※ このメールは自動送信です。返信いただいても対応できません。
 
--
MAIL_FROM_NAME
EOT;
 
// ---------- 管理者通知メール ----------
$subject_admin = "【新着】{$type}　{$garden_name}（{$inquiry_no}）";
$body_admin = <<<EOT
新しい問い合わせが届きました。
 
────────────────────────────
{$body_common}
────────────────────────────
 
EOT;
if ($repair_image) {
    $body_admin .= "■ 写真ファイル：{$repair_image}\n";
}
 
// ---------- メール送信関数 ----------
function send_mail(string $to, string $subject, string $body): bool {
    $from      = MAIL_FROM;
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
    return mail($to, $subject_enc, $body_enc, $headers);
}
 
send_mail($email,       $subject_client, $body_client);
send_mail(MAIL_ADMIN,   $subject_admin,  $body_admin);
 
// ---------- レスポンス ----------
echo json_encode([
    'success'    => true,
    'inquiry_no' => $inquiry_no,
    'message'    => '受付が完了しました',
]);
