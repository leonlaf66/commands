<?php
namespace app\commands;

use WS;
use yii\console\Controller;

class NewsProcessController extends Controller
{
    public function actionIndex($id)
    {
        $db = WS::$app->db;
        $where = 'id='.intval($id);

        // 以下的程序将在后台执行
        $db->createCommand()
            ->update('news', ['status' => 0], $where)
            ->execute();

        $content = $db->createCommand('select content from news where '.$where)->queryScalar();

        // 编译内容
        $newContent = WS::$app->wxImage->process($content, true);
        $db->createCommand()
            ->update('news', ['content' => $newContent], $where)
            ->execute();

        $db->createCommand()
            ->update('news', ['status' => 1], $where)
            ->execute();
    }
}