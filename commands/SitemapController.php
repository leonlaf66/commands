<?php
namespace app\commands;

use libs\Sitemap;
use samdark\sitemap\SitemapIndex;
use yii\console\Controller;

defined('TRIGET_SITE_ROOT') || define('TRIGET_SITE_ROOT', dirname(__DIR__).'/../houses/www');

// https://packagist.org/packages/samdark/sitemap
class SitemapController extends Controller
{
    public function actionGenerate()
    {
        exec('rm '.TRIGET_SITE_ROOT.'/sitemap*.xml');

        $domain = \WS::$app->params['domain'];
        $xmlFiles = [];

        // 房源
        $houses = \common\estate\Sitemap::map(function ($rows, $idx) use ($domain, & $xmlFiles) {
            $sitemap = new Sitemap(TRIGET_SITE_ROOT.'/sitemap_houses'.($idx > $idx ? '_'.($idx + 1) : '').'.xml');
            $xmlFiles[] = 'sitemap_houses'.($idx > $idx ? '_'.($idx + 1) : '').'.xml';

            foreach ($rows as $row) {
                $url = 'http://ma'.$domain.'/'.($row['is_rental'] ? 'lease' : 'purchase').'/'.$row['id'].'/';
                $zhUrl = 'http://ma'.$domain.'/zh/'.($row['is_rental'] ? 'lease' : 'purchase').'/'.$row['id'].'/';
                $sitemap->addItem([$url, $zhUrl], strtotime($row['index_at']), Sitemap::DAILY, 1);
            }
            $sitemap->write();
        }, 40000);

        // 黄页
        $rows = \common\yellowpage\Sitemap::map();
        $sitemap = new Sitemap(TRIGET_SITE_ROOT.'/sitemap_yp.xml');
        $xmlFiles[] = 'sitemap_yp.xml';
        foreach ($rows as $row) {
            $url = 'http://ma'.$domain.'/pro-service/'.$row['id'].'/';
            $zhUrl = 'http://ma'.$domain.'/zh/pro-service/'.$row['id'].'/';
            $sitemap->addItem([$url, $zhUrl], null, Sitemap::MONTHLY, 0.8);
        }
        $sitemap->write();

        // 新闻
        $rows = \common\news\Sitemap::map();
        $sitemap = new Sitemap(TRIGET_SITE_ROOT.'/sitemap_news.xml');
        $xmlFiles[] = 'sitemap_news.xml';
        foreach ($rows as $row) {
            $url = 'http://ma'.$domain.'/news/'.$row['id'].'/';
            $sitemap->addItem($url, strtotime($row['updated_at']), Sitemap::DAILY, 0.9);
        }
        $sitemap->write();

        // 写入robots文件
        $robotsContent = file_get_contents(TRIGET_SITE_ROOT.'/robots.txt');
        
        if (preg_match_all('/Sitemap:.*/', $robotsContent, $matches)) {
            foreach ($matches[0] as $url) {
                $robotsContent = str_replace($url, '', $robotsContent);
            }
        }
        $robotsContent = trim($robotsContent);
        $robotsContent .= "\n";
        foreach($xmlFiles as $xmlFilename) {
            $robotsContent .= "Sitemap: http://ma{$domain}/{$xmlFilename}\n";
        }
        file_put_contents(TRIGET_SITE_ROOT.'/robots.txt', $robotsContent);
    }
}