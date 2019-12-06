<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2017 JTL-Software GmbH
 */

namespace Jtl\Connector\Client;

use JMS\Serializer\Serializer;
use Jtl\Connector\Core\Definition\RpcMethod;
use Jtl\Connector\Core\Model\Ack;
use Jtl\Connector\Core\Model\ConnectorIdentification;
use Jtl\Connector\Core\Model\Features;
use Jtl\Connector\Core\Serializer\SerializerBuilder;
use Jtl\Connector\Core\Model\AbstractDataModel;
use GuzzleHttp\Client as HttpClient;
use Jtl\Connector\Core\Utilities\Str;

class ConnectorClient
{
    const JTL_RPC_VERSION = "2.0";
    const DEFAULT_PULL_LIMIT = 100;

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
     * @var HttpClient
     */
    protected $httpClient;

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
     * @var string
     */
    protected $responseFormat = self::RESPONSE_FORMAT_OBJECT;

    /**
     * @var string[]
     */
    protected static $responseFormats = [
        self::RESPONSE_FORMAT_ARRAY, self::RESPONSE_FORMAT_JSON, self::RESPONSE_FORMAT_OBJECT
    ];

    /**
     * Client constructor.
     * @param string $token
     * @param string $endpointUrl
     * @param HttpClient $httpClient
     */
    public function __construct(string $token, string $endpointUrl, HttpClient $httpClient = null)
    {
        $this->token = $token;
        $this->endpointUrl = $endpointUrl;
        if ($httpClient === null) {
            $httpClient = new HttpClient();
        }

        $this->httpClient = $httpClient;
    }

    /**
     * @return void
     * @throws ResponseException
     */
    public function authenticate(): void
    {
        $this->sessionId = null;
        $params = ['token' => $this->token];
        $result = $this->request(RpcMethod::AUTH, $params, true);

        if (is_array($result)
            && isset($result['sessionId'])
            && !empty($result['sessionId'])) {
            $this->sessionId = $result['sessionId'];
        }
    }

    /**
     * @return boolean
     */
    public function isAuthenticated(): bool
    {
        if ($this->sessionId === null) {
            return false;
        }

        try {
            $this->request(RpcMethod::IDENTIFY, [], true);
        } catch (ResponseException $ex) {
            return false;
        }

        return true;
    }

    /**
     * @return FeaturesCollection
     * @throws ResponseException
     */
    public function features(): Features
    {
        $response = $this->request(RpcMethod::FEATURES);

        $entities = [];
        if (isset($response['entities']) && is_array($response['entities'])) {
            $entities = $response['entities'];
        }

        $flags = [];
        if (isset($response['flags']) && is_array($response['flags'])) {
            $flags = $response['flags'];
        }

        return Features::create($entities, $flags);
    }

    /**
     * @return boolean
     * @throws ResponseException
     */
    public function clear(): bool
    {
        return $this->request(RpcMethod::CLEAR);
    }

    /**
     * @return ConnectorIdentification
     * @throws ResponseException
     */
    public function identify(): ConnectorIdentification
    {
        $data = $this->request(RpcMethod::IDENTIFY);
        return $this->getSerializer()->fromArray($data, ConnectorIdentification::class);
    }

    /**
     * @return mixed[]
     * @throws ResponseException
     */
    public function finish()
    {
        return $this->request(RpcMethod::FINISH);
    }

    /**
     * @param string $controllerName
     * @param integer $limit
     * @return AbstractDataModel[]|mixed[]|string
     * @throws \RuntimeException
     * @throws ResponseException
     */
    public function pull(string $controllerName, int $limit = self::DEFAULT_PULL_LIMIT)
    {
        return $this->requestAndPrepare($controllerName, 'pull', ['limit' => $limit]);
    }

    /**
     * @param string $controllerName
     * @param mixed[] $entities
     * @return mixed[]
     * @throws ResponseException
     */
    public function push(string $controllerName, array $entities)
    {
        $data = $this->getSerializer()->toArray($entities);
        return $this->requestAndPrepare($controllerName, 'push', $data);
    }

    /**
     * @param string $controllerName
     * @param string $payload
     * @return mixed[]|object|object[]|string
     */
    public function rawPush(string $controllerName, string $payload)
    {
        $serialized = $payload;
        return $this->requestAndPrepare($controllerName, 'push', \json_decode($serialized, true));
    }

    /**
     * @param string $controllerName
     * @param mixed[] $entities
     * @return mixed[]
     * @throws ResponseException
     */
    public function delete(string $controllerName, array $entities)
    {
        $data = $this->getSerializer()->toArray($entities);
        return $this->requestAndPrepare($controllerName, 'delete', $data);
    }

    /**
     * @param Ack $ack
     * @return mixed[]
     * @throws ResponseException
     */
    public function ack(Ack $ack)
    {
        $data = $this->getSerializer()->toArray($ack);
        return $this->request(RpcMethod::ACK, $data);
    }

    /**
     * @param string $controllerName
     * @return integer
     * @throws ResponseException
     */
    public function statistic(string $controllerName): int
    {
        $method = $controllerName . '.statistic';
        $params['limit'] = 0;
        $response = $this->request($method, $params);

        if (!isset($response['available'])) {
            throw ResponseException::indexNotFound('available', $controllerName, 'statistic');
        }

        return (int)$response['available'];
    }

    /**
     * @param string $token
     * @return ConnectorClient
     */
    public function setToken(string $token): ConnectorClient
    {
        $this->token = $token;
        $this->sessionId = null;
        return $this;
    }

    /**
     * @return string
     */
    public function getResponseFormat(): string
    {
        return $this->responseFormat;
    }

    /**
     * @param string $format
     * @return ConnectorClient
     */
    public function setResponseFormat(string $format): ConnectorClient
    {
        if (!self::isResponseFormat($format)) {
            throw new RuntimeException(sprintf('%s is not a response format!', $format));
        }

        $this->responseFormat = $format;
        return $this;
    }

    /**
     * @param string $controllerName
     * @param string $action
     * @param array $params
     * @return string|mixed[]|object[]|object
     */
    protected function requestAndPrepare(string $controllerName, string $action, array $params = [])
    {
        $method = $controllerName . '.' . $action;
        $entitiesData = $this->request($method, $params);
        switch ($this->responseFormat) {
            case self::RESPONSE_FORMAT_OBJECT:
                $className = 'Jtl\\Connector\\Core\\Model\\' . Str::toPascalCase($controllerName);
                if (!is_subclass_of($className, AbstractDataModel::class)) {
                    throw new RuntimeException($className . ' does not inherit from ' . AbstractDataModel::class . '!');
                }

                $type = 'array<' . $className . '>';
                return $this->getSerializer()->fromArray($entitiesData, $type);
                break;
            case self::RESPONSE_FORMAT_JSON:
                return \json_encode($entitiesData);
                break;
        }
        return $entitiesData;
    }

    /**
     * @param string $method
     * @param mixed[] $params
     * @param boolean $authRequest
     * @return mixed[]
     * @throws ResponseException
     */
    protected function request(string $method, array $params = [], bool $authRequest = false)
    {
        if (!$authRequest && $this->sessionId === null) {
            $this->authenticate();
        }

        $requestId = uniqid();
        $requestBodyIndex = 'form_params';
        if (version_compare(HttpClient::VERSION, '6.0.0', '<')) {
            $requestBodyIndex = 'body';
        }
        $result = $this->httpClient->post($this->endpointUrl, [$requestBodyIndex => $this->createRequestParams($requestId, $method, $params)]);
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
     * @return string[]
     */
    protected function createRequestParams(string $requestId, string $method, array $params = []): array
    {
        $rpcData = [
            'method' => $method,
        ];

        if (count($params) > 0) {
            $rpcData['params'] = $params;
        }

        $rpcData['jtlrpc'] = self::JTL_RPC_VERSION;
        $rpcData['id'] = $requestId;

        $requestParams = ['jtlrpc' => \json_encode($rpcData)];
        if (!is_null($this->sessionId) && strlen($this->sessionId) > 0) {
            $requestParams['jtlauth'] = $this->sessionId;
        }

        return $requestParams;
    }

    /**
     * @return Serializer
     */
    protected function getSerializer(): Serializer
    {
        if (is_null($this->serializer)) {
            \Doctrine\Common\Annotations\AnnotationRegistry::registerLoader('class_exists');
            $this->serializer = SerializerBuilder::getInstance()->build();
        }
        return $this->serializer;
    }

    /**
     * @param string $format
     * @return bool
     */
    public static function isResponseFormat(string $format): bool
    {
        return in_array($format, self::$responseFormats);
    }
}
