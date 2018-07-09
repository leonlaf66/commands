<?php
namespace app\commands;

use yii\console\Controller;
use common\helper\DbQuery;

class SitemapPushController extends Controller
{
    const MAX = 2000;
    protected $areasUrls = [];

    public function actionSubmit()
    {   
        $t = 'http://ma.usledju.com/zh/purchase/CW72337764/';
        if (preg_match('/\/([\dA-Z]+)\/$/', $t, $matched)) {
            var_dump($matched[1]);exit;
        }

        exit;
        $this->submitToBaidu([
            'http://ma.usledju.com/zh/purchase/72337764/',
            'http://ma.usleju.com/zh/purchase/72337756/'
        ]);exit;

        $domain = \WS::$app->params['domain'];
        $that = $this;
        $this->houseQuery(function ($rows, $grountIndex) use ($that, $domain) {
            foreach ($rows as $row) {
                $type = $row['prop_type'] === 'RN' ? 'lease' : 'purchase';
                $url = 'http://'.$row['area_id'].$domain.'/zh/'.$type.'/'.$row['list_no'].'/';

                $that->addToQueue($url, $that->onCallback);
            }
        }, 1000);

        // ending
        $this->endQueue($that->onCallback);
    }

    protected function onCallback($failUrls, $orgiUrls)
    {
        $successListNos = array_map(function ($url) {
            preg_match('/\/([\dA-Z]+)\/$/', $t, $matched);
            return $matched[1];
        }, array_diff($orgiUrls, $failUrls));

        if (count($successListNos) > 0) {
            app('db')->table('house_index_v2')
                ->whereIn('list_no', $successListNos)
                ->update(['is_to_baidu' => true]);
        }
    }

    protected function addToQueue($url, $callback)
    {
        $areaId = substr($url, 7, 2);
        if (!isset($this->areasUrls[$areaId])) {
            $this->areasUrls[$areaId] = [];
        }

        $this->areasUrls[$areaId][] = $url;
        if (count($this->areasUrls[$areaId]) > static::MAX) {
          $failUrls = $this->pushToBaidu($areaId, $this->areasUrls[$areaId]);
          $this->areasUrls[$areaId] = [];
          $callback($failUrls, $this->areasUrls[$areaId]);
        }
    }

    protected function endQueue($callback)
    {
        foreach (['ma', 'ny', 'ca', 'ga', 'il'] as $areaId) {
            if (!isset($this->areasUrls[$areaId])) continue;

            $urls = $this->areasUrls[$areaId];

            $failUrls = $this->pushToBaidu($areaId, $urls);

            $callback($failUrls, $urls);
            $this->areasUrls[$areaId] = [];
        }
    }

    protected function pushToBaidu($areaId, $urls)
    {
        $api = "http://data.zz.baidu.com/urls?site={$areaId}.usleju.com&token=fDGbZU9vzaxnryzi";
        $ch = curl_init();
        $options =  array(
            CURLOPT_URL => $api,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => implode("\n", $urls),
            CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
        );
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);

        $failUrls = [];
        if ($httpCode !== 200 || $result === false) {
            $failUrls = $urls;
        } else {
            if ($result->not_same_site) {
                $failUrls = array_merge($failUrls, $result->not_same_site);
            }
            if ($result->not_valid) {
                $failUrls = array_merge($failUrls, $result->not_valid);
            }
        }

        return $failUrls;
    }

    protected function houseQuery($callable, $limit = 4000)
    {
        $query = (new \yii\db\Query())
            ->select('list_no, prop_type, area_id')
            ->from('house_index_v2')
            ->andWhere('is_online_abled=true')
            ->andWhere('prop_type is not null')
            ->andWhere('city_id is not null')
            ->andWhere(['is_to_baidu' => false])
            ->orderBy(['index_at' => 'DESC'])
            ->limit($limit);

        $grountIndex = 0;
        DbQuery::patch($query, $limit, function ($query, $totalCount, $that) use ($callable, & $grountIndex) {
            $rows = $query->all();
            $callable($rows, $grountIndex);
            $grountIndex ++;
        });
    }
}