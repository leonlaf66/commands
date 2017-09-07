<?php
namespace app\commands;

use WS;
use yii\console\Controller;
use common\estate\schoolDistrict\Summary as SchoolDistrictSummary;

/*该数据暂无用了, 以summary取代*/
class SchoolDistrictSummaryController extends Controller
{
    public function actionIndex($type = 'oneline')
    {
        SchoolDistrictSummary::flush(function ($row, $index, $total) {
            echo "summaried:{$index}/total:{$total}                   \r";
        });
    }
}