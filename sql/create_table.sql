-- 問い合わせ・発注テーブル
CREATE TABLE IF NOT EXISTS inquiries (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_no  VARCHAR(20)  NOT NULL UNIQUE,          -- 例: INQ-20240428-001
    type        VARCHAR(20)  NOT NULL,                  -- 通常発注/納期確認/修理依頼/その他
    garden_name VARCHAR(100) NOT NULL,
    contact_name VARCHAR(100) NOT NULL,
    phone       VARCHAR(30)  NOT NULL,
    email       VARCHAR(200) NOT NULL,
    items       TEXT         NULL,                      -- 商品情報（JSON）
    repair_symptom TEXT      NULL,                      -- 修理依頼の症状
    repair_image VARCHAR(300) NULL,                     -- 修理写真ファイル名
    note        TEXT         NULL,                      -- 備考
    status      ENUM('未対応','確認中','対応中','完了') DEFAULT '未対応',
    admin_memo  TEXT         NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
