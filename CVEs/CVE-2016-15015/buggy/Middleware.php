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
}
