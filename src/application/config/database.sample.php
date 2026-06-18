<?php
// =====================================================================
//  application/config/database.php の雛形（CI2 形式）
//
//  ★この .sample.php はリポジトリに含める（雛形）。
//  ★実際の database.php は .gitignore で除外し、各環境に個別配置する。
//     - ローカル(Docker): 下記のとおり hostname = 'db'
//     - EC2(本番/テスト) : サーバの shared/ 配下に EC2 用の値で配置
//
//  使い方（ローカル）:
//     cp database.sample.php database.php
// =====================================================================

$active_group  = 'default';
$query_builder = TRUE;

$db['default'] = array(
    'hostname' => 'db',          // ローカル Docker は compose のサービス名 'db'
    'username' => 'demo',
    'password' => 'demopass',
    'database' => 'demo_app',
    'port'     => 3306,
    'dbdriver' => 'mysqli',
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
);
