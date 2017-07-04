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

    const METHOD_ACK = 'core.connector.ack';
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
    protected $token;

    /**
     * @var string
     */
    protected $sessionId;

    /**
     * @var boolean
     */
    protected $authenticate = true;

    /**
     * Client constructor.
     * @param string $token
     * @param string $endpointUrl
     * @param \GuzzleHttp\Client $client
     */
    public function __construct($token, $endpointUrl, \GuzzleHttp\Client $client = null)
    {
        $this->token = $token;
        $this->endpointUrl = $endpointUrl;
        if($client === null) {
            $client = new \GuzzleHttp\Client();
        }

        $this->client = $client;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function authenticate()
    {
        $params = ['token' => $this->token];
        $this->authenticate = false;
        try {
            $result = $this->request(self::METHOD_AUTH, $params);
        } catch (\Exception $ex) {
            $this->authenticate = true;
            throw $ex;
        }
        $this->authenticate = true;

        if(is_array($result)
            && isset($result['sessionId'])
            && !empty($result['sessionId'])) {
            $this->sessionId = $result['sessionId'];
        }
        else {
            $this->sessionId = null;
        }
    }

    /**
     * @return boolean
     */
    public function isAuthenticated()
    {
        if($this->sessionId === null) {
            return false;
        }

        try {
            $this->authenticate = false;
            $this->features();
        } catch (Exception $ex) {
            $this->authenticate = true;
            return false;
        }
        $this->authenticate = true;
        return true;
    }

    /**
     * @return mixed[]
     */
    public function features()
    {
        return $this->request(self::METHOD_FEATURES);
    }

    /**
     * @return mixed[]
     */
    public function clear()
    {
        return $this->request(self::METHOD_CLEAR);
    }

    /**
     * @return mixed[]
     */
    public function identify()
    {
        return $this->request(self::METHOD_IDENTIFY);
    }

    /**
     * @param string $controllerName
     * @param integer $limit
     * @return mixed[]
     */
    public function pull($controllerName, $limit = self::DEFAULT_PULL_LIMIT)
    {
        $method = $controllerName . '.pull';
        $params['limit'] = $limit;
        return $this->request($method, $params);
    }

    /**
     * @param string $controllerName
     * @param mixed[] $entities
     * @return mixed[]
     */
    public function push($controllerName, array $entities)
    {
        $method = $controllerName . '.push';
        return $this->request($method, $entities);
    }


    /**
     * @param mixed[] $identities
     * @return mixed[]
     */
    public function ack(array $identities)
    {
        return $this->request(self::METHOD_ACK, $identities);
    }

    /**
     * @param string $controllerName
     * @return integer
     * @throws Exception
     */
    public function statistic($controllerName)
    {
        $method = $controllerName . '.statistic';
        $params['limit'] = 0;
        $response = $this->request($method, $params);

        if(!isset($response['available'])) {
            throw Exception::indexMissing('available', $controllerName, 'statistic');
        }

        return (int)$response['available'];
    }

    /**
     * @param string $token
     * @return Client
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @param string $method
     * @param mixed[]|null $params
     * @return mixed[]
     * @throws Exception
     */
    protected function request($method, array $params = null)
    {
        if($this->authenticate && ($this->sessionId === null || !$this->isAuthenticated())) {
            $this->authenticate();
        }

        $url = $this->endpointUrl;
        if($this->sessionId !== null && strlen($this->sessionId) > 0) {
            $url .= '?jtlauth=' . $this->sessionId;
        }

        $requestId = uniqid();
        $result = $this->client->post($url, ['body' => $this->createRequestBody($requestId, $method, $params)]);
        $content = $result->getBody()->getContents();
        $response = \json_decode($content, true);

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

        $requestBody = 'jtlrpc=' . \json_encode($data);
        return $requestBody;
    }
}