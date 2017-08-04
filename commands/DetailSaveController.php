<?php
namespace app\commands;

use yii\console\Controller;
use common\estate\helpers\DetailFieldRules;

class DetailSaveController extends Controller
{
    public function actionContent()
    {
        $types = ['bu', 'cc', 'ci', 'ld', 'mf', 'rn', 'sf'];
        foreach ($types as $type) {
            $xmlContent = DetailFieldRules::findOne($type)->getRawContent();
            \WS::$app->db->createCommand()->insert('rets_detail_field_rules', [
                'code'=>$type,
                'xml_rules'=>$xmlContent
            ])->execute();
        }
    }
}