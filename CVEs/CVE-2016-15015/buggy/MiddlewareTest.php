<?php

namespace Barzahlen\Tests;

use Barzahlen\Middleware;

class MiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function testGenerateSignature()
    {
        $signature = Middleware::generateSignature(
            'callback.example.com:443',
            'POST',
            '/barzahlen/callback',
            '',
            'Fri, 01 Apr 2016 09:20:06 GMT',
            '',
            '{"foo":"bar"}',
            PAYMENTKEY
        );

        $this->assertEquals('35764655afcf2121602a5493b58020d3b6b9d75b4150c7395acf6114ae0ba49c', $signature);
    }
}
