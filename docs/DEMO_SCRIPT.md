# デモ進行台本（プレゼン用 / 目安 12〜15 分）

事前にローカル起動・GitHub push・EC2 準備・Actions 設定を**済ませた状態**で開始します。
画面は (1) ブラウザ（ローカル localhost:8080 と EC2 の2タブ）、(2) ターミナル(Git Bash)、(3) GitHub の Actions タブ、を用意。

---

## 0. つかみ（1 分）

- 「これまでは FTP で本番に直接アップロードしていました。誰が何を変えたか残らず、
  アップロード中に壊れることもありました。」
- 「今日は、**手元のコードを push するだけで、自動でテスト環境（AWS EC2）へ
  ゼロダウンタイムでデプロイされる**ところまでをお見せします。」
- README 冒頭の全体像の図を1枚見せる。

---

## 1. ローカル環境（Docker）を見せる（2 分）

```bash
docker compose ps          # web(CentOS6/PHP5.3) と db(MySQL5.7) が Up
```
- ブラウザ `http://localhost:8080` を開く。
- ポイント説明:
  - 「**PHP 5.3**」チップを指して「本番と同じ古い PHP を Docker で再現しています。
    XAMPP だと再現しにくいバージョン差異を、ローカルで先に潰せます。」
  - お知らせ一覧は「MySQL 5.7 から取得しています」。

---

## 2. コードを変更する（2 分）

`src/index.php` の先頭を編集して見せる:
```php
$APP_VERSION  = 'v1.0.0';   →  'v1.1.0';
$BANNER_COLOR = '#2563eb';  →  '#16a34a';   // 青 → 緑
```
- ブラウザ localhost を再読み込み → **ローカルでは即、緑/v1.1.0 に変わる**ことを見せる
  （ボリュームマウントなのでビルド不要）。

---

## 3. push する（1 分）

```bash
git add src/index.php
git commit -m "feat: バナーを v1.1.0 / 緑 に更新"
git push
```
- 「この push が引き金になります。」と言いながら GitHub の **Actions タブ**に切替。

---

## 4. GitHub Actions の自動実行を見せる（3 分）

- Actions タブで `deploy` ワークフローが起動 → ジョブのログを開く。
- ステップを指しながら説明:
  1. **Setup PHP 8.3** … 「Deployer は PHP 8.3 で動きます。手元の PHP 構成に縛られません。」
  2. **Deploy（deployphp/action）** … 「Secrets の鍵で EC2 へ SSH し、Deployer が
     `git clone → shared/writable 設定 → current 切替` を自動実行します。」
- ログの `Successfully deployed!` を確認。

---

## 5. EC2 で結果を確認（2 分）

- EC2 の URL `http://<EC2のIP>/` を**再読み込み**。
- **バナーが緑「APP v1.1.0」に変化**、ホスト名チップが EC2 のものになっていることを指す。
- 「お知らせ一覧（DB）は変わっていません。**コードはリリースごとに切替、データは shared で永続**。
  これがゼロダウンタイムとロールバックの土台です。」

---

## 6. ロールバック（任意 / 1〜2 分）

- 問題が見つかった想定で:
```bash
vendor/bin/dep rollback test     # 手元に PHP8.3 がある場合
```
- 「`current` を1つ前の release に張り替えるだけなので一瞬で戻ります。」
- EC2 を再読み込みして v1.0.0 / 青に戻ることを見せる。

> PHP 8.3 が手元に無い場合は、GitHub で1つ前のコミットに revert して push し直す形でも
> 同じ「戻し」を実演できます。

---

## 7. まとめ（1 分）

- Before/After を一言で:
  - 「履歴が残り、戻せて、止まらない。push だけで誰がやっても同じ手順。」
- 今後の発展（PHASE2）:
  - test と production のホストを分ける、Docker 化を全案件へ展開、PHP を 5.3→7→8 へ段階的に引き上げ。

---

## デモ前 最終チェック（前夜にやる）

- [ ] `docker compose up -d --build` でローカルが緑バナー＋お知らせ表示まで通る
- [ ] GitHub に push 済み、`database.php` が含まれていない
- [ ] EC2 へ初回デプロイが成功し、`http://<EC2>/` が表示される
- [ ] `deploy.php` の repository / hostname / user が自分の値
- [ ] Secrets `PRIVATE_KEY` 登録済み、EC2 の authorized_keys に公開鍵あり
- [ ] 変更 → push → Actions 成功 → EC2 反映、を一度通しでリハーサル済み
- [ ] ネットワーク（会場 Wi-Fi）で GitHub / AWS にアクセスできる
