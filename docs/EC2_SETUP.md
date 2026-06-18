# EC2 / GitHub 事前準備手順

本デモのデプロイ先となる AWS EC2 と、GitHub Actions の接続設定をまとめます。
（デモ用の最小構成です。実運用ではユーザ権限・ファイアウォールをより厳格にしてください）

---

## A. EC2 インスタンスの起動

1. AWS マネジメントコンソール → EC2 → **インスタンスを起動**
2. AMI: **Amazon Linux 2**（無料枠 `t2.micro` / `t3.micro` で可）
3. キーペア: 新規作成し `.pem` を保管（SSH ログイン用）
4. セキュリティグループ（インバウンド）:
   - SSH (22) … **自分のグローバル IP のみ**
   - HTTP (80) … デモ中だけ `0.0.0.0/0`（公開）
5. 起動後、**パブリック IPv4 DNS**（`ec2-...compute.amazonaws.com`）を控える

```bash
# 手元(Git Bash)から SSH ログイン
ssh -i your-key.pem ec2-user@<EC2のパブリックDNS>
```
C:\Users\pente\key\deploydemo.pem
ssh -i C:\Users\pente\key\deploydemo.pem ec2-user@43.207.129.59
---

## B. EC2 にミドルウェアを導入（Amazon Linux 2）

```bash
sudo yum update -y

# Apache
sudo yum install -y httpd

# PHP（amazon-linux-extras で 7.4 を有効化）
sudo amazon-linux-extras enable php7.4
sudo yum clean metadata
sudo yum install -y php php-mysqlnd php-mbstring

# git（Deployer がサーバ上で git clone を実行するため必須）
sudo yum install -y git

# MariaDB（デモ用 DB。本格運用は RDS 推奨）
sudo yum install -y mariadb-server
sudo systemctl enable --now mariadb
sudo systemctl enable --now httpd
```

### デモ用 DB を作成

```bash
sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS demo_app DEFAULT CHARACTER SET utf8;
CREATE USER IF NOT EXISTS 'demo'@'127.0.0.1' IDENTIFIED BY 'demopass';
GRANT ALL PRIVILEGES ON demo_app.* TO 'demo'@'127.0.0.1';
FLUSH PRIVILEGES;

USE demo_app;
CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  body TEXT,
  published_at DATETIME
);
INSERT INTO announcements (title, body, published_at) VALUES
('EC2 稼働中', 'これは EC2 上の DB から取得したお知らせです。', '2026-06-16 12:00:00');
SQL
```

---

## C. デプロイ先ディレクトリと shared 設定

```bash
# デプロイ先ルート
sudo mkdir -p /var/www/ci-cd-demo
sudo chown -R ec2-user:ec2-user /var/www/ci-cd-demo

# shared に EC2 用の database.php を先に置く（リポジトリには含めないため）
mkdir -p /var/www/ci-cd-demo/shared/src/application/config
cat > /var/www/ci-cd-demo/shared/src/application/config/database.php <<'PHP'
<?php
$active_group  = 'default';
$query_builder = TRUE;
$db['default'] = array(
    'hostname' => '127.0.0.1',
    'username' => 'demo',
    'password' => 'demopass',
    'database' => 'demo_app',
    'port'     => 3306,
    'dbdriver' => 'mysqli',
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
);
PHP
```

---

## D. Apache の DocumentRoot を current/src に向ける（初回のみ）

本デモはアプリを `src/` 配下に置いているため、公開ルートは `current/src` です。

```bash
sudo tee /etc/httpd/conf.d/ci-cd-demo.conf >/dev/null <<'CONF'
<VirtualHost *:80>
    DocumentRoot /var/www/ci-cd-demo/current/src
    DirectoryIndex index.php index.html
    <Directory /var/www/ci-cd-demo/current/src>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
CONF

# 既定の welcome ページを無効化（任意）
sudo sed -i 's/^/#/' /etc/httpd/conf.d/welcome.conf 2>/dev/null || true

sudo systemctl reload httpd
```

> current はまだ存在しません（初回デプロイで作られます）。デプロイ後に有効になります。

---

## E. デプロイ用 SSH 鍵（GitHub Actions → EC2）

GitHub Actions のランナーから EC2 へ SSH 接続するための専用鍵を作ります。

```bash
# 手元(Git Bash)で生成
ssh-keygen -t ed25519 -C "github-actions-deploy" -f deploy_key
#  → deploy_key（秘密鍵）, deploy_key.pub（公開鍵）が生成される
```

1. **公開鍵を EC2 に登録**
   ```bash
   # deploy_key.pub の中身を EC2 の ec2-user に登録
   cat deploy_key.pub | ssh -i your-key.pem ec2-user@<EC2のパブリックDNS> \
     'mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys'
   ```
→ 手動でコピペする

2. **秘密鍵を GitHub の Secrets に登録**
   - リポジトリ → Settings → Secrets and variables → Actions → New repository secret
   - Name: `PRIVATE_KEY`
   - Value: `deploy_key` の中身を**改行ごとすべて**貼り付け（`-----BEGIN ...` から `-----END ...` まで）

> `deploy_key` / `deploy_key.pub` は `.gitignore` 済みなのでリポジトリには入りません。

---

## F. deploy.php の書き換え

```php
set('repository', 'git@github.com:YOUR_GITHUB_USER/ci-cd-demo.git');

host('test')
    ->setHostname('YOUR_EC2_PUBLIC_DNS')   // 例: ec2-xx-...compute.amazonaws.com
    ->setRemoteUser('ec2-user')
    ->set('branch', 'main')
    ->set('deploy_path', '/var/www/ci-cd-demo');
```

EC2 が GitHub からリポジトリを clone できるよう、必要なら EC2 側にも
GitHub アクセス用の鍵（Deploy key 等）を設定します。プライベートリポジトリの場合は
リポジトリの **Settings → Deploy keys** に EC2 の公開鍵を登録するのが簡単です。
（パブリックリポジトリなら不要：HTTPS clone でも可）

---

## G. 動作確認（初回デプロイ）

GitHub Actions 経由（推奨）:
```bash
git commit --allow-empty -m "ci: 初回デプロイ確認"
git push
# → Actions タブで deploy ワークフローが成功するのを確認
# → http://<EC2のIP>/ を開く
```

手元から直接（PHP 8.3 がある場合）:
```bash
composer require deployer/deployer --dev
vendor/bin/dep deploy test -vvv
```

成功すると EC2 上に次が作られます。
```
/var/www/ci-cd-demo/
├── releases/1/ (2/ ...)        ← 各デプロイの実体
├── shared/src/application/...  ← database.php / cache / logs
└── current -> releases/N       ← 公開中（Apache はここを見る）
```
