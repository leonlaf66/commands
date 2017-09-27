<?php
namespace app\commands;

use WS;
use yii\console\Controller;
use yii\db\Query;
use common\helper\DbQuery;

use common\core\Configure;

defined('APP_ROOT') || define('APP_ROOT', dirname(__FILE__).'/../');

class RetsIndexController extends Controller
{
    public function actionUpdateAll($fields)
    {
        $fields = explode(',', $fields);

        $groupSize = 1000;

        $query = (new \yii\db\Query())
            ->select('*')
            ->from('mls_rets')
            ->indexBy('list_no')
            ->limit($groupSize);

        $mlsdb = \WS::$app->mlsdb;
        DbQuery::patch($query, $groupSize, function ($query, $totalCount, $that) use($mlsdb, $fields) {
            $rows = $query->orderBy('list_no', 'ASC')->all($mlsdb);
            foreach($rows as $row) {
                //合并json数据到主体
                if ($row['json_data']) {
                    $row = array_merge($row, (array)json_decode($row['json_data']));
                }

                $rets = new \common\component\Object($row);

                if ($rowData = $that->_processRow($rets)) {
                    foreach($rowData as $key=>$data) {
                        if (!in_array($key, $fields)) {
                            unset($rowData[$key]);
                        }
                    }

                    $rowCount = \yii::$app->db->createCommand()
                        ->update('rets_mls_index', $rowData, 'id=:id', [':id'=>$row['list_no']])
                        ->execute();

                    Counter::_($rowCount > 0 ? 'index' : 'error')->increase();
                }

                //屏幕输出
                $index = Counter::_('index')->value;
                $error = Counter::_('error')->value;
                echo "indexed:{$index}/error:{$error}/total:{$totalCount}                   \r";

                unset($row);
                unset($rets);
            }

        }, $this, $mlsdb);
    }

    public function actionBuild()
    {
        $mlsdb = WS::$app->mlsdb;
        $groupSize = 1000;

        $indexLatestAt = Configure::getValue('rets.index.latest_date');
        $query = (new \yii\db\Query())
            ->select('*')
            ->from('mls_rets')
            ->where('update_date > :update_date', [':update_date' => $indexLatestAt])
            ->limit($groupSize);

        $hasIndexed = false;
        DbQuery::patch($query, $groupSize, function ($query, $totalCount, $that) use (& $indexLatestAt, & $hasIndexed) {
            $mlsdb = WS::$app->mlsdb;
            $transaction = \yii::$app->db->beginTransaction();

            $rows = $query->orderBy('list_no', 'ASC')->all($mlsdb);
            foreach($rows as $row) {
                $updateDateAt = $row['update_date'];

                //合并json数据到主体
                if ($row['json_data']) {
                    $row = array_merge($row, (array)json_decode($row['json_data']));
                    unset($row['json_data']);
                }

                $rets = new \common\component\Object($row);

                //处理索引行数据
                if ($rowData = $that->_processRow($rets)) {
                    $rowCount = $that->_writeRow($rowData);
                    Counter::_($rowCount > 0 ? 'index' : 'error')->increase();
                }
                else {
                    Counter::_('error')->increase();
                }

                //附加处理
                if (strtotime($updateDateAt) > strtotime($indexLatestAt)) {
                    var_dump($updateDateAt);exit;
                    $indexLatestAt = $row['update_date'];
                }

                if (! $hasIndexed) $hasIndexed = true;

                unset($row);
                unset($rets);

                //屏幕输出
                $index = Counter::_('index')->value;
                $error = Counter::_('error')->value;
                echo "indexed:{$index}/error:{$error}/total:{$totalCount}                   \r";
            }

            $transaction->commit();

        }, $this, $mlsdb);

        //执行完过后再执行状态
        var_dump($indexLatestAt);exit;
        \yii::$app->db->createCommand()->update('core_config_data', ['value' => $indexLatestAt], 'path="rets.index.latest_date"')->execute();

        //日志
        file_put_contents(__DIR__.'/../log.log', date('Y-m-d H:i:s').' rets.index'."\n", FILE_APPEND);

        //执行过后相关的命令
        if ($hasIndexed) {
            \WS::$app->shellMessage->send('summery/index');
            \WS::$app->shellMessage->send('sitemap/generate');
        }
    }

    protected function _processRow($rets)
    {
        $rowData = [];
        foreach (Config::getFieldMap() as $name => $options) {
            //fetch value
            $value = null;
            if(isset($options['value'])) {
                $valueFn = $options['value'];
                $value = $valueFn($rets);
            }
            else {
                $value = $rets->get($name);
            }
            if(isset($options['filter'])) {
                $filterFn = $options['filter'];
                $value = $filterFn($value);
            }

            $rowData[$name] = $value;

        }

        return $rowData;
    }

    protected function _writeRow($data)
    {
        $db = \yii::$app->db;
        $id = $data['id'];

        if ((new Query())->from('rets_mls_index')->where(['id'=>$id])->exists()) {
            return $db->createCommand()
                ->update('rets_mls_index', $data, 'id=:id', [':id'=>$id])
                ->execute();
        }

        return $db->createCommand()
            ->insert('rets_mls_index', $data)
            ->execute();
    }

    public function doUpdateSet_town_values($row)
    {
        $town_value = crc32($row['town']);
        return "town_value={$town_value}";
    }

    public function doUpdateSet_zip_code_values($row)
    {
        $zip_code_value = crc32($row['zip_code']);
        return "zip_code_value={$zip_code_value}";
    }

    public function doUpdateSet_subway_line_ids($row)
    {
        $subwayLineIds = \common\rets\helper\SubwayGeo::getMatchedLines($row['longitude'], $row['latitude'], 1);
        $subwayLineIds = implode(',', $subwayLineIds);
        return "subway_lines=($subwayLineIds)";
    }
}

class Config {
    public static function getFieldMap()
    {
        return [
            'id'=>[
                'value'=>function($d) {
                    return $d['list_no'];
                }
            ],
            'index_at'=>[
                'value'=>function(){
                    return date('Y-m-d H:i:s');
                }
            ],
            'prop_type'=>[],
            'prop_category'=>[
                'value'=>function($d){
                    $returnValue = 99;
                    $typeValue = $d['prop_type'];

                    if($typeValue == 'RN') {
                        $returnValue = 1;
                    }
                    elseif(in_array($typeValue, ['SF', 'MF', 'CC'])) {
                        $returnValue = 2;
                    }
                    elseif($typeValue == 'CI') {
                        $returnValue = 3;
                    }
                    elseif($typeValue == 'BU') {
                        $returnValue = 4;
                    }
                    elseif($typeValue == 'LD') {
                        $returnValue = 5;
                    }
                    return $returnValue;
                }
            ],
            'is_rental'=>[
                'value'=>function($d) {
                    return $d['prop_type'] == 'RN' ? 1 : 0;
                }
            ],
            'prop_type_smc'=>[
                'value'=>function($d){
                    return in_array($d['prop_type'], ['SF', 'MF', 'CC']) ? 1 : 0;
                }
            ],
            'location'=>[
                'value'=>function($d) {
                    return \common\estate\helpers\Rets::buildLocation($d);
                }
            ],
            'list_date'=>[],
            'list_price'=>[
                'filter'=>function($val) {
                    return floatval($val);
                }
            ],
            'ant_sold_date' => [],
            'sale_price'=>[
                'filter'=>function($val) {
                    return floatval($val);
                }
            ],
            'no_bedrooms'=>[
                'filter'=>function($val){
                    return intval($val);
                }
            ],
            'no_bathrooms'=>[
                'value'=>function($d){
                    return intval($d['no_full_baths']) + intval($d['no_half_baths']);
                }
            ],
            'garage_spaces'=>[
                'filter'=>function($val){
                    return intval($val);
                }
            ],
            'parking_spaces'=>[
                'filter'=>function($val){
                    return intval($val);
                }
            ],
            'square_feet'=>[
                'filter'=>function($val){
                    return floatval($val);
                }
            ],
            'lot_size'=>[
                'filter'=>function($val) {
                    return $val;
                }
            ],
            'latitude'=>[
                'filter'=>function($val) {
                    return $val;
                }
            ],
            'longitude'=>[
                'filter'=>function($val) {
                    return $val;
                }
            ],
            'latitude_rad'=>[
                'value'=>function($d) {
                    return deg2rad($d['latitude']);
                }
            ],
            'longitude_rad'=>[
                'value'=>function($d) {
                    return deg2rad($d['longitude']);
                }
            ],
            'subway_lines'=>[
                'value'=>function($d) {
                    $subwayLineIds = \common\estate\helpers\SubwayGeo::getMatchedLines($d['longitude'], $d['latitude'], 1);
                    $subwayLineIds = implode(',', $subwayLineIds);
                    return "{{$subwayLineIds}}";
                }
            ],
            'subway_stations'=>[
                'value'=>function($d) {
                    $subwayStationIds = \common\estate\helpers\SubwayGeo::getMatchedStations($d['longitude'], $d['latitude'], 1);
                    $subwayStationIds = implode(',', $subwayStationIds);
                    return "{{$subwayStationIds}}";
                }
            ],
            'town'=>[],
            'zip_code'=>[],
            'status'=>[],
            'is_show'=>[
                'value'=>function($d) {
                    return in_array($d['status'], ['ACT','NEW','BOM','PCG','RAC','EXT']);
                }
            ]
        ];
    }
}

class Counter
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