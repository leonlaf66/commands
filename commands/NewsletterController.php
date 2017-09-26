<?php
namespace app\commands;

use WS;
use yii\console\Controller;
use common\customer\RetsNewsletter as Newsletter;

class NewsletterController extends Controller
{
    public function actionIndex()
    {
        $inListNos = [];
        $tasks = Newsletter::findTasks()->all();
        $newsletters = [];

        WS::$app->language = 'en-US';
        foreach($tasks as $task) {
            $userId = $task->user_id;

            if(! isset($inListNos[$userId])) $inListNos[$userId] = []; 
            if(! isset($newsletters[$userId])) $newsletters[$userId] = [];
            
            if($retsItems = $task->getSearchResult()) {
                foreach($retsItems as $rets) {
                    if(in_array($rets->list_no, $inListNos[$userId])) {
                        continue;
                    }
                    $newsletters[$userId][] = $rets;
                }
            }

            if ($task->language) {
                WS::$app->language = $task->language;
            }

            $task->makeTaskStatus();
        }

        foreach($newsletters as $userId=>$retsItems) {
            if(count($retsItems) > 0) {
                $account = \common\customer\Account::findOne($userId);
                $account->sendNewslatterEmail(tt('Usleju Subscription', '米乐居房源订阅'), 'rets/subscription', [
                    'retsItems'=>$retsItems
                ]);
            }
        }
    }
}