<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2017 JTL-Software GmbH
 */
namespace jtl\Connector\Client;

class ResponseException extends Exception
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
    static public function responseError($message, $code)
    {
        return new self($message, $code);
    }

    /**
     * @param string $index
     * @param string $controller
     * @param string $method
     * @return ResponseException
     */
    static public function indexNotFound($index, $controller, $method)
    {
        $msg = "Missing index '" . $index . "' in response for method '" . $method . "' in controller '" . $controller . "'!";
        return new self($msg, self::INDEX_NOT_FOUND);
    }

    /**
     * @return ResponseException
     */
    static public function unknownError()
    {
        return new self('Unknown error occured while fetching response!', self::UNKNOWN_ERROR);
    }
}