<?php
namespace app\commands;

use WS;
use ArrayObject;
use yii\console\Controller;
use common\customer\RetsNewsletter as Newsletter;

class NewsletterController extends Controller
{
    public function actionIndex()
    {
        $tasks = $this->getTasks();

        foreach ($tasks as $task) {
            $task = (object)$task;
            $task->data = json_decode($task->data);

            $taskId = $task->id;
            $userId = $task->user_id;
            $houseIds = $this->getHouseSearchResults($task);

            if (!empty($houseIds)) {
                // 基本环境
                \WS::$app->language = $task->language; /*发邮件需要*/

                $houses = \WS::$app->graphql->request('find-newsletter-houses', [
                    'ids' => $houseIds
                ], [
                    'area-id' => $task->area_id,
                    'language' => $task->language
                ], [])->result;

                $houses = $this->buildHousesResults($houses, $task->area_id, $task->language);

                $account = \common\customer\Account::findOne($userId);
                $account->sendNewslatterEmail(tt('Usleju Subscription', '米乐居房源订阅').':'.$task->name, 'rets/subscription-v2', [
                    'retsItems'=> $houses
                ]);
            }

            $this->makeTaskStatus($taskId, $task->data->notification_cycle);
        }
    }

    public function getTasks()
    {
        return (new \yii\db\Query())
            ->from('house_member_newsletter')
            ->select(['id', 'name', 'user_id', 'data', 'last_task_at', 'language', 'area_id'])
            ->where('next_task_at<now()')
            ->all();
    }

    public function getHouseSearchResults($task)
    {
        $areaId = $task->area_id;
        $data = $task->data;

        $filters = [
            'city' => function ($query, $cityId) use ($areaId) {
                if ($areaId === 'ma') { // 这里是city code， 需要转换为city id
                    $cityId = (new \yii\db\Query())
                        ->from('town')
                        ->select('id')
                        ->where(['short_name' => $cityId])
                        ->scalar();
                    if (!$cityId) $cityId = -999; // 特意使其无结果
                }

                $query->andWhere(['city_id' => $cityId]);
            },
            'prop_type' => function ($query, $prop) {
                $query->andWhere(['prop_type' => $prop]);
            },
            'price_range' => function ($query, $range) {
                list($start, $end) = explode('-', $range);
                $query->andWhere(['>=', 'list_price', $start]);
                $query->andWhere(['<=', 'list_price', $end]);
            },
            'bed_rooms' => function ($query, $num) {
                $query->andWhere(['>=', 'no_beds', $num]);
            },
            'bath_rooms' => function ($query, $num) {
                $query->andWhere('no_baths[1]>'.intval($num));
            }
        ];

        $query = (new \yii\db\Query())
            ->from('house_index_v2')
            ->select('list_no')
            ->where(['area_id' => $areaId])
            ->andWhere(['is_online_abled' => true])
            ->andWhere(['>', 'list_date', $task->last_task_at])
            ->limit(100);

        foreach ($data as $filterId => $filterValue) {
            if (!isset($filters[$filterId])) continue;
            $filterFn = $filters[$filterId];

            $filterFn($query, $filterValue);
        }

        return $query->column();
    }

    public function buildHousesResults($houses, $areaId, $language)
    {
        return array_map(function ($house) use ($areaId, $language) {
            /*for price*/
            $price = intval($house->price);
            if ($language === 'zh-CN') {
                if ($price > 10000) {
                    $house->list_price = number_format($price * 1.0 / 10000, 2).'万美元';
                } else {
                    $house->list_price = $price.'美元';
                }
            } else {
                $house->list_price = '$'.$house->list_price;
            }
            
            /*for square feet 0.092903*/
            if ($house->square_feet && $house->square_feet !=='') {
                $house->square_feet = intval($house->square_feet);
                if ($language === 'zh-CN') {
                    $house->square_feet = number_format($house->square_feet * 0.092903).'平方米';
                } else {
                    $house->square_feet .= 'Sq.Ft';
                }
            }

            /*for url*/
            $house->url_path = ($language === 'zh-CN' ? 'zh/' : '')
                .($house->prop === 'RN' ? 'lease' : 'purchase')
                .'/'.$house->id.'/';

            return $house;
        }, $houses);
    }

    public function makeTaskStatus($taskId, $notificationCycleType = '1')
    {
        $lastTaskAt = date('Y-m-d', time()).' 00:00:00';

        $today = strtotime(date('Y-m-d', time()));
        $nextTaskAt = null;
        if($notificationCycleType === '1') {
            $nextTaskAt = date('Y-m-d', strtotime('+1 day', $today));
        }
        else {
            $nextTaskAt = date('Y-m-d', strtotime('+1 week', $today));
        }

        return \WS::$app->db->createCommand()
            ->update('house_member_newsletter', [
                'last_task_at' => $lastTaskAt,
                'next_task_at' => $nextTaskAt
            ], 'id=:id', [
                ':id' => $taskId
            ]);
    }
}