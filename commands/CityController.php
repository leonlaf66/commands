<?php
namespace app\commands;

use yii\console\Controller;
use QL\QueryList;

defined('APP_ROOT') || define('APP_ROOT', dirname(__FILE__).'/../');

class CityController extends Controller
{
    public function actionFlash()
    {
        $urls = [
            //'NY' => 'https://www.nowmsg.com/us/New%20York',
            //'IL' => 'https://www.nowmsg.com/us/Illinois',
            //'GA' => 'https://www.nowmsg.com/us/Georgia',
            'CA' => 'https://www.nowmsg.com/us/California',
            'MA' => 'https://www.nowmsg.com/us/Massachusetts'
        ];

        $beginFlag = false;
        $lastPos = $this->getLastPos();

        foreach ($urls as $state => $url) {
            //try {
                $ql = QueryList::get($url.'/all_city.asp');
                foreach ($ql->find('.col-sm-8.text-left>.well:eq(1)>p>a')->getElements() as $city) {
                    if ($city->nodeValue === $lastPos) {
                        $beginFlag = true;
                    }
                    if (!$beginFlag) continue;

                    $cityId = $this->flashCity($state, $city->nodeValue);
                    $ql2 = QueryList::get($url.'/'.$city->getAttribute('href'));
                    foreach ($ql2->find('.col-sm-8.text-left>.well:eq(1)>p>a')->getElements() as $zipcode) {
                        $this->flashZipcode($state, $cityId, $zipcode->nodeValue);
                    }
                    $ql2->destruct();

                    echo $city->nodeValue.', '.$state."                \r";
                    // sleep(2);
                }
                $ql->destruct();
            //} catch (\Exception $e) {
            //    continue;
            //}
        }
    }

    public function flashCity($state, $name)
    {
        $cityId = (new \yii\db\Query())
            ->from('city')
            ->select('id')
            ->where([
                'state' => $state,
                'name' => $name
            ])
            ->scalar();

        if (!$cityId) {
            \WS::$app->db->createCommand()
                ->insert('city', [
                    'name' => $name,
                    'state' => $state,
                ])->execute();
            $cityId = \WS::$app->db->lastInsertID;
        }

        return $cityId;
    }

    public function flashZipcode($state, $cityId, $zipcode)
    {
        $isExists = (new \yii\db\Query())
            ->from('zipcode_city')
            ->where(['zip_code' => $zipcode])
            ->exists();

        if (!$isExists) {
            \WS::$app->db->createCommand()
                ->insert('zipcode_city', [
                    'zip_code' => $zipcode,
                    'city_id' => $cityId,
                    'state' => $state,
                ])->execute();
        }
    }

    public function get_page_content($url)
    {
        ob_start();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSLVERSION,3);
        curl_exec($ch);
        curl_close($ch);

        return ob_get_clean();
    }

    public function getLastPos()
    {
        return (new \yii\db\Query())
            ->from('city')
            ->select('name')
            ->orderBy(['id' => SORT_DESC])
            ->limit(1)
            ->scalar();
    }
}