# otoiawase-form
受発注・お問い合わせフォーム
ファイル構成
/
├── index.html          # フォームページ（公開）
├── submit.php          # 受信・保存・メール送信
├── config.php          # ★ DB・メール設定（GitHubに上げない）
├── css/
│   └── style.css
├── sql/
│   └── create_table.sql
├── uploads/            # 修理写真保存先（自動生成）
└── .gitignore
さくらサーバ セットアップ手順
1. MySQLデータベース作成
さくらのコントロールパネル → データベース → 新規作成
2. テーブル作成
phpMyAdmin にて sql/create_table.sql を実行
3. config.php を設定
phpdefine('DB_HOST', 'mysqlXXX.db.sakura.ne.jp');  // さくらのMySQLホスト
define('DB_NAME', 'アカウント名_db名');
define('DB_USER', 'アカウント名');
define('DB_PASS', 'パスワード');
define('MAIL_FROM',  'noreply@yourdomain.jp');
define('MAIL_ADMIN', 'admin@yourdomain.jp');
define('MAIL_FROM_NAME', '会社名');
4. ファイルをアップロード
config.php は手動でFTPアップロード（GitHubからは除外済み）
5. uploadsフォルダのパーミッション
chmod 755 uploads/
機能一覧

種別：通常発注 / 納期確認 / 修理依頼 / その他
商品行の動的追加・削除
修理依頼時のみ写真添付・症状入力を表示
フォーム送信後に問い合わせ番号を発行（例：INQ-20240428-001）
お客様への自動返信メール送信
管理者への通知メール送信
MySQLへの保存（将来の管理画面のため）

今後の拡張予定

管理画面（一覧・ステータス管理・社内メモ）
CSRFトークン対応
reCAPTCHA対応
