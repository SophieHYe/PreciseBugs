<?php

namespace Barzahlen;

class Middleware
{
    /**
     * @param string $host
     * @param string $method
     * @param string $path
     * @param string $query
     * @param string $date
     * @param string $idempotency
     * @param string $body
     * @param string $key
     * @return string
     */
    public static function generateSignature($host, $method, $path, $query, $date, $idempotency, $body, $key)
    {
        $signatureData = array(
            $host,
            $method,
            $path,
            $query,
            $date,
            $idempotency,
            hash('sha256', $body)
        );
        $signatureString = implode("\n", $signatureData);

        return hash_hmac('sha256', $signatureString, $key);
    }

    /**
     * @param string $first
     * @param string $second
     * @return boolean
     *
     * Workaround for PHP < 5.6 by: asphp at dsgml dot com
     * Source: https://php.net/manual/en/function.hash-equals.php#115635
     */
    public static function stringsEqual($first, $second)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($first, $second);
        }

        if (strlen($first) != strlen($second)) {
            return false;
        }

        $res = $first ^ $second;
        $ret = 0;
        for ($i = strlen($res) - 1; $i >= 0; $i--) {
            $ret |= ord($res[$i]);
        }
        return !$ret;
    }
}
