<?php
// =====================================================================
//  deploy.php — Deployer 8 設定（デプロイ先: AWS EC2）
//
//  デプロイ:  vendor/bin/dep deploy test        （手動）
//  ロールバック: vendor/bin/dep rollback test
//  ※ 通常は GitHub Actions が自動実行する（.github/workflows/deploy.yml）
// =====================================================================
namespace Deployer;

require 'recipe/codeigniter.php';

// ----- 基本設定 -----
set('application', 'ci-cd-demo');

// ★ 自分の GitHub（personal）リポジトリの SSH URL に置き換える
set('repository', 'git@github.com:kamoya999/ci-cd-demo.git');

set('keep_releases', 5);     // ロールバック用に5世代保持
set('git_tty', false);       // CI 実行時は対話を無効化

// ----- 共有・書き込み（機密設定と cache/logs を全リリースで共有） -----
//  本デモはアプリを src/ 配下に置いているのでパスを src/ から指定する。
set('shared_files', array('src/application/config/database.php'));
set('shared_dirs',  array('src/application/cache', 'src/application/logs'));
set('writable_dirs', array('src/application/cache', 'src/application/logs'));

// ----- GitHub Actions からの初回 SSH 接続でホスト鍵確認に止まらないようにする -----
//  （デモ用途。厳密運用では ssh-keyscan で known_hosts を事前登録する）
add('ssh_arguments', array('-oStrictHostKeyChecking=accept-new'));

// ----- ホスト定義（AWS EC2） -----
//  本デモでは「テスト環境 = EC2 の1台」を test として扱う。
//  ★ hostname を EC2 のパブリック IP もしくはパブリック DNS に置き換える
//  ★ Amazon Linux は ec2-user、Ubuntu は ubuntu に合わせる
host('test')
    ->setHostname('ec2-43-207-129-59.ap-northeast-1.compute.amazonaws.com')   // 例: ec2-xx-xx-xx-xx.ap-northeast-1.compute.amazonaws.com
    ->setRemoteUser('ec2-user')
    ->set('branch', 'main')
    ->set('deploy_path', '/var/www/ci-cd-demo');

// 本番を分ける場合の例（PHASE2 以降）:
// host('production')
//     ->setHostname('www.example.com')
//     ->setRemoteUser('ec2-user')
//     ->set('branch', 'main')
//     ->set('deploy_path', '/var/www/ci-cd-demo');

// ----- 失敗時は自動でロック解除 -----
after('deploy:failed', 'deploy:unlock');

// ----- デモアプリは Composer 非依存：サーバ側 composer install を無効化 -----
task('deploy:vendors')->disable();
