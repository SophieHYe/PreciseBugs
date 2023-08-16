<?php

namespace Barzahlen;

class Webhook
{
    /**
     * @var string
     */
    private $paymentKey;


    /**
     * @param string $paymentKey
     */
    public function __construct($paymentKey)
    {
        $this->paymentKey = $paymentKey;
    }

    /**
     * @param array $header
     * @param string $body
     * @return boolean
     */
    public function verify($header, $body)
    {
        $signature = Middleware::generateSignature(
            $header['HTTP_HOST'] . ':' . $header['SERVER_PORT'],
            $header['REQUEST_METHOD'],
            $header['SCRIPT_NAME'],
            $header['QUERY_STRING'],
            $header['HTTP_DATE'],
            '',
            $body,
            $this->paymentKey
        );

        return Middleware::stringsEqual($header['HTTP_BZ_SIGNATURE'], 'BZ1-HMAC-SHA256 ' . $signature);
    }
}