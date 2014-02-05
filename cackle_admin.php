<?php
include ('cackle_sync.php');

$cackle_api = new CackleAPI();
cackle_request_handler();


function key_validate($site_id,$site_api,$account_api){
    global $cackle_api;
    $key_url ="http://cackle.me/api/keys/check?siteId=$site_id&accountApiKey=$account_api&siteApiKey=$site_api";
    $key_response = $cackle_api->curl($key_url);
    if ($key_response == "success") {
        return true;
    } else {
        return false;
    }
}

function key_save($site_id,$site_api,$account_api){
    global $cackle_api;
    $cackle_api->cackle_set_param("site_id",$site_id);
    $cackle_api->cackle_set_param("site_api",$site_api);
    $cackle_api->cackle_set_param("account_api",$account_api);
    $cackle_api->cackle_set_param("cackle_activated",true);
    echo($cackle_api->cackle_get_param('account_api'));
}
function cackle_activate(){
    global $cackle_api;
    return $cackle_api->cackle_get_param('cackle_activated');
}

function export_comment($post,$eof=false){
    require_once(dirname(__FILE__) . '/cackle_sync.php');
    $url = HOME_URL . "index.php?newsid=" . $post;
    $wxr = cackle_export_wp($post, $url);
    global $cackle_api;
    $timestamp = time();
    $response = $cackle_api->import_wordpress_comments($wxr, $timestamp,$eof);
    return $response;
}

function cf_json_encode($data) {
    return cfjson_encode($data);
}

function cfjson_encode_string($str) {
    if (is_bool($str)) {
        return $str ? 'true' : 'false';
    }
    return str_replace(
        array(
            '"'
        , '/'
        , "\n"
        , "\r"
        )
        , array(
            '\"'
        , '\/'
        , '\n'
        , '\r'
        )
        , $str
    );
}

function cfjson_encode($arr) {
    $json_str = '';
    if (is_array($arr)) {
        $pure_array = true;
        $array_length = count($arr);
        for ($i = 0; $i < $array_length; $i++) {
            if (!isset($arr[$i])) {
                $pure_array = false;
                break;
            }
        }
        if ($pure_array) {
            $json_str = '[';
            $temp = array();
            for ($i = 0; $i < $array_length; $i++) {
                $temp[] = sprintf("%s", cfjson_encode($arr[$i]));
            }
            $json_str .= implode(',', $temp);
            $json_str .= "]";
        } else {
            $json_str = '{';
            $temp = array();
            foreach ($arr as $key => $value) {
                $temp[] = sprintf("\"%s\":%s", $key, cfjson_encode($value));
            }
            $json_str .= implode(',', $temp);
            $json_str .= '}';
        }
    } else if (is_object($arr)) {
        $json_str = '{';
        $temp = array();
        foreach ($arr as $k => $v) {
            $temp[] = '"' . $k . '":' . cfjson_encode($v);
        }
        $json_str .= implode(',', $temp);
        $json_str .= '}';
    } else if (is_string($arr)) {
        $json_str = '"' . cfjson_encode_string($arr) . '"';
    } else if (is_numeric($arr)) {
        $json_str = $arr;
    } else if (is_bool($arr)) {
        $json_str = $arr ? 'true' : 'false';
    } else {
        $json_str = '"' . cfjson_encode_string($arr) . '"';
    }
    return $json_str;
}

function cackle_request_handler() {
    global $cackle_response;
    global $cackle_api;
    global $post;
    global $wpdb;
    if (!empty($_GET['cf_action'])) {
        switch ($_GET['cf_action']) {
            case 'export_comments':

                $timestamp = intval($_GET['timestamp']);
                $post_id = intval($_GET['post_id']);
                global $cackle_api;
                $each_post = $cackle_api->db_connect("select post_id from ".PREFIX."_comments group by post_id");
                $post = $cackle_api->db_connect("select post_id from ".PREFIX."_comments where post_id > $post_id group by post_id limit 1");
                $post_id = $post[0][post_id];

                $post_length =count($each_post);
                $last_post_id = $each_post[($post_length - 1)][post_id];
                    if ($last_post_id == $post_id){
                        $eof = true;

                    }



                    if ($eof) {
                        $status = 'complete';
                        $msg = 'Your comments have been sent to Cackle and queued for import!<br/>';
                    } else {
                        $status = 'partial';
                        //require_once(dirname(__FILE__) . '/manage.php');
                        $msg = "Processed comments on post $post_id";
                    }
                    $result = 'fail';
                    ob_start();
                    $response = null;
                    if ($post) {
                        if ($eof){
                            $response = export_comment($post_id,true);
                        }
                        else {
                            $response = export_comment($post_id);
                        }



                        if (!($response == "success")) {
                            $result = 'fail';
                            $msg = '<p class="status cackle-export-fail">' . ('Sorry, something  happened with the export. Please <a href="#" id="cackle_export_retry">try again</a></p><p>If your API key has changed, you may need to reinstall Cackle (deactivate the plugin and then reactivate it). If you are still having issues, refer to the <a href="%s" onclick="window.open(this.href); return false">WordPress help page</a>.' . 'http://cackle.me/help/') . '</p>';
                            $response = $cackle_api->get_last_error();
                        } else {
                            if ($eof) {
                                $msg =  'Your comments have been sent to Cackle and queued for import!<br/>After exporting the comments you receive email notification' . 'http://cackle.me/help/';
                            }
                            $result = 'success';
                        }
                    }
                    //AJAX response
                    $debug = ob_get_clean();
                    $response = compact('result', 'timestamp', 'status', 'post_id', 'msg', 'eof', 'response', 'debug');
                    header('Content-type: text/javascript');
                    echo cf_json_encode($response);
                    die();

                break;
            case 'import_comments':
                if ($cackle_api->cackle_get_param("last_comment")){
                    $cackle_api->db_connect("delete from ".PREFIX."_comments where user_agent like 'Cackle:%%'");
                }
                $cackle_api->cackle_set_param("last_comment",0);
                $cackle_api->cackle_set_param("last_modified",0);
                ob_start();
                $sync = new Sync();

                $response = $sync->comment_sync_all();
                $debug = ob_get_clean();
                if (!$response) {
                    $status = 'error';
                    $result = 'fail';
                    $error = $cackle_api->get_last_error();
                    $msg = '<p class="status cackle-export-fail">' . cackle_i('There was an error downloading your comments from Cackle.') . '<br/>' . htmlspecialchars($error) . '</p>';
                } else {
                    if ($response) {
                        $status = 'complete';
                        $msg = 'Your comments have been downloaded from Cackle and saved in your local database.';
                    }
                    $result = 'success';
                }
                $debug = explode("\n", $debug);
                $response = compact('result', 'status', 'comments', 'msg', 'last_comment_id', 'debug');
                header('Content-type: text/javascript');
                echo cf_json_encode($response);
                die();

                break;
        }
    }
}


?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
</head>
<!-- Settings box -->
<div class="accordion">

    <!-- General Settings -->
    <div class="box" style="margin:20px;">
        <a target="_blank" href="http://cackle.ru" style="float: left; margin-bottom: 12px; margin-top:10px;">
            <img src="http://cackle.ru/static/img/logo.png" alt="cackle logo">
        </a>
        <p style="float: left; font-size: 13px; font-weight: bold; line-height: 30px; padding-left: 13px;">comments
            platform that helps your website's audience communicate through social networks.</p>

        <div class="feature">

            <form method="post">
                <h3 style="clear: both;
    padding-left: 0;font-size: 1.17em;
    margin: 1em 0;">Настройки</h3>
                <p>Пожалуйста, <a target="_blank" href="http://cackle.ru/site/17216/wordpress">кликните здесь</a> для получения ваших Site ID, Account API Key, Site API Key. </p>
                <ul class="list">
                    <li>
                        <label>Cackle SiteId: </label>
                        <input id="siteId" name="siteId" class="input-number" value="<?php echo $cackle_api->cackle_get_param('site_id'); ?>"/>
                    </li>
                    <li>
                        <label>Cackle Account API Key: </label>
                        <input id="accountApiKey" style="width: 550px;" name="accountApiKey" class="input-number" value="<?php echo $cackle_api->cackle_get_param('account_api'); ?>"/>
                    </li>
                    <li>
                        <label>Cackle Site API Key</label>
                        <input id="siteApiKey" style="width: 550px;" name="siteApiKey" class="input-number" value="<?php echo $cackle_api->cackle_get_param('site_api'); ?>"/>
                    </li>



                        <?php
                        if (!empty($_POST)) {

                            if (key_validate($_POST['siteId'], $_POST['siteApiKey'], $_POST['accountApiKey'])) {
                                echo '<span style="color:green;font-weight:bold;">Виджет успешно активирован</span>';
                                key_save($_POST['siteId'], $_POST['siteApiKey'], $_POST['accountApiKey']);
                                $cackle_api->cackle_set_param("cackle_sso",(isset($_POST['enable_sso'])) ? 1 : 0);

                            }
                            else{
                                echo '<span style="color:red"><p>При активации виджета произошла ошибка. Проверьте правильность ввода ключей.</p></span>';
                            }
                        }
                        else{
                            if(cackle_activate()){
                                echo '<span style="color:green;font-weight:bold;">Виджет был уже успешно активирован</span>';
                            }
                            else{
                                echo '<span style="color:red">Invalid keys</span> <br><p style="color: #C0504D;">Перед активацией убедитесь, что вы настроили локальную БД в cackle_api.php.</p>' ;
                            }
                        }

                        ?>
                    <li>
                        <?php echo "Single Sign On:"?> <input type="checkbox" value="1" name="enable_sso" <?php if ($cackle_api->cackle_get_param('cackle_sso')==1): ?>
                                                              checked="checked" <?php endif;?>/>
                        Данная опция позволяет залогиненым на вашем сайте пользователям оставлять комментарии в виджете комментариев Cackle под своим именем и аватаркой, без необходимости авторизации через виджет. <b>Внимание</b>, данная опция доступна
                        только на аккаунте <a href="http://cackle.me/plans" title="See details about Pro Account">Corporate</a>.

                    </li>
                    <li>
                        <label>Activate widget</label>
                        <input class="button" type="submit" value="Activate"/>

                    </li>

                </ul>
            </form>
        </div>


<h3>Import / Export</h3>

<table class="form-table">

    <tr id="export">
        <th scope="row" valign="top"><?php echo ('Экспорт '); ?></th>
        <td>
            <div id="cackle_export">
                <p class="status">
                    <a href="#"
                       class="button"><?php echo ('Export Comments'); ?></a>  <?php echo ('Начать экспорт локальных комментариев в Cackle'); ?>
                </p>
            </div>
        </td>
    </tr>

    <tr>
        <th scope="row" valign="top"><?php echo ('Ресинхронизация'); ?></th>
        <td>
            <div id="cackle_import">
                <div class="status">
                    <p><a href="#" class="button"><?php echo ('ReSyncComments'); ?></a> <?php echo ('Начать ресинхронизацию комментариев Cackle с локальной БД'); ?></p>
                    <br/>


                </div>
            </div>
        </td>
    </tr>
</table>

    </div>


</div>


<style type="text/css">
    .cackle-importing, .cackle-imported, .cackle-import-fail, .cackle-exporting, .cackle-exported, .cackle-export-fail {

        line-height: 16px;
        padding-left: 20px;
    }

    p.status {
        padding-top: 0;
        padding-bottom: 0;
        margin: 0;
    }

    body{
    line-height: 1.4em;
    font-family: sans-serif;
    color:#333333;
        font-size: 12px;
    }
    ul.list{
        list-style: none;
    }
    ul.list li{
        margin-bottom: 20px;

    }
    ul.list li input{
        padding:4px;

    }
    ul .button{
        background-color: #31759B;
        background-image: linear-gradient(to bottom, #2A96C5, #21769B);
        border-color: #21751B #31759B #2E6A8D;
        box-shadow: 0 1px 0 rgba(120, 200, 230, 0.5) inset;
        color: #FFFFFF;
        text-decoration: none;
        text-shadow: 0 1px 0 rgba(0, 0, 0, 0.1);
        border-radius: 3px 3px 3px 3px;
        border-style: solid;
        border-width: 1px;
        cursor: pointer;
        display: inline-block;
        font-size: 12px;
        height: 30px;
        line-height: 23px;
    }



</style>
<script type="text/javascript">
    jQuery(function ($) {
        $('#cackle-tabs li').click(function () {
            $('#cackle-tabs li.selected').removeClass('selected');
            $(this).addClass('selected');
            $('.cackle-main, .cackle-advanced').hide();
            $('.' + $(this).attr('rel')).show();
        });
        if (location.href.indexOf('#adv') != -1) {
            $('#cackle-tab-advanced').click();
        }
    <?php if (isset($_POST['site_api_key'])) { ?>
        $('#cackle-tab-advanced').click()
        <?php }?>
        cackle_fire_export();
        cackle_fire_import();
    });
    cackle_fire_export = function () {
        var $ = jQuery;
        $('#cackle_export a.button, #cackle_export_retry').unbind().click(function () {
            $('#cackle_export').html('<p class="status"></p>');
            $('#cackle_export .status').removeClass('cackle-export-fail').addClass('cackle-exporting').html('Processing...');
            cackle_export_comments();
            return false;
        });
    }
    cackle_export_comments = function () {
        var $ = jQuery;
        var status = $('#cackle_export .status');
        var export_info = (status.attr('rel') || '0|' + (new Date().getTime() / 1000)).split('|');
        $.get(
                window.location.href,
                {
                    cf_action:'export_comments',
                    post_id:export_info[0],
                    timestamp:export_info[1]
                },
                function (response) {
                    switch (response.result) {
                        case 'success':
                            status.html(response.msg).attr('rel', response.post_id + '|' + response.timestamp);
                            switch (response.status) {
                                case 'partial':
                                    cackle_export_comments();
                                    break;
                                case 'complete':
                                    status.removeClass('cackle-exporting').addClass('cackle-exported');
                                    break;
                            }
                            break;
                        case 'fail':
                            status.parent().html(response.msg);
                            cackle_fire_export();
                            break;
                    }
                },
                'json'
        );
    }
    cackle_fire_import = function () {
        var $ = jQuery;
        $('#cackle_import a.button, #cackle_import_retry').unbind().click(function () {
            var wipe = $('#cackle_import_wipe').is(':checked');
            $('#cackle_import').html('<p class="status"></p>');
            $('#cackle_import .status').removeClass('cackle-import-fail').addClass('cackle-importing').html('Processing...');
            cackle_import_comments(wipe);
            return false;
        });
    }
    cackle_import_comments = function (wipe) {
        var $ = jQuery;
        var status = $('#cackle_import .status');
        var last_comment_id = status.attr('rel') || '0';
        $.get(
                window.location.href,
                {
                    cf_action:'import_comments',
                    last_comment_id:last_comment_id,
                    wipe:(wipe ? 1 : 0)
                },
                function (response) {
                    switch (response.result) {
                        case 'success':
                            status.html(response.msg).attr('rel', response.last_comment_id);
                            switch (response.status) {
                                case 'partial':
                                    cackle_import_comments(false);
                                    break;
                                case 'complete':
                                    status.removeClass('cackle-importing').addClass('cackle-imported');
                                    break;
                            }
                            break;
                        case 'fail':
                            status.parent().html(response.msg);
                            cackle_fire_import();
                            break;
                    }
                },
                'json'
        );
    }
</script>

</html>
