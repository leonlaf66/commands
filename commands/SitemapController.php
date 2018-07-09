<?php
namespace app\commands;

use samdark\sitemap\Sitemap;
use samdark\sitemap\Index as SitemapIndex;
use yii\console\Controller;

defined('TRIGET_SITE_ROOT') || define('TRIGET_SITE_ROOT', dirname(__DIR__).'/../houses/www');

// https://packagist.org/packages/samdark/sitemap
class SitemapController extends Controller
{
    public function actionGenerate()
    {
        $domain = \WS::$app->params['domain'];
        $sitemapIndex = new SitemapIndex(TRIGET_SITE_ROOT . '/sitemap_index.xml');

        $areaIds = ['ma', 'ny', 'ca', 'ga', 'il'];

        // 房源
        foreach($areaIds as $areaId) {
            $sitemap = new Sitemap(TRIGET_SITE_ROOT."/sitemaps/es-{$areaId}.xml.gz");
            $sitemap->setMaxUrls(3000);
            $sitemap->setUseGzip(true);
            $houses = \common\estate\Sitemap::map($areaId, function ($rows, $idx) use ($domain, $sitemap, $areaId) {
                foreach ($rows as $row) {
                    $path = ($row['prop_type'] === 'RN' ? 'lease' : 'purchase').'/'.$row['list_no'].'/';

                    $url = 'http://'.$areaId.$domain.'/zh/'.$path;
                    $sitemap->addItem($url, strtotime($row['index_at']), Sitemap::MONTHLY, 1);
                }
            }, 2000);

            $sitemap->write();
            $sitemapUrls = $sitemap->getSitemapUrls('http://'.$areaId.$domain.'/sitemaps/');
            foreach ($sitemapUrls as $url) {
                $sitemapIndex->addSitemap($url);
            }
        }

        // 学区(仅ma)
        $xmlFile = 'sitemaps/sd-ma.xml';
        $sitemap = new Sitemap(TRIGET_SITE_ROOT.'/'.$xmlFile);

        $xqids = \WS::$app->db->createCommand('select id from schooldistrict')
            ->queryColumn('id');

        foreach ($xqids as $xqid) {
            $url = 'http://ma'.$domain.'/zh/school-district/'.$xqid.'/';
            $sitemap->addItem($url, null, Sitemap::MONTHLY, 1);
        }

        if (count($xqids) > 0) {
            $sitemap->write();
            $sitemapIndex->addSitemap('http://ma'.$domain.'/'.$xmlFile);
        }

        // 黄页
        foreach($areaIds as $areaId) {
            $xmlFile = "sitemaps/ps-{$areaId}.xml";
            $sitemap = new Sitemap(TRIGET_SITE_ROOT.'/'.$xmlFile);

            $rows = \common\yellowpage\Sitemap::map($areaId);
            foreach ($rows as $row) {
                $url = 'http://'.$areaId.$domain.'/zh/pro-service/'.$row['id'].'/';
                $sitemap->addItem($url, null, Sitemap::MONTHLY, 0.8);
            }

            if (count($rows) > 0) {
                $sitemap->write();
                $sitemapIndex->addSitemap('http://'.$areaId.$domain.'/'.$xmlFile);
            }
        }

        // 新闻
        foreach($areaIds as $areaId) {
            $xmlFile = "sitemaps/news-{$areaId}.xml";
            $sitemap = new Sitemap(TRIGET_SITE_ROOT.'/'.$xmlFile);

            $rows = \common\news\Sitemap::map($areaId);
            foreach ($rows as $row) {
                $url = 'http://'.$areaId.$domain.'/zh/news/'.$row['id'].'/';
                $sitemap->addItem($url, strtotime($row['updated_at']), Sitemap::MONTHLY, 0.9);
            }

            if (count($rows) > 0) {
                $sitemap->write();
                $sitemapIndex->addSitemap('http://'.$areaId.$domain.'/'.$xmlFile);
            }
        }

        // ending
        $sitemapIndex->write();

        /*log*/
        file_put_contents(__DIR__.'/../log.log', date('Y-m-d H:i:s').' sitemap xml'."\n", FILE_APPEND);
    }
}