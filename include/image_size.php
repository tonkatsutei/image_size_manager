<?php

declare(strict_types=1);

namespace tonkatsutei\image_size_manager\image_size;

if (!defined('ABSPATH')) exit;

use tonkatsutei\image_size_manager\base\_options;
use tonkatsutei\image_size_manager\base\_common;

class _image_size
{
    // WP標準アイキャッチのサイズ
    public static array $regular_sizes = [
        'thumbnail' => [
            'org_w' => 150,
            'org_h' => 150,
        ],
        'medium' => [
            'org_w' => 300,
            'org_h' => 225,
        ],
        'large' => [
            'org_w' => 1024,
            'org_h' => 768,
        ],
        'medium_large' => [
            'org_w' => 768,
            'org_h' => 576,
        ]
    ];

    // 設定を反映させる
    //  現在当プラグインで管理している追加アイキャッチのみ処理する
    //  標準アイキャッチはここで操作しなくても反映する
    public static function apply_setting(): void
    {
        $added = self::get_added_image_sizes();
        foreach ($added as $size_name => $items) {
            $width  = $items['width'];
            $height = $items['height'];
            $crop   = $items['crop'];
            $flug   = $items['flug'];

            // アイキャッチの登録 or 変更
            if ($flug) {
                add_image_size($size_name, $width, $height, $crop);
            }

            // 削除
            if (!$flug) {
                remove_image_size($size_name);
            }
        }
    }

    // 更新ボタンを押した
    public static function update_control_panel(): void
    {
        // POSTデータ
        // 初期化の際は初期値がセットされてくる
        $re = self::post_to_size_array();
        $regular = $re['regular'];
        $added   = $re['added'];

        // WP標準アイキャッチ
        self::update_regular_image_sizes($regular);

        // テーマ等によって登録されたアイキャッチ
        self::update_added_image_sizes($added);
    }

    // POSTからアイキャッチのデータを取り出す
    public static function post_to_size_array(): array
    {
        // フォーム上のname
        // data_{$name}_w
        // data_{$name}_h
        // data_{$name}_c
        // data_{$name}_f
        // data_{$name}_i

        // サイズ名
        $names = [];
        foreach ($_POST as $key => $val) {
            $end = '###DUMMY###'; // サイズ名に '_'が含まれている場合の対策
            $key .= $end;
            if (strpos($key, 'data_') === false) {
                continue;
            }
            if (strpos($key, '_w' . $end) === false) {
                continue;
            }
            $size = _common::between('data_', '_w' . $end, $key)[0];
            if (!in_array($size, $names)) {
                $names[] = $size;
            }
        }

        // 設定値
        foreach ($names as $i) {
            $data[$i]['width']  = $_POST['data_' . $i . '_w'];
            $data[$i]['height'] = $_POST['data_' . $i . '_h'];
            $s = $_POST['data_' . $i . '_c'];
            if ($s === '1') {
                $data[$i]['crop'] = true;
            } else {
                $data[$i]['crop'] = false;
            }
            $s = $_POST['data_' . $i . '_f'];
            if ($s === '1') {
                $data[$i]['flug'] = true;
            } else {
                $data[$i]['flug'] = false;
            }
        }

        // type振り分け
        foreach ($data as $key => $val) {
            if (array_key_exists($key, self::$regular_sizes)) {
                $data[$key]['type'] = 'regular';
            } else {
                $data[$key]['type'] = 'added';
            }
        }

        // 初期化
        $added = self::get_added_image_sizes();
        foreach ($names as $i) {
            if (array_key_exists("data_{$i}_i", $_POST)) {
                if ($data[$i]['type'] === 'regular') {
                    $data[$i]['width'] = self::$regular_sizes[$i]['org_w'];
                    $data[$i]['height'] = self::$regular_sizes[$i]['org_h'];
                } else {
                    $data[$i]['width'] = $added[$i]['org_w'];
                    $data[$i]['height'] = $added[$i]['org_h'];
                }
                $data[$i]['flug'] = true;
                $data[$i]['crop'] = false;
            } else {
            }
        }

        // すべてOFF
        if (isset($_POST['all_off'])) {
            if ($_POST['all_off'] === "1") {
                foreach ($data as $key => $val) {
                    $data[$key]['flug'] = false;
                }
                _options::update('all_off', '1');
            }
        } else {
            _options::update('all_off', '0');
        }

        // データを分ける
        $regular = [];
        $added = [];
        foreach ($data as $key => $val) {
            if ($val['type'] === 'regular') {
                $regular[$key] = $val;
            } else {
                $added[$key] = $val;
            }
        }

        return [
            'regular' => $regular,
            'added'   => $added,
        ];
    }

    // WP標準のアイキャッチを取得
    // WPダッシュボード > 設定 > メディア の値
    public static function get_regular_image_sizes(): array
    {
        foreach (self::$regular_sizes as $name => $val) {
            $res[$name] = [
                'width'  => (int)get_option($name . '_size_w'),
                'height' => (int)get_option($name . '_size_h'),
                'crop'   => false,
                'type'   => 'regular'
            ];
        }

        // thumbnailのみクロップ要素がある
        if (get_option('thumbnail_crop') == 1) {
            $res['thumbnail']['crop'] = true;
        } else {
            $res['thumbnail']['crop'] = false;
        };

        // 幅,高=0の時は未使用とする
        foreach (self::$regular_sizes as $name => $val) {
            if ($res[$name]['width'] === 0 && $res[$name]['height'] === 0) {
                $res[$name]['flug'] = false;
            } else {
                $res[$name]['flug'] = true;
            }
        }

        return $res;
    }

    // WP標準のアイキャッチを更新
    public static function update_regular_image_sizes(array $data): void
    {
        // 標準アイキャッチには削除はない
        // width, heightを0にすることで実質無効化する
        // WPダッシュボード > 設定 > メディア > 幅,高=0になる
        foreach ($data as $name => $vals) {
            if (!$vals['flug']) {
                $data[$name]['width'] = 0;
                $data[$name]['height'] = 0;
            }
        }

        foreach ($data as $name => $val) {
            update_option($name . '_size_w', $val['width']);
            update_option($name . '_size_h', $val['height']);
        }
        update_option('thumbnail_crop', $data['thumbnail']['crop']);
    }


    // 追加アイキャッチを取得
    // 現在当プラグインで管理している追加アイキャッチのみ
    public static function get_added_image_sizes(): array
    {
        $added = _options::get('added_image_size');
        if (
            $added === false || $added === ''
        ) {
            return [];
        } else {
            return unserialize($added);
        }
    }

    // 追加アイキャッチを更新
    public static function update_added_image_sizes(array $data): void
    {
        // 更新前のデータ
        $added = self::get_added_image_sizes();

        // 新規の場合は現在のサイズをオリジナルサイズとして保存
        foreach ($data as $size_name => $vals) {
            if (array_key_exists($size_name, $added) === false) {
                $added[$size_name]['org_w'] = $vals['width'];
                $added[$size_name]['org_h'] = $vals['height'];
                $added[$size_name]['type'] = 'added';
                // 全てOFFの時
                if (_options::get('all_off') === '1') {
                    $added[$size_name]['flug'] === 0;
                }
            }
        }

        // 既存データに更新データを上書き
        foreach ($data as $size_name => $items) {
            foreach ($items as $item => $val) {
                $added[$size_name][$item] = $val;
            }
        }

        // 保存
        $added = serialize($added);
        _options::update('added_image_size', $added);
    }
}
