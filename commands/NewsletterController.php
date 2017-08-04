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

            $task->makeTaskStatus();
        }

        foreach($newsletters as $userId=>$retsItems) {
            if(count($retsItems) > 0) {
                $account = \common\customer\Account::findOne($userId);
                $account->sendNewslatterEmail('Wesnail Newsletter', 'rets/newsletters', [
                    'retsItems'=>$retsItems
                ]);
            }
        }
    }
}