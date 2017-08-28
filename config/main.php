<?php
defined('APP_ROOT') OR define('APP_ROOT', dirname(dirname(__FILE__)));

Yii::setAlias('@mods', dirname(__DIR__) . '/app');
Yii::setAlias('@tests', dirname(__DIR__) . '/tests');

$fdnEtc = get_fdn_etc();
$domain = $fdnEtc['domain'];
unset($fdnEtc['domain']);
unset($fdnEtc['components']['session']);
unset($fdnEtc['components']['user']);

return \yii\helpers\ArrayHelper::merge($fdnEtc, [
    'id' => 'usleju-console',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'app\commands',
    'params' => [
        'domain' => $domain
    ]
]);
