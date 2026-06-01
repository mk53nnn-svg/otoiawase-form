<?php
// ============================================================
//  config.php  ※ GitHubには上げず .gitignore に追加すること
// ============================================================
 
// --- データベース設定 ---
define('DB_HOST', 'mysql3110.db.sakura.ne.jp'); // データベース一覧のホスト名
define('DB_NAME', 'hoikukyouhan_order_system');
define('DB_USER', 'hoikukyouhan');
define('DB_PASS', 'hoiku3333');

define('DB_CHARSET', 'utf8mb4');
 
// --- メール設定 ---
define('MAIL_FROM',     'noreply@hoikukyouhan.co.jp');
define('MAIL_FROM_NAME', '埼玉保育教販株式会社');
define('MAIL_ADMIN',    'hoikukyouhan@yahoo.co.jp');
 
// --- アップロード設定 ---
define('UPLOAD_DIR', __DIR__ . '/uploads/');        // 修理写真保存先
define('UPLOAD_MAX_MB', 5);
 
// --- タイムゾーン ---
date_default_timezone_set('Asia/Tokyo');
 
