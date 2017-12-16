<?php
namespace app\commands;

use WS;
use yii\console\Controller;
use yii\db\Query;
use common\helper\DbQuery;

use models\SiteSetting as Configure;

defined('APP_ROOT') || define('APP_ROOT', dirname(__FILE__).'/../');

class ListhubIndexController extends Controller
{
    public function actionExecute()
    {
        $groupSize = 500;

        $indexLatestAt = Configure::get('listhub.rets.index.latest_date');

        $query = (new \yii\db\Query())
            ->select('list_no, state, xml, latitude, longitude,last_update_date')
            ->from('mls_rets_listhub')
            ->where('list_no=09818189')
            //->where('last_update_date > :last_update_date', [':last_update_date' => $indexLatestAt])
            ->limit($groupSize);

        $hasIndexed = false;
        $mlsdb = WS::$app->mlsdb;

        DbQuery::patch($query, $groupSize, function ($query, $totalCount, $that) use (& $indexLatestAt, & $hasIndexed) {
            $mlsdb = WS::$app->mlsdb;
            $transaction = \yii::$app->db->beginTransaction();

            $rows = $query->orderBy('list_no', 'ASC')->all($mlsdb);

            foreach($rows as $row) {
                //未知state直接扔掉
                if (!$row['state']) continue;

                // 未知city时直接扔掉
                $cityName = $xmlDom->one('Address/City')->val();
                if (empty($cityName)) {
                    continue;
                }

                //解析数据实体
                $xmlDom = \models\listhub\Rets::toModel($row['xml']);

                //处理索引行数据
                if ($rowData = $that->_processRow($xmlDom, $row)) {
                    if ($rowData['prop_type'] === false) { // 未知类型，直接扔掉
                        continue;
                    }

                    $rowCount = $that->_writeRow($rowData);

                    ListhubCounter::_($rowCount > 0 ? 'index' : 'error')->increase();
                }
                else {
                    ListhubCounter::_('error')->increase();
                }

                //附加处理
                //if (strtotime($row['last_update_date']) > strtotime($indexLatestAt)) {
                //    $indexLatestAt = $row['last_update_date'];
                //}

                if (! $hasIndexed) $hasIndexed = true;

                unset($row);
                unset($xmlDom);

                //屏幕输出
                $index = ListhubCounter::_('index')->value;
                $error = ListhubCounter::_('error')->value;

                echo "indexed:{$index}/error:{$error}/total:{$totalCount}                   \r";
            }

            $transaction->commit();

        }, $this, $mlsdb);

        //执行完过后再执行状态
        //\yii::$app->db->createCommand()->update('site_setting', ['value' => json_encode($indexLatestAt)], "path='listhub.rets.index.latest_date'")->execute();
    }

    protected function _processRow($xmlDom, $row)
    {
        $rowData = [];
        foreach (ListHubConfig::getFieldMap() as $name => $callable) {
            $rowData[$name] = $callable($xmlDom, $row);
        }
        return $rowData;
    }

    protected function _writeRow($data)
    {
        $db = \yii::$app->db;
        $id = $data['id'];

        if ((new Query())->from('listhub_index')->where(['id'=>$id])->exists()) {
            return $db->createCommand()
                ->update('listhub_index', $data, 'id=:id', [':id'=>$id])
                ->execute();
        }

        return $db->createCommand()
            ->insert('listhub_index', $data)
            ->execute();
    }
}

class ListHubConfig {
    public static function getFieldMap()
    {
        static $idx = 0;

        return [
            'id' => function($d) {
                return $d->one('MlsNumber')->val();
            },
            'index_at' => function ($d) {
                return date('Y-m-d H:i:s');
            },
            'prop_type' => function ($d) {
                $propTypeName = $d->one('PropertyType')->val();
                $propSubTypeName = $d->one('PropertySubType')->val();

                return \common\listhub\estate\References::findPropTypeCode($propTypeName, $propSubTypeName);
            },
            'location' => function ($d) {
                $address = $d->Address;
                return implode(' ', [
                    $address->FullStreetAddress->val().', '.$address->City->val(),
                    $address->StateOrProvince->val(),
                    $address->PostalCode->val()
                ]);
            },
            'list_date' => function ($d) {
                $listDate = $d->one('ListingDate')->val();
                if (!$listDate || strlen($listDate) === 0) {
                    $listDate = date('Y-m-d H:i:s');
                }
                return $listDate;
            },
            'list_price' => function ($d) {
                return $d->one('ListPrice')->val();
            },
            'no_bedrooms' => function ($d) {
                return intval($d->one('Bedrooms')->val());
            },
            'no_bathrooms' => function ($d) {
                return intval($d->one('Bathrooms')->val());
            },
            'no_half_baths' => function ($d) {
                return intval($d->one('HalfBathrooms')->val());
            },
            'no_full_baths' => function ($d) {
                return intval($d->one('FullBathrooms')->val());
            },
            'parking_spaces' => function ($d) {
                return $d->one('DetailedCharacteristics/NumParkingSpaces')->val();
            },
            'square_feet' => function ($d) {
                return $d->one('LivingArea')->val();
            },
            'lot_size' => function ($d) {
                return $d->one('LotSize')->val();
            },
            'latitude' => function ($d, $row) {
                return $row['latitude'];
            },
            'longitude' => function ($d, $row) {
                return $row['longitude'];
            },
            'latitude_rad' => function ($d, $row) {
                return $row['latitude'] ? deg2rad($row['latitude']) : null;
            },
            'longitude_rad' => function ($d, $row) {
                return $row['longitude'] ? deg2rad($row['longitude']) : null;
            },
            'zip_code' => function ($d) {
                return $d->one('Address/PostalCode')->val();
            },
            'city_name' => function ($d) {
                return ucwords($d->one('Address/City')->val());
            },
            'city_id' => function ($d, $row) {
                $cityId = null;

                $cityName = $d->one('Address/City')->val();
                $cityName = ucwords($cityName);
                if (!empty($cityName)) {
                    $cityId = (new \yii\db\Query())
                        ->from('city')
                        ->select('id')
                        ->where(['state' => $row['state'], 'name' => $cityName])
                        ->orderBy(['type_rule' => SORT_ASC, 'id' => SORT_ASC])
                        ->limit(1)
                        ->scalar();
                } else {
                    echo "\n{$cityName} not found\n";
                }

                return $cityId;
            },
            'state' => function ($d, $row) {
                return $row['state'];
            }
        ];
    }
}

class ListhubCounter
{
    public $value = 0;

    public static function _($id)
    {
        static $entities = [];
        if (!isset($entities[$id])) {
            $entities[$id] = new self();
        }
        return $entities[$id];
    }

    public function increase($n = 1)
    {
        $this->value += $n;
    }
}