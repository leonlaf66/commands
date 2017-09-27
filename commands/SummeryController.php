<?php
namespace app\commands;

use WS;
use yii\console\Controller;

class SummeryController extends Controller
{
    public function actionIndex()
    {
        $db = WS::$app->db;
        
        $services = [
            // 分学区统计数据
            'sdDataUpdate' => function ($sdCode, $path, $data) use ($db) {
                $isExists = (new \yii\db\Query())
                    ->from('schooldistrict_data')
                    ->where(['code' => $sdCode, 'path' => $path])
                    ->exists();

                if ($isExists) {
                    return $db->createCommand()
                        ->update('schooldistrict_data', [
                            'data' => json_encode($data)
                        ], 'code=:code and path=:path')
                        ->bindValue(':code', $sdCode)
                        ->bindValue(':path', $path)
                        ->execute();
                } else {
                    return $db->createCommand()
                        ->insert('schooldistrict_data', [
                            'code' => $sdCode,
                            'path' => $path,
                            'data' => json_encode($data)
                        ])
                        ->execute();
                }
            },
            // 图形统计数据
            'writeDCharts' => function ($rows) use ($db) {
                 $db->createCommand()
                    ->delete('data_charts')
                    ->execute();

                foreach ($rows as $row) {
                    $db->createCommand()
                        ->insert('data_charts', $row)
                        ->execute();
                }
            }
        ];

        /*分学区统计*/
        $sdCodes = $db->createCommand('select code from schooldistrict_items')->queryColumn();
        $townSummeries = $this->townSummeries();
        foreach ($sdCodes as $sdCode) {
            $towns = explode('/', $sdCode);
            foreach ($townSummeries as $summeryKey => $callable) {
                if ($value = $callable($towns, $this)) {
                    ($services['sdDataUpdate'])($sdCode, $summeryKey, $value);
                }
            }
        }

        /*分区域统计*/
        $rows = [];
        $areaSummaries = $this->areaSummaries();
        foreach ($areaSummaries as $summeryKey => $callable) {
            if ($data = $callable($this)) {
                $data = json_encode($data);
                $rows[] = ['path' => $summeryKey, 'data' => $data];
            }
        }

        /*图形统计*/
        ($services['writeDCharts'])($rows);

        /*log*/
        file_put_contents(__DIR__.'/../log.log', date('Y-m-d H:i:s').' summary', FILE_APPEND);
    }

    public function townSummeries()
    {
        return [
            // 平均房价
            'average-price' => function ($towns) {
                return (new \yii\db\Query())
                    ->select('avg(list_price) as value')
                    ->from('rets_mls_index')
                    ->where(['in', 'town', $towns])
                    ->andWhere(['in', 'prop_type', ['SF','CC','MF']])
                    ->andWhere(['in', 'status', ['ACT','NEW','BOM','PCG','RAC','EXT']])
                    ->andWhere(['in', 'town', $towns])
                    ->scalar();
            },
            // 平均月租
            'avergage-rental-price' => function ($towns) {
                return (new \yii\db\Query())
                    ->select('avg(list_price) as value')
                    ->from('rets_mls_index')
                    ->where(['in', 'town', $towns])
                    ->andWhere(['prop_type' => 'RN'])
                    ->andWhere(['in', 'status', ['ACT','NEW','BOM','PCG','RAC','EXT']])
                    ->scalar();
            },
            // 年成交量
            'year-down-total' => function ($towns) {
                return (new \yii\db\Query())
                    ->select('count(*) as value')
                    ->from('rets_mls_index')
                    ->where(['in', 'town', $towns])
                    ->andWhere(['<>', 'prop_type', 'RN'])
                    ->andWhere(['status' => 'SLD'])
                    ->andWhere("ant_sold_date > now() - interval '1 year'")
                    ->scalar();
            },
            // 近三年学区房季度成交量
            'three-years-charts' => function ($towns) {
                return null;
            },
            // 近五年学区房每季度房价走势
            'five-years-charts' => function ($towns) {
                return null;
            },
            /*学区房源数量*/
            'total' => function ($towns) {
                return (new \yii\db\Query())
                    ->select('count(*) as value')
                    ->from('rets_mls_index')
                    ->where(['in', 'town', $towns])
                    ->andWhere(['<>', 'prop_type', 'RN'])
                    ->andWhere(['in', 'status', ['ACT','NEW','BOM','PCG','RAC','EXT']])
                    ->scalar();
            },

            /* 房源详情 */
            // 近三年房价走势
            'three-years-charts' => function ($towns) {
                return null;
            },
        ];
    }

    public function areaSummaries()
    {
        return [
            /* Home */
            // 房源均价
            'marketing/average-housing-price' => function () {
                // 当前平均价格
                $sql = "select avg(list_price)
                    from rets_mls_index
                    where prop_type in ('SF','CC','MF')
                      and status in ('ACT','NEW','BOM','PCG','RAC','EXT')";

                $avgPrice = \WS::$app->db->createCommand($sql)->queryScalar();

                // 上月已售出平均价格
                $sql = "select avg(sale_price)
                    from rets_mls_index
                    where prop_type in ('SF','CC','MF')
                      and status='SLD'
                      and ant_sold_date > now() - interval '1 month'";
                $priorPirce = \WS::$app->db->createCommand($sql)->queryScalar();

                return [
                    'value'=>number_format($avgPrice, 0),
                    'dir' => $avgPrice > $priorPirce ? 'up' : 'down'
                ];
            },
            // 平均环比较上月
            'marketing/month-on-month-change' => function () {
                // 2个月前
                $sql = "select avg(sale_price)
                    from rets_mls_index
                    where prop_type <> 'RN'
                      and status='SLD'
                      and ant_sold_date > now() - interval '2 month'
                      and ant_sold_date < now() - interval '1 month'";
                $avgPrice1 = \WS::$app->db->createCommand($sql)->queryScalar();

                // 1个月前
                $sql = "select avg(sale_price)
                    from rets_mls_index
                    where prop_type <> 'RN'
                      and status='SLD'
                      and ant_sold_date > now() - interval '1 month'";
                $avgPrice2 = \WS::$app->db->createCommand($sql)->queryScalar();

                if ($avgPrice2 >= $avgPrice1) { // 涨了多少
                    return [
                        'value' => number_format($avgPrice2 / $avgPrice1, 2),
                        'dir' => 'up'
                    ];
                } else { // 跌了多少
                    return [
                        'value' => number_format($avgPrice1 / $avgPrice2, 2),
                        'dir' => 'down'
                    ];
                }
            },
            // 上月成交量
            'marketing/prop-transactions-of-last-month' => function () {
                // 2个月前
                $sql = "select count(*) as total
                    from rets_mls_index
                    where prop_type <> 'RN'
                      and status='SLD'
                      and ant_sold_date > now() - interval '2 month'
                      and ant_sold_date < now() - interval '1 month'";
                $total1 = \WS::$app->db->createCommand($sql)->queryScalar();

                // 1个月前
                $sql = "select count(*) as total
                    from rets_mls_index
                    where prop_type <> 'RN'
                      and status='SLD'
                      and ant_sold_date > now() - interval '1 month'";
                $total2 = \WS::$app->db->createCommand($sql)->queryScalar();

                return [
                    'value' => $total2,
                    'dir' => $total2 > $total1 ? 'up' : 'down'
                ];
            },
            // New Listings of this month
            'marketing/new-listings-of-this-month' => function () {
                // 当前平均价格
                $sql = "select count(*) as count
                    from rets_mls_index
                    where prop_type in ('SF','CC','MF')
                      and status in ('ACT','NEW','BOM','PCG','RAC','EXT')
                      and list_date > now() - interval '1 month'";

                $count1 = \WS::$app->db->createCommand($sql)->queryScalar();

                // 上月已售出平均价格
                $sql = "select count(*) as count
                    from rets_mls_index
                    where prop_type in ('SF','CC','MF')
                      and status='SLD'
                      and ant_sold_date > now() - interval '1 month'";
                $count2 = \WS::$app->db->createCommand($sql)->queryScalar();

                return [
                    'value'=>$count1,
                    'dir' => $count2 > $count1 ? 'up' : 'down'
                ];
            }
        ];
    }
}