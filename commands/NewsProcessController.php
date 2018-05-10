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
            ->update('news', ['status' => 2], $where)
            ->execute();

        $content = $db->createCommand('select content_raw from news where '.$where)->queryScalar();

        // 替换内容中的图片
        if (preg_match_all('/<img.*?src="(.*?)".*?>/is', $content, $matchs)) {
            foreach ($matchs[1] as $imageUrl) {
                $newImageUrl = $this->convertToLocalImageUrl($imageUrl);
                var_dump($newImageUrl);
                $content = str_replace($imageUrl, $newImageUrl, $content);
            }
        }

        $db->createCommand()
            ->update('news', ['content' => $content, 'status' => 1], $where)
            ->execute();
    }

    public function actionAll()
    {
        $db = WS::$app->db;
        $ids = $db->createCommand('select id from news')->queryColumn();

        $total = count($ids);
        foreach ($ids as $idx => $id) {
            $this->actionIndex($id);

            $index = $idx + 1;
            // echo "{$index}/$total           \r";
        }
    }

    protected function convertToLocalImageUrl($remoteUrl)
    {
        $hashId = md5($remoteUrl);
        $localFileDir = sprintf('%s/%s/%s',
            substr($hashId, 0, 1),
            substr($hashId, 1, 1),
            substr($hashId, 2, 2)
        );

        $rootDir = \WS::$app->params['media']['root'].'/news/img';
        $fileDir = $rootDir.'/'.$localFileDir;

        if (! is_dir($fileDir)) {
            mkdir($fileDir, 0777, true);
            chmod($fileDir, 0777);
        }

        $localFile = $fileDir.'/'.substr($hashId, 4).'.jpg';

        /*已经有本地缓存*/
        if (file_exists($localFile)) {
            return '//'.$localFileDir.'/'.substr($hashId, 4).'.jpg';
            // return $localFile;
        }

        $blob = $this->fetchRemoteImage($remoteUrl);

        $fp = fopen($localFile, 'wb');
        $imgLen = strlen($blob);
        $_inx = 1024;
        $_time = ceil($imgLen / $_inx);
        for($i = 0; $i < $_time; $i ++){
            fwrite($fp,substr($blob, $i * $_inx, $_inx));
        }
        fclose($fp);
        return '//'.$localFileDir.'/'.substr($hashId, 4).'.jpg';
    }

    protected function fetchRemoteImage($url)
    {
        ob_start();
        @ readfile($url);
        $blob = ob_get_contents();
        ob_end_clean();

        return $blob;
    }
}
