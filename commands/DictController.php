<?php
namespace app\commands;

use yii\console\Controller;

defined('APP_ROOT') || define('APP_ROOT', dirname(__FILE__).'/../');

class DictController extends Controller
{
    public function actionFetch()
    {
        $totalCount = \Yii::$app->search->createCommand('select count(*) as count from rt_rets')->queryScalar();
        $pageSize = 1000;
        $pageCount = ceil($totalCount / ($pageSize * 1.0));

        for($pageNo=1; $pageNo <= $pageCount; $pageNo++) {
            echo "{$pageNo}/{$pageCount} ...\n";

            $offset = ($pageNo - 1) * $pageSize;
            $ids = \Yii::$app->search->createCommand("select id from rt_rets limit {$offset},{$pageSize} option max_matches=1000000")->queryColumn();

            foreach($ids as $id) {
                if($rets = \app\modules\estate\models\Rets::findOne($id, true)) {
                    $rets = \app\modules\indexer\models\Rets::init($rets);

                    foreach(['zip_code'] as $code) {
                        Dict::add($rets, $code);
                    }
                }
            }
        }

        $data = Dict::getData();
        file_put_contents(__DIR__.'/../modules/estate/etc/rets.dicts.php', "<?php\nreturn ".var_export($data, true).';');
    }
}

class Dict
{
    protected static $_data = [];

    public static function add($rets, $code)
    {
        $value = $rets->get($code, '');
        if($value !== '') {
            if(! isset(self::$_data[$code])) self::$_data[$code] = [];

            if(! isset(self::$_data[$code][$value])) {
                self::$_data[$code][$value] = ['city'=>self::_getCityName($rets->get('town'))];
            }
        }
    }

    protected static function _getCityName($code)
    {
        static $cityDictData = null;
        if(is_null($cityDictData)) {
            $cityDictData = include(APP_ROOT.'/modules/estate/etc/rets.city.dict.php');
        }
        return isset($cityDictData[$code]) ? $cityDictData[$code] : $code;
    }

    public static function getData()
    {
        return self::$_data;
    }
}