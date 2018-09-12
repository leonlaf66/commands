<?php
namespace app\commands\lib;

use Exception;

class GraphQlClient extends \yii\base\Component
{
    public $baseUrl = '';
    public $appToken = null;
    public $language = 'en-US';
    public $defaultAreaId = 'ma';
    public $accessToken = '';

    protected $_client = null;

    public function init()
    {
        $this->_client = new \EUAutomation\GraphQL\Client($this->baseUrl);
    }

    public function request($gqlId, $variables = [], $headers = [], $defValue = null)
    {
        $headers = array_merge([
            'app-token' => $this->appToken,
            'language' => $this->language,
            'area-id' => $this->defaultAreaId,
            'access-token' => $this->accessToken
        ], $headers);

        $query = $this->getGraphqlQuery($gqlId);
        $response = null;
        try {
            $response = $this->_client->response($query, $variables, $headers);
        } catch(\Exception $e) {
            throw new Exception($e->getMessage(), 400, $e);
        }
        if ($response->hasErrors()) {
            $error = $response->errors()[0];
            throw new Exception( 'SERVICE-ERROR:'.$error->message, 401);
        }

        return $response->all();
    }

    private function getGraphqlQuery($gqlId)
    {
        return file_get_contents(dirname(__DIR__) . '/gql/' . $gqlId . '.gql');
    }
}