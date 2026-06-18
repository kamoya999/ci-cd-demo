# CI/CD オートデプロイ デモ一式

ローカル（Windows ノート PC）の **Docker Desktop** で動かしたアプリを、
**Git for Windows → GitHub（personal）→ GitHub Actions → Deployer → AWS EC2**
の経路で「push するだけで自動デプロイ」されるところまでをプレゼンでデモするための一式です。

添付マニュアル「CI/CD 導入・デプロイ手順マニュアル PHASE1」の構成に沿い、
デプロイ先だけを **AWS EC2** に置き換えています。

---

## 1. 全体像

```
  [ローカル Windows ノート PC]
   Docker Desktop
   ┌──────────────────────────────┐
   │ web: CentOS6 + Apache + PHP5.3│  ← 本番互換（XAMPP から移行）
   │ db : MySQL 5.7               │
   └──────────────────────────────┘
        │ コード編集
        ▼
   git commit / git push  ──►  GitHub (personal リポジトリ)
                                    │ main への push を契機に
                                    ▼
                              GitHub Actions
                              （PHP 8.3 ランナーで Deployer 実行）
                                    │ SSH
                                    ▼
                              AWS EC2（テスト環境）
                              releases/ shared/ current で
                              ゼロダウンタイム + ロールバック
```

コードの流れは一方向：**ローカルで開発 → push → GitHub Actions が EC2 へ自動デプロイ**。

---

## 2. ディレクトリ構成

```
ci-cd-demo/
├── README.md                       ← このファイル
├── docker-compose.yml              ← ローカル環境（web + db）
├── deploy.php                      ← Deployer 設定（EC2 向け）
├── .gitignore
├── .github/workflows/deploy.yml    ← GitHub Actions（自動デプロイ）
├── docker/
│   ├── web/
│   │   ├── Dockerfile              ← CentOS6 + Apache + PHP5.3
│   │   ├── Dockerfile.php56        ← 代替: 公式 php:5.6（exit139 回避用）
│   │   └── httpd-vhost.conf
│   └── db/initdb/01_demo.sql       ← デモデータ（自動投入）
├── src/                            ← 公開アプリ（DocumentRoot）
│   ├── index.php                   ← デモページ（ここを編集してデプロイ実演）
│   ├── .htaccess
│   └── application/
│       ├── config/
│       │   ├── database.php        ← ローカル用（.gitignore 対象）
│       │   └── database.sample.php ← 雛形（コミットする）
│       ├── cache/index.html
│       └── logs/index.html
└── docs/
    ├── EC2_SETUP.md                ← EC2 / GitHub 事前準備（要熟読）
    └── DEMO_SCRIPT.md              ← 当日の進行台本（時間配分つき）
```

---

## 3. 事前準備チェックリスト

| # | 準備するもの | 補足 |
|---|---|---|
| 1 | Docker Desktop（Windows / WSL2） | ローカル環境の起動に使用 |
| 2 | Git for Windows | push と SSH 鍵に使用 |
| 3 | GitHub アカウント（personal） | 空のリポジトリ `ci-cd-demo` を作成 |
| 4 | AWS EC2 インスタンス（テスト用） | `docs/EC2_SETUP.md` 参照 |
| 5 | デプロイ用 SSH 鍵ペア | `docs/EC2_SETUP.md` 参照 |

> **重要**: 当日いきなり通すのは危険です。前日までに「4. ローカル起動」と
> 「5〜7（GitHub / EC2 / Actions）」を一度通し、`docs/DEMO_SCRIPT.md` でリハーサルしてください。

---

## 4. ローカル環境の起動（Docker Desktop）

```bash
cd ci-cd-demo

# 初回はビルドに数分かかる（CentOS6 イメージ取得 + PHP5.3 導入）
docker compose up -d --build

# ブラウザで確認
#   http://localhost:8080
#   → 青いバナーに「APP v1.0.0」、下にお知らせ一覧（MySQL から取得）が出れば成功
```

> **⚠ ビルドが exit 139 で失敗する場合**（WSL2/Docker Desktop でよくある）
> CentOS6 の古い glibc と WSL2 の新カーネルの非互換が原因です。次のどちらかで解決します。
> - **(A) PHP5.3 を維持**: `C:\Users\<ユーザー名>\.wslconfig` に下記を書き、`wsl --shutdown` → Docker 再起動 → 再ビルド。
>   ```ini
>   [wsl2]
>   kernelCommandLine = vsyscall=emulate
>   ```
> - **(B) WSL2 を触らない**: `docker-compose.yml` の `web.build.dockerfile` を `Dockerfile.php56` に変更（公式 php:5.6-apache。本デモアプリはそのまま動作）。

- `src/application/config/database.php` は ZIP に同梱済みなので、そのまま起動すれば DB に繋がります。
- 停止: `docker compose down` ／ DB ごと初期化: `docker compose down -v`

> **PHP バージョンの確認**: 画面の「PHP」チップに `5.3.x` と表示されれば、
> 本番互換の PHP 5.3 がローカルで動いています（XAMPP では再現しにくい点）。

---

## 5. GitHub（personal）へ push

```bash
# まだ Git 管理していない場合
git init
git branch -M main
git add .
git status        # ← database.php と vendor/ が「含まれていない」ことを必ず確認

git commit -m "chore: CI/CD デモ初期取り込み"

# GitHub で空のリポジトリ ci-cd-demo を作成しておく
git remote add origin git@github.com:YOUR_GITHUB_USER/ci-cd-demo.git
git push -u origin main
```

push 後、GitHub 上で `src/application/config/database.php` が**存在しない**ことを確認してください
（機密がリポジトリに残らない運用の確認）。

---

## 6. AWS EC2 の準備

`docs/EC2_SETUP.md` の手順で次を済ませます。

- EC2 起動（Amazon Linux 2 / セキュリティグループ 22・80）
- Apache + PHP + MySQL(MariaDB) + git の導入
- デプロイ先 `/var/www/ci-cd-demo` 作成と DocumentRoot 設定
- `shared/src/application/config/database.php`（EC2 用 DB 設定）の配置
- デプロイ用 SSH 鍵の `authorized_keys` 登録

`deploy.php` の `repository` と `host('test')->setHostname()` を自分の値に書き換えます。

---

## 7. GitHub Actions の有効化（Secrets 登録）

1. デプロイ用秘密鍵を GitHub に登録
   - リポジトリ → **Settings → Secrets and variables → Actions → New repository secret**
   - Name: `PRIVATE_KEY` ／ Value: デプロイ用秘密鍵（`deploy_key`）の中身全部
2. `.github/workflows/deploy.yml` は同梱済み。`main` への push で自動起動します。

---

## 8. デプロイ実演（デモの山場）

```bash
# 1) src/index.php を編集
#    $APP_VERSION  = 'v1.0.0'  →  'v1.1.0'
#    $BANNER_COLOR = '#2563eb' →  '#16a34a'   （青→緑にすると変化が一目瞭然）

git add src/index.php
git commit -m "feat: バナーを更新（v1.1.0）"
git push
```

- GitHub の **Actions** タブでワークフローの進行をスクリーンに映す。
- 完了後、**EC2 の URL（http://EC2のIP/）** を再読み込み → バナーが緑「APP v1.1.0」に変化。
- お知らせ一覧（DB の中身）は変わらない＝**コードはリリースで切替、データは shared で永続**、を説明。

詳しい台本は `docs/DEMO_SCRIPT.md`。

---

## 9. ロールバック実演

```bash
# 手元から（PHP 8.3 が必要。無ければ Actions 経由で十分）
vendor/bin/dep rollback test
```

`current` シンボリックリンクが1つ前の release へ張り替わるだけなので一瞬で復旧します。

---

## 10. トラブルシューティング（抜粋）

| 症状 | 主な原因 | 対処 |
|---|---|---|
| `docker compose build` が **exit 139** で失敗 | CentOS6 の古い glibc が WSL2 の新カーネル（vsyscall 無効）と非互換で yum が SIGSEGV | **(A)** `C:\Users\<名>\.wslconfig` に `[wsl2]` `kernelCommandLine = vsyscall=emulate` を追加 → `wsl --shutdown` → Docker 再起動 → 再ビルド。**(B)** WSL2 を触らないなら `docker-compose.yml` の `dockerfile:` を `Dockerfile.php56` に変更（公式 php:5.6） |
| `docker compose up` で yum エラー | CentOS6 の vault 接続不可 | ネットワーク／プロキシ確認。`docker/web/Dockerfile` の baseurl が vault を指しているか |
| 画面に「DB接続失敗」 | db コンテナ未起動／初期化途中 | `docker compose logs db` で `ready for connections` を待つ |
| Actions: `Permission denied (publickey)` | EC2 に公開鍵未登録／Secrets 誤り | `authorized_keys` と Secrets `PRIVATE_KEY` を確認 |
| EC2 で 500 / 白画面 | DocumentRoot が current/src を向いていない／shared 未配置 | `docs/EC2_SETUP.md` の DocumentRoot と shared を確認 |
| 変更が反映されない | DocumentRoot が current を見ていない | Apache 設定を確認し reload |

---

## 補足：本デモでの割り切り（プレゼンで説明できるように）

- **OS イメージ**: CentOS 6.5 は EOL のため Docker では 6.6 を使用（PHP は 6.x いずれも 5.3.3）。
- **EC2 側 PHP**: 実運用の 5.3 を EC2 で再現するのは現実的でないため、デモでは EC2 は新しめの PHP（7.4 等）。
  本デモアプリは `mysqli` のみ使用し PHP 5.3〜8 で動くよう書いているため、ローカル 5.3／EC2 7.4 の両方で動作します。
- **アプリ**: CodeIgniter フレームワーク本体は同梱せず、CI2 のディレクトリ流儀（`application/config/database.php` を shared）に
  そろえた軽量サンプルにしています。パイプライン（push→自動デプロイ）の理解に集中するためです。
