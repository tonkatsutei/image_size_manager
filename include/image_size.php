<?php

declare(strict_types=1);

namespace tonkatsutei\image_size_manager\image_size;

if (!defined('ABSPATH')) exit;

use tonkatsutei\image_size_manager\base\_options;
use tonkatsutei\image_size_manager\base\_common;

class _image_size
{
    /* 型
    org_w , org_h, width, height   : int
    org_c , crop, flug             : bool
    type                           : string
    */

    // 標準アイキャッチのサイズ
    public static array $regular_sizes = [
        'thumbnail' => [
            'org_w'  => 150,
            'org_h'  => 150,
            'width'  => 150,
            'height' => 150,
            'org_c'  => 1,
            'crop'   => 1,
            'flug'   => 1,
        ],
        'medium' => [
            'org_w'  => 300,
            'org_h'  => 225,
            'width'  => 300,
            'height' => 225,
            'org_c'  => 0,
            'crop'   => 0,
            'flug'   => 1,
        ],
        'large' => [
            'org_w'  => 1024,
            'org_h'  => 768,
            'width'  => 1024,
            'height' => 768,
            'org_c'  => 0,
            'crop'   => 0,
            'flug'   => 1,
        ],
        'medium_large' => [
            'org_w'  => 768,
            'org_h'  => 576,
            'width'  => 768,
            'height' => 576,
            'org_c'  => 0,
            'crop'   => 0,
            'flug'   => 1,
        ]
    ];

    // ISMを反映させる前の状態を退避
    public static function apply_prev_data_backup(): void
    {
        $apply_prev_data = wp_get_additional_image_sizes();
        $apply_prev_data = serialize($apply_prev_data);
        _options::update('apply_prev_data', $apply_prev_data);
    }

    // 設定を反映させる
    public static function apply_setting(): void
    {
        // 追加アイキャッチ
        $added = self::get_added_image_sizes();
        foreach ($added as $size_name => $items) {
            $width  = $items['width'];
            $height = $items['height'];
            $crop   = $items['crop'];
            $flug   = $items['flug'];

            // 利用宣言（アイキャッチの登録 or 変更）
            if ($flug) {
                add_image_size($size_name, $width, $height, $crop);
            }

            // 削除
            if (!$flug) {
                remove_image_size($size_name);
            }
        }
    }

    // 初回起動 & RESET
    public static function first_time(): array
    {
        // 標準アイキャッチは初期値をセット
        $regular = self::$regular_sizes;
        self::update_regular_image_sizes($regular);

        // 現在利用可能な追加アイキャッチをセット
        $old = [];
        $added = self::add_added_image_sizes($old);
        self::update_added_image_sizes($added);

        return [
            'regular' => $regular,
            'added'   => $added,
        ];
    }

    // 更新ボタンを押した
    public static function update_control_panel(): array
    {
        // POSTからアイキャッチのデータを取り出す
        $data = self::post_to_size_array();

        // type振り分け
        $re = self::assign_type($data);
        $regular = $re['regular'];
        $added   = $re['added'];

        return [
            'regular' => $regular,
            'added'   => $added,
        ];
    }

    // POSTからアイキャッチのデータを取り出す
    public static function post_to_size_array(): array
    {
        /* フォーム上のname
        data_{$name}_w
        data_{$name}_h
        data_{$name}_c
        data_{$name}_f
        data_{$name}_i
        */

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
            $data[$i]['width']  = (int)$_POST['data_' . $i . '_w'];
            $data[$i]['height'] = (int)$_POST['data_' . $i . '_h'];
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

        return $data;
    }

    // type振り分け
    public static function assign_type(array $data): array
    {
        foreach ($data as $key => $val) {
            if (array_key_exists($key, self::$regular_sizes)) {
                $data[$key]['type'] = 'regular';
            } else {
                $data[$key]['type'] = 'added';
            }
        }

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

    // 初期化
    public static function initialize(array $img_sizes): array
    {
        $regular = $img_sizes['regular'];
        foreach ($regular as $name => $vals) {
            if (array_key_exists("data_{$name}_i", $_POST)) {
                $regular[$name]['width']  = self::$regular_sizes[$name]['org_w'];
                $regular[$name]['height'] = self::$regular_sizes[$name]['org_h'];
                $regular[$name]['crop']   = self::$regular_sizes[$name]['org_c'];
                $regular[$name]['flug']   = true;
                $regular[$name]['type']   = 'regular';
            }
        }

        $added   = $img_sizes['added'];
        foreach ($added as $name => $vals) {
            if (array_key_exists("data_{$name}_i", $_POST)) {
                $added[$name]['width']  = $added[$name]['org_w'];
                $added[$name]['height'] = $added[$name]['org_h'];
                $added[$name]['crop']   = $added[$name]['org_c'];
                $added[$name]['flug']   = true;
                $added[$name]['type']   = 'added';
            }
        }

        return [
            'regular' => $regular,
            'added'   => $added,
        ];
    }

    // すべてOFF
    public static function all_off(array $img_sizes): array
    {
        $regular = $img_sizes['regular'];
        foreach ($regular as $key => $vals) {
            $regular[$key]['flug'] = false;
        }

        $added = $img_sizes['added'];
        foreach ($added as $key => $vals) {
            $added[$key]['flug'] = false;
        }

        return [
            'regular' => $regular,
            'added'   => $added,
        ];
    }

    // 保存値を取得
    public static function get_saved_value(): array
    {
        // 標準アイキャッチ
        $regular = _image_size::get_regular_image_sizes();

        // 追加アイキャッチ
        $added = _image_size::get_added_image_sizes();

        return [
            'regular' => $regular,
            'added'   => $added,
        ];
    }

    // 標準アイキャッチを取得
    // WPダッシュボード > 設定 > メディア の値
    // Ver.1.2.0以降はメニューが非表示になっている
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
        if (get_option('thumbnail_crop') === '1') {
            $res['thumbnail']['crop'] = true;
        } else {
            $res['thumbnail']['crop'] = false;
        };

        // 幅,高=0の時はOFFとする
        foreach (self::$regular_sizes as $name => $val) {
            if ($res[$name]['width'] === 0 && $res[$name]['height'] === 0) {
                $res[$name]['flug'] = false;
            } else {
                $res[$name]['flug'] = true;
            }
        }

        return $res;
    }

    // 標準アイキャッチを更新
    public static function update_regular_image_sizes(array $regular): void
    {
        // 標準アイキャッチには削除はない
        // width, heightを0にすることで実質無効化する
        // WPダッシュボード > 設定 > メディア > 幅,高=0になる
        foreach ($regular as $name => $val) {
            if (!$val['flug']) {
                $regular[$name]['width']  = 0;
                $regular[$name]['height'] = 0;
            }
        }

        // WPダッシュボード > 設定 > メディア > 幅,高を書き換える
        foreach ($regular as $name => $val) {
            update_option($name . '_size_w', $val['width']);
            update_option($name . '_size_h', $val['height']);
        }

        // thumbnailのみcropがある
        update_option('thumbnail_crop', $regular['thumbnail']['crop']);
    }


    // 追加アイキャッチを取得（当プラグインでの管理データ）
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

    // 未管理サイズを追加
    public static function add_added_image_sizes(array $old): array
    {
        // ISM反映前のサイズ
        $prev = _options::get('apply_prev_data');
        $prev = unserialize($prev);

        // 新規の場合は現在のサイズをオリジナルサイズとして保存
        foreach ($prev as $size_name => $vals) {
            if (array_key_exists($size_name, $old) === false) {
                $old[$size_name]['width']  = $vals['width'];
                $old[$size_name]['height'] = $vals['height'];
                $old[$size_name]['org_w']  = $vals['width'];
                $old[$size_name]['org_h']  = $vals['height'];
                $old[$size_name]['type']   = 'added';
                $old[$size_name]['flug']   = true;

                $old[$size_name]['org_c'] = $vals['crop'];
                if ($old[$size_name]['org_c'] !== true) {
                    $old[$size_name]['org_c'] = false;
                }
                $old[$size_name]['crop'] = $old[$size_name]['org_c'];
            }
        }
        return $old;
    }

    // 現在のテーマによって使用されているかどうか
    // ISM管理画面でのON-OFFではない
    public static function is_use_size(array $img_sizes): array
    {
        // 標準アイキャッチは未使用にはならない
        $regular = $img_sizes['regular'];
        foreach ($regular as $size_name => $vals) {
            $regular[$size_name]['used'] = true;
        }


        // ISM反映前のサイズ
        $prev = _options::get('apply_prev_data');
        $prev = unserialize($prev);

        // ISMで管理中のサイズ
        $added = $img_sizes['added'];

        // 管理中に有って反映前に無いサイズはfalse
        foreach ($added as $size_name => $vals) {
            if (array_key_exists($size_name, $prev)) {
                $added[$size_name]['used'] = true;
            } else {
                $added[$size_name]['used'] = false;
            }
        }

        return [
            'regular' => $regular,
            'added'   => $added,
        ];
    }

    // 未使用サイズを管理データから削除
    public static function delete_unused(array $added): array
    {
        foreach ($added as $name => $vals) {
            if ($vals['used'] === false) {
                unset($added[$name]);
            }
        }
        return $added;
    }
}
