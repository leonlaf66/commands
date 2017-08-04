<?php
namespace app\commands;

use WS;
use yii\console\Controller;

class SummeryController extends Controller
{
    public function actionIndex()
    {
        $db = WS::$app->db;
        $rows = [];

        $services = [
            'townDataUpdate' => function ($town, $path, $data) use ($db) {
                $sql = 'select 1 from schooldistrict_data where town_id=:town and path=:path limit 1';
                $isExists = $db->createCommand($sql)
                    ->bindValue(':town', $town)
                    ->bindValue(':path', $path)
                    ->queryScalar();

                if ($isExists) {
                    return $db->createCommand()
                        ->update('schooldistrict_data', [
                            'data' => json_encode($data)
                        ], 'town_id=:town and path=:path')
                        ->bindValue(':town', $town)
                        ->bindValue(':path', $path)
                        ->execute();
                } else {
                    return $db->createCommand()
                        ->insert('schooldistrict_data', [
                            'town_id' => $town,
                            'path' => $path,
                            'data' => json_encode($data)
                        ])
                        ->execute();
                }
            },
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

        $summaries = $this->summaries();
        foreach ($summaries as $summeryKey => $callable) {
            if ($data = $callable($this)) {
                $summaryTypeId = explode('/', $summeryKey)[0];
                if ($summaryTypeId === 'sd') {
                    foreach ($data as $townId => $townData) {
                        ($services['townDataUpdate'])($townId, $summeryKey, $townData);
                    }
                } else {
                    $data = json_encode($data);
                    $rows[] = ['path' => $summeryKey, 'data' => $data];
                }
            }
        }

        ($services['writeDCharts'])($rows);
    }

    public function summaries()
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
            },

            /*学区详情*/
            // 平均房价
            'sd/average-price' => function () {
                $sql = "select town, avg(list_price) as value
                    from rets_mls_index
                    where prop_type in ('SF','CC','MF')
                      and status in ('ACT','NEW','BOM','PCG','RAC','EXT')
                    group by town";
                $rows = \WS::$app->db->createCommand($sql)->queryAll();

                return array_key_value($rows, function ($row) {
                    return [
                        $row['town'],
                        number_format($row['value'], 0)
                    ];
                });
            },
            // 平均月租
            'sd/avergage-rental-price' => function () {
                $sql = "select town, avg(list_price) as value
                    from rets_mls_index
                    where prop_type='RN'
                      and status in ('ACT','NEW','BOM','PCG','RAC','EXT')
                    group by town";
                $rows = \WS::$app->db->createCommand($sql)->queryAll();

                return array_key_value($rows, function ($row) {
                    return [
                        $row['town'],
                        number_format($row['value'], 0)
                    ];
                });
            },
            // 年成交量
            'sd/year-down-total' => function () {
                $sql = "select town, count(*) as value
                    from rets_mls_index
                    where prop_type <> 'RN'
                      and status='SLD'
                      and ant_sold_date > now() - interval '1 year'
                    group by town";

                $rows = \WS::$app->db->createCommand($sql)->queryAll();

                return array_key_value($rows, function ($row) {
                    return [
                        $row['town'],
                        $row['value']
                    ];
                });
            },
            // 近三年学区房季度成交量
            'sd/three-years-charts' => function () {
                return null;
            },
            // 近五年学区房每季度房价走势
            'sd/five-years-charts' => function () {
                return null;
            },
            /*学区其它*/
            'sd/total' => function () {
                $sql = "select town, count(*) as count
                    from rets_mls_index
                    where prop_type <> 'RN'
                      and status in ('ACT','NEW','BOM','PCG','RAC','EXT')
                    group by town";

                $rows = \WS::$app->db->createCommand($sql)->queryAll();

                return array_key_value($rows, function ($row) {
                    return [
                        $row['town'],
                        $row['count']
                    ];
                });
            },

            /* 房源详情 */
            // 近三年房价走势
            'sd/three-years-charts' => function () {
                return null;
            },
        ];
    }
}