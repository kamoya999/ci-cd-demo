-- =====================================================================
--  デモ用 初期化 SQL（MySQL 5.7）
--  docker-entrypoint-initdb.d に置くと初回起動時に自動実行される。
-- =====================================================================

CREATE DATABASE IF NOT EXISTS demo_app DEFAULT CHARACTER SET utf8;
USE demo_app;

CREATE TABLE IF NOT EXISTS announcements (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(200) NOT NULL,
  body         TEXT,
  published_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO announcements (title, body, published_at) VALUES
('サイトリニューアルのお知らせ', '新デザインへ移行しました。引き続きよろしくお願いします。', '2026-06-01 10:00:00'),
('メンテナンス実施について',     '6/10 深夜にサーバメンテナンスを実施します。',             '2026-06-05 18:30:00'),
('CI/CD 導入完了',               'FTP 運用から Git + Deployer + GitHub Actions へ移行しました。', '2026-06-16 09:00:00');

-- demo ユーザー（MYSQL_USER で作成済み）に demo_app の権限を付与（保険）
GRANT ALL PRIVILEGES ON demo_app.* TO 'demo'@'%';
FLUSH PRIVILEGES;
