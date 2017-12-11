<?php
namespace app\commands;

use yii\console\Controller;
// http://maps.google.com/maps/api/geocode/json?components=country:US|postal_code:01583&sensor=false
defined('APP_ROOT') || define('APP_ROOT', dirname(__FILE__).'/../');

class KmlController extends Controller
{
    public function actionToPhpArray()
    {
        $file = '/Develops/branches/usleju/fdn/data/kmls/NY/cb_2016_36_place_500k.kml';
        $toDir = '/Develops/branches/usleju/fdn/data/polygons/NY';

        ini_set('memory_limit', '1024M');

        $cityResult = [];
        $temp = '';

        $xml = simplexml_load_file($file);
        $xml->registerXPathNamespace("msg", "http://www.opengis.net/kml/2.2");
        $xml->registerXPathNamespace("gx", "http://www.google.com/kml/ext/2.2");
        $xml->registerXPathNamespace("atom", "http://www.w3.org/2005/Atom");

        $placemarkItems = $xml->xpath('Document/Folder/Placemark');

        foreach($placemarkItems as $placemarkItem) {
            $cityName = (string)$placemarkItem->name;
            if(strpos($cityName, ' ')!==false) {
                $cityName = str_replace(' ', '-', $cityName);
            }
            $cityName = strtolower($cityName);
            foreach (['<at><openparen>', '<closeparen>'] as $str) {
                $cityName = str_replace($str, '', $cityName);
            }
            $cityPolygons = [];
            
            if(! empty($placemarkItem->xpath('MultiGeometry'))) {
                $polygons = $placemarkItem->xpath('MultiGeometry/Polygon');
            }
            else {
                $polygons = $placemarkItem->xpath('Polygon');
            }
            
            foreach($polygons as $polygon) {
                 $cityPolygons[] = $this->polygon2array((string)($polygon->outerBoundaryIs->LinearRing->coordinates));
            }

            $exportCode = var_export($cityPolygons, true);
            file_put_contents($toDir.'/'.$cityName.'.php', "<?php\nreturn {$exportCode};");
        }
    }

    protected function polygon2array($polygons)
    {
        $result = [];

        $tempItem = [];
        $polygonItems = explode(' ', $polygons);
        foreach($polygonItems as $polygonItem) {
            $tempItem = explode(',', $polygonItem);
            $result[] = [
                floatval($tempItem[0]), 
                floatval($tempItem[1])
            ];
        }
        
        return $result;
    }
}