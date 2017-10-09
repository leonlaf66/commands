<?php
namespace app\commands;

use yii;
use yii\console\Controller;

class DataBaseConvertController extends Controller
{
    public function actionIndex()
    {
        $tableNames = [
            
        ];
        
        foreach ($tableNames as $tableName) {
            $primaryField = $this->getTablePrimaryKey($tableName);
            var_dump(implode(',', $primaryField));exit;

            // 准备命令
            $commands = [
                "create table {$tableName}_tmp as select * from {$tableName}", //备份主表数据
                "truncate {$tableName}", // 清空主表数据
                "CREATE TABLE ma.{$tableName}() INHERITS ({$tableName})", // 创建分表
                function ($tableName, $that) { // 创建分表主键
                    if ($primaryField = $that->getTablePrimaryKey($tableName)) {
                        $primaryField = implode(',', $primaryField);
                        return "alter table ma.{$tableName} add primary key({$primaryField})";
                    }
                },
                function ($tableName, $that) { // 创建分类的主键序列
                    if ($primaryField = $that->getTablePrimaryKey($tableName)) {
                        if ($idSequence = $that->getIDSequence($tableName, $primaryField)) {
                            return "ALTER TABLE ma.{$tableName} ALTER COLUMN {$primaryField} SET DEFAULT {$idSequence}";
                        }
                    }  
                },
                "INSERT INTO ma.{$tableName} select * from {$tableName}_tmp", // 还原数据到分表
                "drop table {$tableName}_tmp",
            ];

            // 执行
            $transaction = yii::$app->db->beginTransaction();
            foreach ($commands as $command) {
                if (is_callable($command)) {
                    $command = $command($tableName, $this);
                    if (! $command) continue;
                }
                yii::$app->db->createCommand($command)->execute();
            }
            $transaction->commit();
        }
    }

    protected function getTablePrimaryKey($tableName)
    {
        static $caches = [];
        if (!isset($caches[$tableName])) {
            $sql = "select pg_attribute.attname as colname from 
                pg_constraint  inner join pg_class 
                on pg_constraint.conrelid = pg_class.oid 
                inner join pg_attribute on pg_attribute.attrelid = pg_class.oid 
                and  array[pg_attribute.attnum] <@ pg_constraint.conkey
                inner join pg_type on pg_type.oid = pg_attribute.atttypid
                where pg_class.relname = '{$tableName}' 
                and pg_constraint.contype='p'";

            $caches[$tableName] = yii::$app->db->createCommand($sql)->queryColumn();
        }
        return $caches[$tableName];
    }

    protected function getIDSequence($tableName, $primary) {
        static $caches = [];

        if (!isset($caches[$tableName])) {
            $sql = "SELECT column_default
                FROM information_schema.columns
                WHERE (table_schema, table_name) = ('public', '{$tableName}')
                and column_name='{$primary}'
                ORDER BY ordinal_position";
            $caches[$tableName] = yii::$app->db->createCommand($sql)->queryScalar();
        }
        return $caches[$tableName];
    }
}