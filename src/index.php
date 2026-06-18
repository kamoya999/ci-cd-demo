<?php
// =====================================================================
//  CI/CD デモアプリ（PHP 5.3 互換 / mysqli 使用）
//
//  このファイルを編集 → git commit → git push すると、
//  GitHub Actions 経由で EC2 へ自動デプロイされ、画面が変わることを
//  デモするためのサンプルです。
//
//  ※ 5.3 で動かすため、短縮配列 [] や <?= は使わず array() / echo を使用。
// =====================================================================

// ▼▼▼ デモ実演で書き換える箇所 ▼▼▼
$APP_VERSION  = 'v1.0.0';     // デプロイのたびに上げると変化が分かりやすい
$BANNER_COLOR = '#2563eb';    // 青。実演で '#16a34a'（緑）等に変えると一目で違いが出る
$BANNER_TEXT  = 'ローカル Docker → GitHub Actions → AWS EC2 自動デプロイ デモ';
// ▲▲▲ ここまで ▲▲▲

date_default_timezone_set('Asia/Tokyo');

// --- DB 設定の読み込み（CI2 形式の application/config/database.php） ---
$dbConfigFile = dirname(__FILE__) . '/application/config/database.php';
$db = array();
if (file_exists($dbConfigFile)) {
    require $dbConfigFile;   // この中で $db['default'] が定義される
}
$conf = isset($db['default']) ? $db['default'] : array();

$rows    = array();
$dbError = '';

if (!empty($conf)) {
    $mysqli = @mysqli_connect(
        isset($conf['hostname']) ? $conf['hostname'] : 'localhost',
        isset($conf['username']) ? $conf['username'] : '',
        isset($conf['password']) ? $conf['password'] : '',
        isset($conf['database']) ? $conf['database'] : '',
        isset($conf['port'])     ? (int)$conf['port'] : 3306
    );
    if ($mysqli) {
        @mysqli_set_charset($mysqli, 'utf8');
        $res = @mysqli_query($mysqli, 'SELECT id, title, body, published_at FROM announcements ORDER BY id DESC');
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $rows[] = $r;
            }
        } else {
            $dbError = 'クエリ失敗: ' . mysqli_error($mysqli);
        }
        mysqli_close($mysqli);
    } else {
        $dbError = 'DB接続失敗: ' . mysqli_connect_error();
    }
} else {
    $dbError = 'database.php が見つからない、または未設定です。';
}

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$host = php_uname('n');
$phpv = phpversion();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CI/CD デモ - <?php echo h($APP_VERSION); ?></title>
<style>
  body { font-family: -apple-system, "Segoe UI", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
         margin: 0; background: #f3f4f6; color: #111827; }
  .banner { background: <?php echo h($BANNER_COLOR); ?>; color: #fff; padding: 28px 24px; }
  .banner h1 { margin: 0 0 6px; font-size: 20px; }
  .version { font-size: 40px; font-weight: 800; letter-spacing: 1px; }
  .wrap { max-width: 880px; margin: 24px auto; padding: 0 16px; }
  .meta { display: flex; flex-wrap: wrap; gap: 12px; margin: 16px 0 24px; }
  .chip { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
          padding: 8px 12px; font-size: 13px; }
  .chip b { color: #6b7280; font-weight: 600; }
  table { width: 100%; border-collapse: collapse; background: #fff;
          border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
  th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #f0f0f0;
           font-size: 14px; vertical-align: top; }
  th { background: #f9fafb; color: #6b7280; font-weight: 600; }
  .err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
         padding: 12px 14px; border-radius: 8px; font-size: 14px; }
  .date { color: #9ca3af; font-size: 12px; white-space: nowrap; }
  footer { text-align: center; color: #9ca3af; font-size: 12px; margin: 28px 0; }
</style>
</head>
<body>
  <div class="banner">
    <h1><?php echo h($BANNER_TEXT); ?></h1>
    <div class="version">APP <?php echo h($APP_VERSION); ?></div>
  </div>

  <div class="wrap">
    <div class="meta">
      <div class="chip"><b>ホスト名</b> <?php echo h($host); ?></div>
      <div class="chip"><b>PHP</b> <?php echo h($phpv); ?></div>
      <div class="chip"><b>表示時刻</b> <?php echo h(date('Y-m-d H:i:s')); ?></div>
    </div>

    <h2 style="font-size:16px;">お知らせ一覧（MySQL から取得）</h2>

    <?php if ($dbError !== ''): ?>
      <div class="err"><?php echo h($dbError); ?></div>
    <?php else: ?>
      <table>
        <thead><tr><th style="width:48px;">ID</th><th>タイトル / 本文</th><th>公開日</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?php echo h($row['id']); ?></td>
            <td>
              <strong><?php echo h($row['title']); ?></strong><br>
              <span style="color:#6b7280;"><?php echo h($row['body']); ?></span>
            </td>
            <td class="date"><?php echo h($row['published_at']); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="3" style="color:#9ca3af;">データがありません。</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <footer>CI/CD Demo — このページはデプロイで切り替わる current リリースを表示しています。</footer>
  </div>
</body>
</html>
