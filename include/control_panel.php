<?php

declare(strict_types=1);

namespace tonkatsutei\image_size_manager\control_panel;

if (!defined('ABSPATH')) exit;


use tonkatsutei\image_size_manager\base\_common;
use tonkatsutei\image_size_manager\image_size\_image_size;
use tonkatsutei\image_size_manager\base\_options;

class _control_panel
{
    public static function show_admin_menu(): void
    {
        add_menu_page(
            'ISM', // page_title
            'ISM', // menu_title
            'administrator', // capability
            'ISM', // menu_slug
            'tonkatsutei\image_size_manager\control_panel\_control_panel::control_panel_html', // html
            'dashicons-format-gallery', // icon_url   https://developer.wordpress.org/resource/dashicons
        );
    }

    public static function control_panel_html(): void
    {
        // RESETボタンを押した場合
        if (isset($_POST['ism_reset'])) {
            _options::update('added_image_size', '');
            unset($_POST['all_off']);
            unset($_POST['delete_unused']);
            _options::update('all_off', '0');
            _options::update('delete_unused', '0');
            $v["settei_res"] = <<<EOD
                <div style="padding:1em;">
                    リセットしました。
                </div>
            EOD;
        } else {
            $v["settei_res"] = '';
        }

        // 初回起動 & RESET
        $s = _options::get('added_image_size');
        if ($s === false || $s === '') {
            _image_size::first_time();
        }

        // 更新ボタンを押した場合
        if (isset($_POST['ism_settei'])) {
            // POSTデータから保存値を更新
            // 以降の処理でsize_nameを使うので戻り値を取る
            $img_sizes = _image_size::update_control_panel();
            $v["settei_res"] = <<<EOD
                <div style="padding:1em;">
                    更新しました。<br>
                    反映しない場合は他のプラグインやテーマを確認してください。
                </div>
            EOD;
        } else {
            $img_sizes = _image_size::get_saved_value();
        }

        // すべてOFFと未使用削除のチェックマーク
        if (isset($_POST['ism_settei']) === false) {
            if (_options::get('all_off') === '1') {
                $_POST['all_off'] = '1';
            }
            if (_options::get('delete_unused') === '1') {
                $_POST['delete_unused'] = '1';
            }
        }

        // 個別に初期化
        $img_sizes = _image_size::initialize($img_sizes);

        // 未管理のサイズを追加
        $img_sizes['added'] = _image_size::add_added_image_sizes($img_sizes['added']);

        // 使用中か未使用かを追加
        $img_sizes = _image_size::is_use_size($img_sizes);

        // 全てOFFの場合
        if (isset($_POST['all_off'])) {
            $img_sizes = _image_size::all_off($img_sizes);
            _options::update('all_off', '1');
        } else {
            _options::update('all_off', '0');
        }

        // 未使用を削除の場合
        if (isset($_POST['delete_unused'])) {
            $img_sizes['added'] = _image_size::delete_unused($img_sizes['added']);
            _options::update('delete_unused', '1');
        } else {
            _options::update('delete_unused', '0');
        }

        // 標準アイキャッチのDBを更新
        _image_size::update_regular_image_sizes($img_sizes['regular']);

        // 追加アイキャッチのDBを更新
        _image_size::update_added_image_sizes($img_sizes['added']);

        // HTML生成
        $html = self::generate_html($img_sizes, $v);

        // 表示
        print $html;
    }

    // HTML生成
    public static function generate_html(array $img_sizes, array $v): string
    {
        // すべてOFFのチェックマーク
        $all_off = _options::get('all_off');
        if ($all_off === '1') {
            $v['all_off_check'] = 'checked="checked"';
        } else {
            $v['all_off_check'] = '';
        }
        print "all_off_check => " . $v['all_off_check'] . "<br>";

        // 未使用を削除のチェックマーク
        $delete_unused = _options::get('delete_unused');
        if ($delete_unused === '1') {
            $v['delete_unused_check'] = 'checked="checked"';
        } else {
            $v['delete_unused_check'] = '';
        }

        // サイズ一覧テーブル
        $v['table'] = self::generate_table_html($img_sizes);

        // バージョン表記
        $v['version'] = 'Ver.' . _common::plugin()['version'];

        // HTML
        $html  = self::html($v);
        $html .= self::main_style($v);
        $html .= self::dark_mode_style($v);

        return $html;
    }

    // TABLE生成
    public static function generate_table_html(array $img_sizes): string
    {
        // regularとaddedを統合
        foreach ($img_sizes as $i => $vals) {
            foreach ($vals as $key => $val) {
                $data[$key] = $val;
            }
        }

        // 表示用TABLE
        $table_html = "<table name='usable_image_sizes'>";
        $table_html .= <<<EOF
                        <tr>
                            <th></th>
                            <th>サイズ</th>
                            <th>切替</th>
                            <th>width</th>
                            <th>height</th>
                            <th>crop</th>
                            <th>初期化</th>
                        </tr>
                    EOF;
        foreach ($data as $key => $val) {
            $type   = $val['type'];
            $width  = (int)$val['width'];
            $height = (int)$val['height'];
            $flug   = $val['flug'];
            $crop   = $val['crop'];
            $used   = $val['used'];
            $table_html .= self::generate_tr_html($type, $key, $flug, $width, $height, $crop, $used);
        }
        $table_html .= '</table>';

        return $table_html;
    }

    // TR生成
    private static function generate_tr_html(string $type, string $name, bool $flug, int $width, int $height, bool $crop, bool $used): string
    {
        if ($type === 'regular') {
            //$type_src = '<span class="dashicons dashicons-wordpress-alt"></span>';
            $type_src = '<span class="dashicons dashicons-wordpress"></span>';
        } else {
            $type_src = '<span class="dashicons dashicons-layout"></span>';
        }

        if ($flug) {
            $flug_src = <<<EOD
                        　<label><input type='radio' name='data_{$name}_f' value='0'>OFF</label>
                        　<label><input type='radio' name='data_{$name}_f' value='1' checked = 'cheched' >ON</label>　
                    EOD;
        } else {
            $flug_src = <<<EOD
                        　<label><input type='radio' name='data_{$name}_f' value='0' checked = 'cheched'>OFF</label>
                        　<label><input type='radio' name='data_{$name}_f' value='1'>ON</label>　
                    EOD;
        }

        if ($crop) {
            $crop = 1;
        } else {
            $crop = 0;
        }

        if ($used) {
            $unuse_src = '';
        } else {
            $unuse_src = "class='unuse'";
        }

        if ($type === 'regular' && $name !== 'thumbnail') {
            $crop_src = "<input type='text' value='0' style='text-align:center;color:#000;' disabled><input type='hidden' name='data_{$name}_c' value='0'>";
        } else {
            $crop_src = "<input type='text' name='data_{$name}_c' value='{$crop}' style='text-align:center;'>";
        }

        $initialization_src = "<input type='checkbox' name='data_{$name}_i' value='1'>";

        return <<<EOD
            <tr {$unuse_src}>
                <td>{$type_src}</td>
                <td>{$name}</td>
                <td>{$flug_src}</td>
                <td><input type='text' name='data_{$name}_w' value='{$width}'  style='text-align:right;'></td>
                <td><input type='text' name='data_{$name}_h' value='{$height}' style='text-align:right;'></td>
                <td>{$crop_src}</td>
                <td style='text-align:center;'>{$initialization_src}</td>
            </tr>
        EOD;
    }

    private static function html(array $v): string
    {
        $prefix = "_" . _common::plugin()['name'];
        return <<<EOD
            <form method="post" action="" name="ism_form" enctype="multipart/form-data">
            <div class="{$prefix}_wrap">
                <div class="settei_res">{$v["settei_res"]}</div>

                <h2>
                    <span class="dashicons dashicons-format-gallery icon"></span><br><br>
                    Image Size Manager
                </h2>
                <div class='version'>{$v['version']}</div>

                <h3>現在設定されている画像サイズ</h3>
                <div>
                    利用しているテーマやプラグインによって、ここに表示されるサイズは違います。<br>
                    OFFにすると以降の投稿時にサムネイルファイルが生成されなくなります。<br>
                    <span class="dashicons dashicons-wordpress" style="font-size:1.2em"></span>マークのサイズをOFFにした場合 width, heightの値は0になります。<br>
                    <span class='comment'>設定を変更しても既に生成済みの画像には影響しません。</span>
                </div>
                {$v['table']}
                <div style='margin:10px 2em;'>
                    <input type='checkbox' name='all_off' value='1' {$v['all_off_check']}>
                    今後追加されるサイズも含めてすべてOFFにする。
                </div>
                <div style='margin:10px 2em;'>
                    <input type='checkbox' name='delete_unused' value='1' {$v['delete_unused_check']}>
                    未使用サイズは Image Size Manager の管理データから削除する
                </div>
                <button type="submit" name="ism_settei" value="on">更 新</button>
                <button type="submit" name="ism_reset" class="link-style-btn" value="1">
                    <span class="dashicons dashicons-image-rotate"></span>
                    RESET
                </button>
            </div>
            </form>
        EOD;
    }

    private static function main_style(array $v): string
    {
        $prefix = "_" . _common::plugin()['name'];
        return <<<EOD
            <style>
            .{$prefix}_wrap {
                width: 100%;
                max-width: 700px;
                margin-top: 1em;
                padding: 1em;
                border-radius: 10px;
                letter-spacing: 0.1em;
            }            
            .{$prefix}_wrap .center{
                text-align:center;
            }
            .{$prefix}_wrap .settei_res {
                font-weight: bold;
                border-radius: 5px;
            }
            .{$prefix}_wrap h2 {
                font-size: 4em;
                /*font-weight: 100;*/
                padding-bottom: inherit;
                margin-bottom: 0;
            }
            .{$prefix}_wrap .version{
                font-weight: normal;
                margin-left: 1em;
                color: #999;
            }
            .{$prefix}_wrap h3 {
                margin-bottom: 0.1em;
                letter-spacing: 0.03em;
            }
            .{$prefix}_wrap .inline {
                display: inline-block;
            }
            .{$prefix}_wrap button {
                padding:1em 3em;
            }
            .{$prefix}_wrap .w80 {
                width: 80px;
            }
            .{$prefix}_wrap .w170 {
                width: 170px;
            }
            .{$prefix}_wrap hr {
                border: 0;
            }
            .{$prefix}_wrap table {
                margin: 1em;
            }
            .{$prefix}_wrap table input[type='text']{
                width: 50px;
            }
            .{$prefix}_wrap button{
                margin: 1em 0;
            }
            .{$prefix}_wrap .icon {
                color: #7cbaf1;
                font-size: 2em;
                margin: 0 0 10px;
                display: inline;
            }
            .{$prefix}_wrap tr.unuse {
                color:#54636f;
            }

            .{$prefix}_wrap button.link-style-btn{
                cursor: pointer;
                border: none;
                background: none;
                color: #c3c4c7;
            }
            .{$prefix}_wrap button.link-style-btn:hover{
                color: #9ab3fd;
            }

            </style>
        EOD;
    }

    private static function dark_mode_style(array $v): string
    {
        $prefix = "_" . _common::plugin()['name'];
        return <<<EOD
            <style>
            #wpcontent {
                background-color: #2a4359;
            }
            .{$prefix}_wrap {
                background-color: #2c3338;
                color: #fff;
            }            
            .{$prefix}_wrap textarea, .{$prefix}_wrap input{
                background-color: #49545c;
                color: #fff;
            }
            .{$prefix}_wrap .settei_res {
                background-color: #717171;
                color: #fff;
            }
            .{$prefix}_wrap h2 {
                color: #7cbaf1;
            }
            .{$prefix}_wrap h3 {
                color: #7cbaf1;
                /*background-color: #7cbaf1;*/
            }
            .comment {
                color: #999;
            }
            .color_f5374e {
                color: #f5374e;
            }
            .color_fff {
                color: #fff;
            }
            .{$prefix}_wrap hr {
                border-top: 1px dashed #7cbaf1;
            }
            .{$prefix}_wrap table {
                color: #fff;
            }
            .{$prefix}_wrap th{
                border-top: solid 1px #999;
                border-bottom: solid 1px #999;
                padding: 3px;
            }
            .{$prefix}_wrap td{
                border-bottom: solid 1px #999;
                padding: 3px;
            }

            </style>
        EOD;
    }
}
