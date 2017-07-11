<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2017 JTL-Software GmbH
 */
namespace jtl\Connector\Client;

use JMS\Serializer\Serializer;
use jtl\Connector\Model\Ack;
use jtl\Connector\Model\DataModel;
use jtl\Connector\Serializer\JMS\SerializerBuilder;

class Client
{
    const JTL_RPC_VERSION = "2.0";
    const DEFAULT_PULL_LIMIT = 100;

    const METHOD_ACK = 'core.connector.ack';
    const METHOD_AUTH = 'core.connector.auth';
    const METHOD_FEATURES = 'core.connector.features';
    const METHOD_IDENTIFY = 'connector.identify';
    const METHOD_CLEAR = 'core.linker.clear';

    const DATA_FORMAT_JSON = 'json';
    const DATA_FORMAT_ARRAY = 'array';
    const DATA_FORMAT_OBJECT = 'object';

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
    protected $authenticationRequest = false;

    /**
     * @var Serializer
     */
    protected $serializer;

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
        $this->serializer = SerializerBuilder::create();
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function authenticate()
    {
        $params = ['token' => $this->token];
        $this->authenticationRequest = true;
        try {
            $result = $this->request(self::METHOD_AUTH, $params);
        } catch (\Exception $ex) {
            $this->authenticationRequest = false;
            throw $ex;
        }
        $this->authenticationRequest = false;

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
            $this->authenticationRequest = true;
            $this->features();
        } catch (ResponseException $ex) {
            $this->authenticationRequest = false;
            return false;
        }
        $this->authenticationRequest = false;
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
     * @return boolean
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
     * @param string $responseFormat
     * @return DataModel[]
     * @throws \Exception
     */
    public function pull($controllerName, $limit = self::DEFAULT_PULL_LIMIT, $responseFormat = self::DATA_FORMAT_OBJECT)
    {
        $method = $controllerName . '.pull';
        $params['limit'] = $limit;
        $entitiesData = $this->request($method, $params);

        $className = 'jtl\\Connector\\Model\\' . $this->underscoreToCamelCase($controllerName);
        if(!is_subclass_of($className, \jtl\Connector\Model\DataModel::class)){
            throw new \Exception($className . ' does not inherit from ' . \jtl\Connector\Model\DataModel::class . '!');
        }

        switch($responseFormat){
            case self::DATA_FORMAT_OBJECT:
                $ns = 'ArrayCollection<' . $className . '>';
                return $this->serializer->deserialize(\json_encode($entitiesData), $ns, 'json');
                break;
            case self::DATA_FORMAT_JSON:
                return \json_encode($entitiesData);
                break;
        }

        return $entitiesData;
    }

    /**
     * @param string $controllerName
     * @param mixed[] $entities
     * @return mixed[]
     */
    public function push($controllerName, array $entities)
    {
        $method = $controllerName . '.push';
        $serialized = $this->serializer->serialize($entities, 'json');
        return $this->request($method, \json_decode($serialized, true));
    }

    /**
     * @param Ack $ack
     * @return mixed[]
     */
    public function ack(Ack $ack)
    {
        $serialized = $this->serializer->serialize($ack, 'json');
        return $this->request(self::METHOD_ACK, \json_decode($serialized, true));
    }

    /**
     * @param string $controllerName
     * @return integer
     * @throws ResponseException
     */
    public function statistic($controllerName)
    {
        $method = $controllerName . '.statistic';
        $params['limit'] = 0;
        $response = $this->request($method, $params);

        if(!isset($response['available'])) {
            throw ResponseException::indexNotFound('available', $controllerName, 'statistic');
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
     * @param mixed[] $params
     * @return mixed[]
     * @throws ResponseException
     */
    protected function request($method, array $params = [])
    {
        if(!$this->authenticationRequest && $this->sessionId === null) {
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

        if (isset($response['error']) && is_array($response['error']) && !empty($response['error'])) {
            $error = $response['error'];
            $message = isset($error['message']) ? $error['message'] : 'Unknown Error while fetching connector response';
            $code = isset($error['code']) ? (int)$error['code'] : ResponseException::UNKNOWN_ERROR;
            if (!$this->authenticationRequest && $code === ResponseException::SESSION_INVALID) {
                $this->authenticate();
                return $this->request($method, $params);
            }
            throw ResponseException::responseError($message, $code);
        }

        if (isset($response['result'])) {
            return $response['result'];
        }

        throw ResponseException::unknownError();
    }

    /**
     * @param string $requestId
     * @param string $method
     * @param mixed[] $params
     * @return string
     */
    protected function createRequestBody($requestId, $method, array $params = [])
    {
        $data = [
          'method' => $method,
        ];

        if(count($params) > 0) {
            $data['params'] = $params;
        }

        $data['jtlrpc'] = self::JTL_RPC_VERSION;
        $data['id'] = $requestId;

        $requestBody = 'jtlrpc=' . \json_encode($data);
        return $requestBody;
    }

    /**
     * @param string $string
     * @return string
     */
    protected function underscoreToCamelCase($string)
    {
        $camelCase = '';
        foreach(explode('_', $string) as $part) {
            $camelCase .= ucfirst($part);
        }
        return $camelCase;
    }
}