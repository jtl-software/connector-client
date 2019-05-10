<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2017 JTL-Software GmbH
 */
namespace Jtl\Connector\Client;

class ResponseException extends RuntimeException
{
    const UNKNOWN_ERROR = 10;
    const INDEX_NOT_FOUND = 20;
    const AUTHENTICATION_FAILED = 790;
    const SESSION_INVALID = -32000;

    /**
     * @param string $message
     * @param integer $code
     * @return ResponseException
     */
    public static function responseError($message, $code)
    {
        return new self($message, $code);
    }

    /**
     * @param string $index
     * @param string $controller
     * @param string $method
     * @return ResponseException
     */
    public static function indexNotFound($index, $controller, $method)
    {
        $msg = "Missing index '" . $index . "' in response for method '" . $method . "' in controller '" . $controller . "'!";
        return new self($msg, self::INDEX_NOT_FOUND);
    }

    /**
     * @return ResponseException
     */
    public static function unknownError()
    {
        return new self('Unknown error occured while fetching response!', self::UNKNOWN_ERROR);
    }
}