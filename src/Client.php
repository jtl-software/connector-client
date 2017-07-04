<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2017 JTL-Software GmbH
 */
namespace jtl\Connector;

class Client
{
    const JTLRPC_VERSION = 2.0;
    const DEFAULT_PULL_LIMIT = 100;

    const METHOD_AUTH = 'core.connector.auth';
    const METHOD_FEATURES = 'core.connector.features';
    const METHOD_IDENTIFY = 'connector.identify';
    const METHOD_CLEAR = 'core.linker.clear';

    const RESPONSE_FORMAT_JSON = 'json';
    const RESPONSE_FORMAT_ARRAY = 'array';
    const RESPONSE_FORMAT_OBJECT = 'object';

    /**
     * The Connector endpoint url
     *
     * @var string
     */
    protected $endpointUrl;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Client constructor.
     * @param string $endpointUrl
     * @param \GuzzleHttp\Client $client
     */
    public function __construct($endpointUrl, \GuzzleHttp\Client $client = null)
    {
        $this->endpointUrl = $endpointUrl;
        if($client === null) {
            $client = new \GuzzleHttp\Client();
        }

        $this->client = $client;
    }

    /**
     * @param string $token
     * @return string|null
     */
    public function authenticate($token)
    {
        $params = ['token' => $token];
        $result = $this->request(self::METHOD_AUTH, null, $params);

        if(is_array($result)
            && isset($result['sessionId'])
            && !empty($result['sessionId'])) {
            return $result;
        }

        return null;
    }

    /**
     * @param string $sessionId
     * @return boolean
     */
    public function isAuthenticated($sessionId)
    {
        try {
            $this->features($sessionId);
        } catch (Exception $ex) {
            return false;
        }
        return true;
    }

    /**
     * @param string $sessionId
     * @return mixed[]
     */
    public function features($sessionId)
    {
        return $this->request(self::METHOD_FEATURES, $sessionId);
    }

    /**
     * @param string $sessionId
     * @return mixed[]
     */
    public function clear($sessionId)
    {
        return $this->request(self::METHOD_CLEAR, $sessionId);
    }

    /**
     * @param string $sessionId
     * @return mixed[]
     */
    public function identify($sessionId)
    {
        return $this->request(self::METHOD_IDENTIFY, $sessionId);
    }

    /**
     * @param string $sessionId
     * @param string $controllerName
     * @param integer $limit
     * @return mixed[]
     */
    public function pull($sessionId, $controllerName, $limit = self::DEFAULT_PULL_LIMIT)
    {
        $method = $controllerName . '.pull';
        $params['limit'] = $limit;
        return $this->request($method, $sessionId, $params);
    }

    /**
     * @param string $sessionId
     * @param string $controllerName
     * @param mixed[] $data
     * @return mixed[]
     */
    public function push($sessionId, $controllerName, array $data)
    {
        $method = $controllerName . '.push';
        return $this->request($method, $sessionId, $data);
    }

    /**
     * @param string $sessionId
     * @param string $controllerName
     * @return integer
     * @throws Exception
     */
    public function statistic($sessionId, $controllerName)
    {
        $method = $controllerName . '.statistic';
        $params['limit'] = 0;
        $response = $this->request($method, $sessionId, $params);

        if(!isset($response['available'])) {
            throw Exception::indexMissing('available', $controllerName, 'statistic');
        }

        return (int)$response['available'];
    }

    /**
     * @param string $method
     * @param string|null $sessionId
     * @param mixed[]|null $params
     * @return mixed[]
     * @throws Exception
     */
    protected function request($method, $sessionId = null, array $params = null)
    {
        $url = $this->endpointUrl;
        if($sessionId !== null && strlen($sessionId) > 0) {
            $url .= '?jtlauth=' . $sessionId;
        }
        $requestId = uniqid();
        $result = $this->client->post($url, ['body' => $this->createRequestBody($requestId, $method, $params)]);
        $response = \json_decode($result->getBody()->getContents(), true);

        if(is_array($response['error']) && !empty($response['error'])) {
            $error = $response['error'];
            $message = isset($error['message']) ? $error['message'] : 'Unknown Error while fetching connector response';
            $code = isset($error['code']) ? $error['code'] : Exception::UNKNOWN_ERROR;
            throw Exception::responseError($message, $code);
        }

        if (is_array($response['result'])) {
            return $response['result'];
        }

        return null;
    }

    /**
     * @param string $requestId
     * @param string $method
     * @param mixed[] $params
     * @return string
     */
    protected function createRequestBody($requestId, $method, array $params = null)
    {
        $data = [
          'method' => $method,
        ];

        if(count($params) > 0) {
            $data['params'] = $params;
        }

        $data['jtlrpc'] = self::JTLRPC_VERSION;
        $data['id'] = $requestId;

        return 'jtlrpc=' . \json_encode($data);
    }
}