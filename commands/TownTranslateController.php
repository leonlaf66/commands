<?php
namespace app\commands;

use WS;
use yii\console\Controller;

class TownTranslateController extends Controller
{
    public function actionIndex($type = 'oneline')
    {
        $rows = WS::$app->db->createCommand('select id, name from dict_town where name_cn is null')->queryAll();
        $updateCommand = WS::$app->db->createCommand();

        $count = count($rows);
        foreach ($rows as $idx => $row) {
            $id = $row['id'];
            $name = $row['name'];

            $updateCommand->update('dict_town', [
                'name_cn' => $this->googleTranslate($name)
            ], 'id='.$id)->execute();

            $index = $idx + 1;
            echo "{$index}/{$count}                 \r";
        }
        echo "\n";
    }

    public function googleTranslate($text)
    {
        $google = rawurlencode("https://maps.googleapis.com/maps/api/geocode/json?language=zh-CN&address={$text}&key=AIzaSyASw945SCkkPUillpS_YAcb8Aqnt2Fzh_k");
        $google = str_replace('&amp;', '&', $google);
        $translateUrl = "http://www.usleju.com/?delegate={$google}";

        $response = file_get_contents($translateUrl);
        try {
            $response = json_decode($response);
        } catch (Exception $e) {
            return '';
        }

        if (! $response) {
            return null;
        }

        if (property_exists($response, 'results') && 
            property_exists($response->results[0], 'address_components') &&
            property_exists($response->results[0]->address_components[0], 'long_name')) {
            return $response->results[0]->address_components[0]->long_name;
        }

        return null;
    }
}