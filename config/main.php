<?php
defined('APP_ROOT') OR define('APP_ROOT', dirname(dirname(__FILE__)));

Yii::setAlias('@mods', dirname(__DIR__) . '/app');
Yii::setAlias('@tests', dirname(__DIR__) . '/tests');

$fdnEtc = get_fdn_etc();

return \yii\helpers\ArrayHelper::merge(get_fdn_etc(), [
    'id' => 'usleju-console',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'app\commands'
], include(__DIR__.'/local.php'));
