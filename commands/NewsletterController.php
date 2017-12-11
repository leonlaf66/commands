<?php
namespace app\commands;

use WS;
use yii\console\Controller;
use common\customer\RetsNewsletter as Newsletter;

class NewsletterController extends Controller
{
    public function actionIndex()
    {
        $tasks = Newsletter::findTasks()->all();

        WS::$app->language = 'en-US';
        foreach($tasks as $task) {
            $userId = $task->user_id;

            if ($task->language) {
                WS::$app->language = $task->language;
            }

            if($retsItems = $task->getSearchResult()) {
                $account = \common\customer\Account::findOne($userId);
                $account->sendNewslatterEmail(tt('Usleju Subscription', '米乐居房源订阅').':'.$task->name, 'rets/subscription', [
                    'retsItems'=> $retsItems
                ]);
            }

            $task->makeTaskStatus();
        }
    }
}