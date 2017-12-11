<?php
namespace app\commands;

use yii\console\Controller;
use QL\QueryList;

defined('APP_ROOT') || define('APP_ROOT', dirname(__FILE__).'/../');

class KmlCityController extends Controller
{
    public function actionFlash()
    {
        $items = (new \yii\db\Query())
            ->from('city')
            ->select('id,state,name')
            ->where('polygon_id is null and polygon_json is null')
            ->all();

        $count = count($items);

        foreach ($items as $idx => $item) {
            $id = $item['id'];
            $state = $item['state'];
            $name = $item['name'];

            $json = $this->getCoordinates($state, $name);
            if (! empty($json)) {
                $json = json_encode($json);
                \yii::$app->db->createCommand()
                    ->update('city', [
                        'polygon_json' => $json
                    ], 'id=:id', [
                        ':id' => $id
                    ])->execute();
            }

            $index = $idx + 1;
            echo "{$index}/{$count}                      \r";
        }
exit;
        $results = $this->getCoordinates('NY', 'Brantingham');
        //$results = $this->getCityNameByPostaCode('13301');
        var_dump($results);

        exit;
        $states = ['MA', 'NY', 'GA', 'CA', 'IL'];

        foreach ($states as $state) {
            $this->matchKmlCity($state, function ($state, $cityId) {
                \WS::$app->db->createCommand()->insert('kml_city', [
                    'state' => $state,
                    'city_id' => $cityId
                ])->execute();
            });
        }
    }

    public function matchKmlCity($state, $callable)
    {
        $items = scandir(__DIR__.'/../../fdn/data/polygons/'.$state);
        foreach ($items as $fileName) {
            $cityId = strtolower(str_replace('.php', '', $fileName));
            if (!in_array($cityId, ['.', '..'])) {
                $callable($state, $cityId);
            }
        }
    }

    public function getCityNameByPostaCode($code, $defReturn = null)
    {
        $content = @ file_get_contents("http://maps.google.com/maps/api/geocode/json?components=country:US|postal_code:{$code}&sensor=false");
        $response = @ json_decode($content);
        if (! $response) return $defReturn;
        if (! is_array($response->results)) return $defReturn;
        if (count($response->results) === 0) return $defReturn;

        $data = $response->results[0];

        return trim(explode(',', $data->formatted_address)[0]);
    }

    public function getCoordinates($state, $cityName, $defReturn = [])
    {
        $cityName = urlencode($cityName);
        $response = @ file_get_contents("http://nominatim.openstreetmap.org/search.php?q={$cityName}%2C{$state}%2CUSA&polygon_geojson=1");
        if (! $response) return $defReturn;

        $startFlag = 'var nominatim_results = ';
        $startPos = strpos($response, $startFlag);
        if ($startPos === false) return $defReturn;

        $response = substr($response, $startPos + strlen($startFlag));
        $endPos = strpos($response, ';');
        if ($endPos === false) return $defReturn;

        $response = substr($response, 0, $endPos);
        $rawData = @json_decode($response);

        if (!is_array($rawData)) return $defReturn;
        if (count($rawData) === 0) return $defReturn;

        $rawData = $rawData[0];
        $asgeoData = json_decode($rawData->asgeojson);

        return $asgeoData->type === 'Polygon' ? $asgeoData->coordinates : $defReturn;
    }
}