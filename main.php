<?php
/* 
Plugin Name: Image Size Manager
Plugin URI: https://manual.tonkatsutei.com/image_size_manager/
Description: 利用しないサムネイルファイルの生成を停止します。
Author: ton活亭
Version: 1.0.0
Author URI: https://twitter.com/tonkatsutei

バージョン履歴
[Ver.1.0.0] 2022/12/26
・リリース

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

// 本体
require_once('include/base.php');
