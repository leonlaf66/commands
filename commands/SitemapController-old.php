<?php
namespace app\commands;

use samdark\sitemap\Sitemap;
use samdark\sitemap\Index as SitemapIndex;
use yii\console\Controller;

defined('TRIGET_SITE_ROOT') || define('TRIGET_SITE_ROOT', dirname(__DIR__).'/../houses/www');

// https://packagist.org/packages/samdark/sitemap
class SitemapController extends Controller
{
    public function actionGenerate($type = 'g')
    {
        $domain = \WS::$app->params['domain'];
        $sitemapIndex = new SitemapIndex(TRIGET_SITE_ROOT . '/sitemap_index'.($type === 'g' ? '' : '_'.$type).'.xml');
        $areaIds = ['ma', 'ny', 'ca', 'ga', 'il'];

        // 房源
        foreach($areaIds as $areaId) {
            $houses = \common\estate\Sitemap::map($areaId, function ($rows, $idx) use ($domain, $sitemapIndex, $type, $areaId) {
                $xmlFile = 'sitemap_houses_'.$areaId.($type === 'g' ? '' : '_'.$type).($idx > 0 ? '_'.($idx + 1) : '').'.xml';
                $sitemap = new Sitemap(TRIGET_SITE_ROOT.'/'.$xmlFile, $type === 'g');

                foreach ($rows as $row) {
                    $path = ($row['prop_type'] === 'RN' ? 'lease' : 'purchase').'/'.$row['list_no'].'/';

                    if ($type === 'g') {
                        $url = 'http://'.$areaId.$domain.'/'.$path;
                        $zhUrl = 'http://'.$areaId.$domain.'/zh/'.$path;

                        $sitemap->addItem(['en'=>$url, 'zh'=>$zhUrl], strtotime($row['index_at']), Sitemap::DAILY, 1);
                    } else {
                        $url = 'http://'.$areaId.$domain.'/zh/'.$path;

                        $sitemap->addItem($url, strtotime($row['index_at']), Sitemap::DAILY, 1);
                    }
                }

                $sitemap->write();

                $sitemapIndex->addSitemap('http://'.$areaId.$domain.'/'.$xmlFile);
            }, ($type === 'g' ? 20000 : 40000));
        }

        /*学区(仅ma)*/
        $xmlFile = 'sitemap_yp_ma_xq'.($type === 'g' ? '' : '_'.$type).'.xml';
        $sitemap = new Sitemap(TRIGET_SITE_ROOT.'/'.$xmlFile, $type === 'g');
        $xqids = \WS::$app->db->createCommand('select id from schooldistrict')
            ->queryColumn('id');
        foreach ($xqids as $xqid) {
            if ($type === 'g') {
                $url = 'http://ma'.$domain.'/school-district/'.$xqid.'/';
                $zhUrl = 'http://ma'.$domain.'/zh/school-district/'.$xqid.'/';
                $sitemap->addItem(['en'=>$url, 'zh'=>$zhUrl], null, Sitemap::MONTHLY, 0.8);
            } else {
                $url = 'http://ma'.$domain.'/school-district/zh/'.$xqid.'/';
                $sitemap->addItem($url, null, Sitemap::MONTHLY, 0.8);
            }
        }

        if (count($xqids) > 0) {
            $sitemap->write();
            $sitemapIndex->addSitemap('http://ma'.$domain.'/'.$xmlFile);
        }

        // 黄页
        foreach($areaIds as $areaId) {
            $xmlFile = 'sitemap_yp_'.$areaId.($type === 'g' ? '' : '_'.$type).'.xml';

            $rows = \common\yellowpage\Sitemap::map($areaId);
            $sitemap = new Sitemap(TRIGET_SITE_ROOT.'/'.$xmlFile, $type === 'g');
            foreach ($rows as $row) {
                if ($type === 'g') {
                    $url = 'http://'.$areaId.$domain.'/pro-service/'.$row['id'].'/';
                    $zhUrl = 'http://'.$areaId.$domain.'/zh/pro-service/'.$row['id'].'/';
                    $sitemap->addItem(['en'=>$url, 'zh'=>$zhUrl], null, Sitemap::MONTHLY, 0.8);
                } else {
                    $url = 'http://'.$areaId.$domain.'/zh/pro-service/'.$row['id'].'/';
                    $sitemap->addItem($url, null, Sitemap::MONTHLY, 0.8);
                }
            }

            if (count($rows) > 0) {
                $sitemap->write();
                $sitemapIndex->addSitemap('http://'.$areaId.$domain.'/'.$xmlFile);
            }
        }

        // 新闻
        foreach($areaIds as $areaId) {
            $xmlFile = 'sitemap_news_'.$areaId.($type === 'g' ? '' : '_'.$type).'.xml';

            $rows = \common\news\Sitemap::map($areaId, $type === 'g');
            $sitemap = new Sitemap(TRIGET_SITE_ROOT.'/'.$xmlFile);

            foreach ($rows as $row) {
                $url = 'http://'.$areaId.$domain.'/zh/news/'.$row['id'].'/';
                $sitemap->addItem($url, strtotime($row['updated_at']), Sitemap::DAILY, 0.9);
            }

            if (count($rows) > 0) {
                $sitemap->write();
                $sitemapIndex->addSitemap('http://'.$areaId.$domain.'/'.$xmlFile);
            }
        }

        $sitemapIndex->write();

        /*log*/
        file_put_contents(__DIR__.'/../log.log', date('Y-m-d H:i:s').' sitemap xml'."\n", FILE_APPEND);
    }

    public function actionGenerateBaidu($type = 'b')
    {
        return $this->actionGenerate($type);
    }
}