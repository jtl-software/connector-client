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
     * @var string
     */
    protected $responseFormat = self::RESPONSE_FORMAT_JSON;

    /**
     * @var string[]
     */
    protected $validResponseFormats = [
        self::RESPONSE_FORMAT_ARRAY,
        self::RESPONSE_FORMAT_OBJECT,
        self::RESPONSE_FORMAT_JSON,
    ];

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
     * @param $token
     * @return mixed[]
     */
    public function authenticate($token)
    {
        $params = ['token' => $token];
        return $this->request(self::METHOD_AUTH, null, $params);
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
     * @return mixed[]
     */
    public function statistic($sessionId, $controllerName)
    {
        $method = $controllerName . '.statistic';
        $params['limit'] = 0;
        return $this->request($method, $sessionId, $params);
    }

    /**
     * @param string $responseFormat
     * @throws \Exception
     */
    public function setResponseFormat($responseFormat)
    {
        if(!in_array($responseFormat, $this->validResponseFormats)) {
            throw new \Exception($responseFormat . ' is not a valid response Format!');
        }
        $this->responseFormat = $responseFormat;
    }

    /**
     * @param string $method
     * @param null|string $sessionId
     * @param mixed[] $params
     * @return mixed[]
     */
    protected function request($method, $sessionId = null, array $params = null)
    {
        $url = $this->endpointUrl;
        if($sessionId !== null && strlen($sessionId) > 0) {
            $url .= '?jtlauth=' . $sessionId;
        }
        $requestId = uniqid();
        $result = $this->client->post($url, ['body' => $this->createRequestBody($requestId, $method, $params)]);
        $response = $result->getBody()->getContents();

        switch ($this->responseFormat) {
            case self::RESPONSE_FORMAT_ARRAY:
                return \json_decode($response, true);
                break;
            case self::RESPONSE_FORMAT_OBJECT:
                return \json_decode($response);
                break;
        }

        return $response;
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