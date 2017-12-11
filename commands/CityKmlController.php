<?php
namespace app\commands;

use yii\console\Controller;
use QL\QueryList;

defined('APP_ROOT') || define('APP_ROOT', dirname(__FILE__).'/../');

class CityKmlController extends Controller
{
    public function actionFlash()
    {
        $cities = (new \yii\db\Query())
            ->from('city')
            ->all();

        foreach ($cities as $city) {
            if ($cityId = $this->matchKmlId($city['state'], $city['name'])) {
                if (strlen($cityId) > 30) continue; // 太长的先不要
                \yii::$app->db->createCommand()
                    ->update('city', [
                        'polygon_id' => $cityId
                    ], 'id=:id', [':id' => $city['id']])
                    ->execute();
            }
        }
    }

    public function matchKmlId($stateId, $cityName)
    {
        static $cache = [];
        if (!isset($cache[$stateId])) {
            $items = scandir(__DIR__.'/../../fdn/data/polygons/'.$stateId);

            $cache[$stateId] = [];
            foreach ($items as $fileName) {
                $cache[$stateId][] = str_replace('.php', '', $fileName);
            }
        }

        $dbCityId = strtolower(str_replace(' ', '-', $cityName));
        if (in_array($dbCityId, $cache[$stateId])) {
            return $dbCityId;
        }

        foreach ($cache[$stateId] as $cityId) {
            $pos = strpos($cityId, $dbCityId);
            if ($pos !== false) {
                return $cityId;
            }

            $pos = strpos($dbCityId, $cityId);
            if ($pos !== false) {
                return $cityId;
            }
        }

        return null;
    }
}