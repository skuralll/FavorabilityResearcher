<?php

require('vendor/autoload.php');

use Abraham\TwitterOAuth\TwitterOAuth;
use Symfony\Component\Yaml\Yaml as Yaml;

const TOKEN_FILE = "TOKEN.yml";
const FAV_API_MAX = 200;

/*ファイルからトークンを取得、インスタンスを作成*/
$tokens = Yaml::parseFile(TOKEN_FILE);
$connection = new TwitterOAuth($tokens['api_key'], $tokens['api_key_secret'], $tokens['access_token'], $tokens['access_token_secret']);

$my_id = ((array) $connection->get('account/verify_credentials', []))['screen_name'];

/*スクリーンネームの入力待機*/
echo('>>対象アカウントのIDを入力してください(@______,FF内限定): ');
$user_id = trim(fgets(STDIN));

/*本アカウントと対象ユーザーがFFかどうかを確認*/
$connect_flag = 0;
$relations = $connection->get('friendships/lookup', ['screen_name' => $user_id]);
if(isset($relations[0]) && $relations[0] instanceof stdClass){
    $rel_det = (array) $relations[0];
    foreach ($rel_det['connections'] as $connect){
        if($connect === 'following' || $connect === 'followed_by') $connect_flag++;
        if($connect_flag == 2) break;
    }
}
if(!($connect_flag === 2 || $user_id === $my_id )) exit('>>指定されたユーザーはFF外です.');
else echo(">>FF内であることを確認しました.\n");

/*いいね一覧を取得する*/
echo('>>サンプルにするいいねの数を入力してください(最大数2000): ');
$fav_max = (int) trim(fgets(STDIN));
if($fav_max < 1|| $fav_max > 2000) exit("1~2000の間で指定してください");
$fav_count = 0;
$fav_get = FAV_API_MAX;
$counts = [];
$names = [];
$last_id = -1;
while($fav_count < $fav_max){
    $fav_get = $fav_max - $fav_count;
    if($fav_get > 200) $fav_get = 200;
    $query = ['screen_name' => $user_id, 'count' => $fav_get, 'include_entities' => false];
    if($last_id !== -1) $query['max_id'] = $last_id;
    $fav_list = $connection->get('favorites/list', $query);
    if($fav_list instanceof stdClass){
        echo(">>API制限がかかっています.\n");
        break;
    }
    //多次元配列にするとソートが面倒なため分割
    if(!isset($last_at)) $last_at = ((array) $fav_list[0])['created_at'];
    foreach ($fav_list as $fav_cont){
        $fav_det = (array) $fav_cont;
        $user_det = (array) $fav_det['user'];
        $last_id = $fav_det["id"];
        if(!isset($counts[$user_det['screen_name']])){
            $counts[$user_det['screen_name']] = 0;
            $names[$user_det['screen_name']] = $user_det['name'];
        }
        $counts[$user_det['screen_name']]++;
        $first_at = $fav_det['created_at'];
        $fav_count++;
    }
}
arsort($counts);

/*結果を表示*/
echo("＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝\n");
foreach ($counts as $tg_id => $tg_count){
    echo($names[$tg_id] . '(@' . $tg_id . ') : ' . $tg_count . '[' . (ceil(100 * 100 * $tg_count / $fav_count) / 100) . '%]' . "\n");
}
echo("First at " . $first_at . " \n");
echo("Last  at " . $last_at . " \n");
echo("取得いいね数 " . $fav_count . " \n");
echo("＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝\n");