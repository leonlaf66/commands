<?php
namespace app\commands;

use WS;
use yii\console\Controller;

class ListhubSummeryController extends Controller
{
    public function actionIndex()
    {
        $db = WS::$app->db;
        
        $services = [
            // 图形统计数据
            'writeDCharts' => function ($areaId, $rows) use ($db) {
                 $db->createCommand()
                     ->delete('site_chart_setting', ['area_id' => $areaId])
                     ->execute();

                foreach ($rows as $row) {
                    $row['area_id'] = $areaId;
                    $db->createCommand()
                        ->insert('site_chart_setting', $row)
                        ->execute();
                }
            }
        ];

        /*分区域统计*/
        foreach (['ny', 'ga', 'ca', 'il'] as $areaId) {
            $rows = [];
            $stateId = strtoupper($areaId);
            $areaSummaries = $this->areaSummaries();
            foreach ($areaSummaries as $summeryKey => $callable) {
                if ($data = $callable($stateId, $this)) {
                    $data = json_encode($data);
                    $rows[] = ['path' => $summeryKey, 'data' => $data];
                }
            }

            /*图形统计*/
            ($services['writeDCharts'])($areaId, $rows);
        }


        /*log*/
        file_put_contents(__DIR__.'/../log.log', date('Y-m-d H:i:s').' summary'."\n", FILE_APPEND);
    }

    public function areaSummaries()
    {
        return [
            /* Home */
            // 房源均价
            'marketing/average-housing-price' => function ($stateId) {
                // 当前平均价格
                $sql = "select avg(list_price)
                    from listhub_index
                    where state = '{$stateId}'
                      and prop_type in ('SF','CC','MF')
                      and status = 'Active'
                      and list_price > 0";

                $avgPrice = \WS::$app->db->createCommand($sql)->queryScalar();

                // 上月已售出平均价格
                $sql = "select avg(list_price)
                    from listhub_index
                    where state = '{$stateId}'
                      and prop_type in ('SF','CC','MF')
                      and status='Sold'
                      and list_price > 0
                      and ant_sold_date > now() - interval '1 month'";
                $priorPirce = \WS::$app->db->createCommand($sql)->queryScalar();

                return [
                    'value'=>number_format($avgPrice, 0),
                    'dir' => $avgPrice > $priorPirce ? 'up' : 'down'
                ];
            },
            // 平均环比较上月
            'marketing/month-on-month-change' => function ($stateId) {
                // 2个月前
                $sql = "select avg(list_price)
                    from listhub_index
                    where state = '{$stateId}'
                      and prop_type <> 'RN'
                      and status='Sold'
                      and list_price > 0
                      and ant_sold_date > now() - interval '2 month'
                      and ant_sold_date < now() - interval '1 month'";
                $avgPrice1 = \WS::$app->db->createCommand($sql)->queryScalar();

                // 1个月前
                $sql = "select avg(list_price)
                    from listhub_index
                    where state = '{$stateId}'
                      and prop_type <> 'RN'
                      and list_price > 0
                      and status='Sold'
                      and ant_sold_date > now() - interval '1 month'";
                $avgPrice2 = \WS::$app->db->createCommand($sql)->queryScalar();

                if ($avgPrice2 >= $avgPrice1) { // 涨了多少
                    if (! $avgPrice1) $avgPrice1 = $avgPrice2;
                    return [
                        'value' => number_format($avgPrice2 / $avgPrice1, 2),
                        'dir' => 'up'
                    ];
                } else { // 跌了多少
                    if (! $avgPrice2) $avgPrice2 = $avgPrice1;
                    return [
                        'value' => number_format($avgPrice1 / $avgPrice2, 2),
                        'dir' => 'down'
                    ];
                }
            },
            // 上月成交量
            'marketing/prop-transactions-of-last-month' => function ($stateId) {
                // 2个月前
                $sql = "select count(*) as total
                    from listhub_index
                    where state = '{$stateId}'
                      and prop_type <> 'RN'
                      and status='Sold'
                      and ant_sold_date > now() - interval '2 month'
                      and ant_sold_date < now() - interval '1 month'";
                $total1 = \WS::$app->db->createCommand($sql)->queryScalar();

                // 1个月前
                $sql = "select count(*) as total
                    from listhub_index
                    where state = '{$stateId}'
                      and prop_type <> 'RN'
                      and status='Sold'
                      and ant_sold_date > now() - interval '1 month'";
                $total2 = \WS::$app->db->createCommand($sql)->queryScalar();

                return [
                    'value' => $total2,
                    'dir' => $total2 > $total1 ? 'up' : 'down'
                ];
            },
            // New Listings of this month
            'marketing/new-listings-of-this-month' => function ($stateId) {
                // 当前平均价格
                $sql = "select count(*) as count
                    from listhub_index
                    where state = '{$stateId}'
                      and prop_type in ('SF','CC','MF')
                      and status='Active'
                      and list_price > 0
                      and list_date > now() - interval '1 month'";

                $count1 = \WS::$app->db->createCommand($sql)->queryScalar();

                // 上月已售出平均价格
                $sql = "select count(*) as count
                    from listhub_index
                    where state = '{$stateId}'
                      and prop_type in ('SF','CC','MF')
                      and status='Sold'
                      and list_price > 0
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