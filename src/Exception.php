<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2017 JTL-Software GmbH
 */
namespace jtl\Connector;

class Exception extends \Exception
{
    const UNKNOWN_ERROR = 10;
    const INDEX_NOT_FOUND = 20;

    /**
     * @param string $message
     * @param integer $code
     * @return Exception
     */
    static public function responseError($message, $code)
    {
        return new self($message, $code);
    }

    /**
     * @param string $index
     * @param string $controller
     * @param string $method
     * @return Exception
     */
    static public function indexMissing($index, $controller, $method)
    {
        $msg = "Missing index '" . $index . "' in response for method '" . $method . "' in controller '" . $controller . "'!";
        return new self($msg, self::INDEX_NOT_FOUND);
    }
}