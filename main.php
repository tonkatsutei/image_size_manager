<?php
/* 
Plugin Name: Image Size Manager
Plugin URI: https://manual.tonkatsutei.com/image_size_manager/
Description: 利用しないサムネイルファイルの生成を停止します。
Author: ton活亭
Version: 1.2.0
Author URI: https://twitter.com/tonkatsutei


### バージョン履歴 ###
[Ver.1.2.0] 2023/01/02
「未使用サイズはImage Size Managerの管理データから削除する」スイッチを付けた
標準プラグインもImage Size Managerで管理するようにした
    それに伴い「設定 > メディア」メニューを非表示に
    Image Size ManagerのDBも併用する案は却下
RESETを実装
プログラムの全体的な見直し

[Ver.1.1.0] 2022/12/27
「今後増えるサイズもすべて自動的にOFFにする」スイッチを付けた
[Ver.1.0.1] 2022/12/26
・自動バージョンアップのテスト
[Ver.1.0.0] 2022/12/26
・こっそりリリース

*/

declare(strict_types=1);

if (!defined('ABSPATH')) exit;
@define('WP_MEMORY_LIMIT', '256M');

//ini_set("display_errors", 'On');
//error_reporting(E_ALL ^ E_DEPRECATED);

// 自動更新
require_once('plugin-update-checker-5.0/plugin-update-checker.php');

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/tonkatsutei/image_size_manager/',
    __FILE__,
    'ISM'
);
$myUpdateChecker->setBranch('master');

// 本体読込
require_once('include/base.php');
