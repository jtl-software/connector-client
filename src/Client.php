<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2017 JTL-Software GmbH
 */
namespace jtl\Connector\Client;

use JMS\Serializer\Serializer;
use jtl\Connector\Client\Features\FeaturesCollection;
use jtl\Connector\Model\Ack;
use jtl\Connector\Model\ConnectorIdentification;
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
    const METHOD_FINISH = 'connector.finish';
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
     * @throws ResponseException
     */
    public function authenticate()
    {
        $this->sessionId = null;
        $params = ['token' => $this->token];
        $result = $this->request(self::METHOD_AUTH, $params, true);

        if(is_array($result)
            && isset($result['sessionId'])
            && !empty($result['sessionId'])) {
            $this->sessionId = $result['sessionId'];
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
            $this->request(self::METHOD_IDENTIFY, [], true);
        } catch (ResponseException $ex) {
            return false;
        }

        return true;
    }

    /**
     * @return FeaturesCollection
     * @throws ResponseException
     */
    public function features()
    {
        $response = $this->request(self::METHOD_FEATURES);

        $entities = [];
        if(isset($response['entities']) && is_array($response['entities'])) {
            $entities = $response['entities'];
        }

        $flags = [];
        if(isset($response['flags']) && is_array($response['flags'])) {
            $flags = $response['flags'];
        }

        return FeaturesCollection::create($entities, $flags);
    }

    /**
     * @return boolean
     * @throws ResponseException
     */
    public function clear()
    {
        return $this->request(self::METHOD_CLEAR);
    }

    /**
     * @return ConnectorIdentification
     * @throws ResponseException
     */
    public function identify()
    {
        $json =  \json_encode($this->request(self::METHOD_IDENTIFY));
        $ns = ConnectorIdentification::class;
        return $this->serializer->deserialize($json, $ns, 'json');
    }

    /**
     * @return mixed[]
     * @throws ResponseException
     */
    public function finish()
    {
        return $this->request(self::METHOD_FINISH);
    }

    /**
     * @param string $controllerName
     * @param integer $limit
     * @param string $responseFormat
     * @return DataModel[]
     * @throws \RuntimeException
     * @throws ResponseException
     */
    public function pull($controllerName, $limit = self::DEFAULT_PULL_LIMIT, $responseFormat = self::DATA_FORMAT_OBJECT)
    {
        $method = $controllerName . '.pull';
        $params['limit'] = $limit;
        $entitiesData = $this->request($method, $params);

        $className = 'jtl\\Connector\\Model\\' . $this->underscoreToCamelCase($controllerName);
        if(!is_subclass_of($className, \jtl\Connector\Model\DataModel::class)){
            throw new \RuntimeException($className . ' does not inherit from ' . \jtl\Connector\Model\DataModel::class . '!');
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
     * @throws ResponseException
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
     * @throws ResponseException
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
        $this->sessionId = null;
        return $this;
    }

    /**
     * @param string $method
     * @param mixed[] $params
     * @param boolean $authRequest
     * @return mixed[]
     * @throws ResponseException
     */
    protected function request($method, array $params = [], $authRequest = false)
    {
        if(!$authRequest && $this->sessionId === null) {
            $this->authenticate();
        }

        $requestId = uniqid();
        $result = $this->client->post($this->endpointUrl, ['form_params' => $this->createRequestParams($requestId, $method, $params)]);
        $content = $result->getBody()->getContents();
        $response = \json_decode($content, true);

        if (isset($response['error']) && is_array($response['error']) && !empty($response['error'])) {
            $error = $response['error'];
            $message = isset($error['message']) ? $error['message'] : 'Unknown Error while fetching connector response';
            $code = isset($error['code']) ? (int)$error['code'] : ResponseException::UNKNOWN_ERROR;
            if (!$authRequest && $code === ResponseException::SESSION_INVALID) {
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
    protected function createRequestParams($requestId, $method, array $params = [])
    {
        $rpcData = [
          'method' => $method,
        ];

        if(count($params) > 0) {
            $rpcData['params'] = $params;
        }

        $rpcData['jtlrpc'] = self::JTL_RPC_VERSION;
        $rpcData['id'] = $requestId;

        $requestParams = ['jtlrpc' => \json_encode($rpcData)];
        if(!is_null($this->sessionId) && strlen($this->sessionId) > 0) {
            $requestParams['jtlauth'] = $this->sessionId;
        }

        return $requestParams;
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