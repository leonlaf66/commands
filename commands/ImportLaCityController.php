<?php
namespace app\commands;

use yii\console\Controller;
use QL\QueryList;

defined('APP_ROOT') || define('APP_ROOT', dirname(__FILE__).'/../');

class ImportLaCityController extends Controller
{
    const STATE = 'CA';

    public function actionImport()
    {
        $citis = file_get_contents(__DIR__.'/../../fdn/data/LA.city');
        $citis = explode("\n", $citis);
        $citis = array_filter($citis, function ($name) {
            return !empty($name);
        });
        $citis = array_unique($citis);

        $parentId = $this->getParentId();
        $startId = $this->getLastId() + 1;

        foreach ($citis as $cityName) {
            $this->flashCity($parentId, $startId, $cityName);
            $startId ++;
        }
    }

    public function flashCity($parentId, $newId, $name)
    {
        $cityId = (new \yii\db\Query())
            ->from('city')
            ->select('id')
            ->where([
                'state' => self::STATE,
                'name' => $name
            ])
            ->scalar();

        if (!$cityId) {
            \WS::$app->db->createCommand()
                ->insert('city', [
                    'id' => $newId,
                    'name' => $name,
                    'state' => self::STATE,
                    'parent_id' => $parentId
                ])->execute();
            $cityId = \WS::$app->db->lastInsertID;
        }

        return $cityId;
    }

    public function getLastId()
    {
        return (new \yii\db\Query())
            ->from('city')
            ->select('id')
            ->orderBy(['id' => SORT_DESC])
            ->limit(1)
            ->scalar();
    }

    public function getParentId()
    {
        return (new \yii\db\Query())
            ->from('city')
            ->select('id')
            ->where(['state' => self::STATE, 'name' => 'Los Angeles'])
            ->scalar();
    }
}