<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:36 下午.
 */

namespace HughCube\Laravel\DingTalk\Tests;

use Exception;
use HughCube\Laravel\DingTalk\Robot\Client as Robot;
use HughCube\Laravel\DingTalk\Robot\Messages\Text;
use InvalidArgumentException;

class RobotTest extends TestCase
{
    protected function getRobot(): Robot
    {
        return $this->makeRobot(['access_token' => 'test-token']);
    }

    /**
     * @return array
     */
    protected function parseQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    public function testAccessTokenOnly()
    {
        $robot = $this->makeRobot(['access_token' => 'token-abc']);

        $this->assertSame(
            'https://oapi.dingtalk.com/robot/send?access_token=token-abc',
            $robot->getRequestUrl()
        );
    }

    public function testFullUrlOnly()
    {
        $robot = $this->makeRobot(['url' => 'https://example.com/robot/send?access_token=token-url']);

        $this->assertSame(
            'https://example.com/robot/send?access_token=token-url',
            $robot->getRequestUrl()
        );
    }

    public function testUrlTakesPrecedenceOverAccessToken()
    {
        $robot = $this->makeRobot([
            'url'          => 'https://example.com/robot/send?access_token=token-url',
            'access_token' => 'token-config',
        ]);

        $query = $this->parseQuery($robot->getRequestUrl());

        $this->assertSame('token-url', $query['access_token']);
        $this->assertStringNotContainsString('token-config', $robot->getRequestUrl());
    }

    public function testUrlWithoutTokenFallsBackToAccessToken()
    {
        $robot = $this->makeRobot([
            'url'          => 'https://example.com/robot/send',
            'access_token' => 'token-config',
        ]);

        $this->assertSame(
            'https://example.com/robot/send?access_token=token-config',
            $robot->getRequestUrl()
        );
    }

    public function testUrlKeepsExtraQueryParameters()
    {
        $robot = $this->makeRobot([
            'url' => 'https://example.com/robot/send?access_token=token-url&foo=bar',
        ]);

        $query = $this->parseQuery($robot->getRequestUrl());

        $this->assertSame('token-url', $query['access_token']);
        $this->assertSame('bar', $query['foo']);
    }

    public function testUrlKeepsRawQueryEncoding()
    {
        $robot = $this->makeRobot([
            'url' => 'https://example.com/robot/send?access_token=token-url&foo=a%2Bb',
        ]);

        $this->assertStringContainsString('foo=a%2Bb', $robot->getRequestUrl());
    }

    public function testGivenSignatureKeptWithoutSecret()
    {
        $robot = $this->makeRobot([
            'url' => 'https://example.com/robot/send?access_token=token-url&timestamp=1&sign=old-sign',
        ]);

        $this->assertStringContainsString('timestamp=1&sign=old-sign', $robot->getRequestUrl());
    }

    public function testExpiredSignatureReplacedWhenSecretConfigured()
    {
        $robot = $this->makeRobot([
            'url'    => 'https://example.com/robot/send?access_token=token-url&timestamp=1&sign=old-sign',
            'secret' => 'secret-abc',
        ]);

        $url = $robot->getRequestUrl();
        $query = $this->parseQuery($url);

        $this->assertStringNotContainsString('old-sign', $url);
        $this->assertNotSame('1', $query['timestamp']);
        $this->assertSame(
            base64_encode(hash_hmac('sha256', $query['timestamp']."\n".'secret-abc', 'secret-abc', true)),
            $query['sign']
        );
    }

    public function testUrlIsTrimmed()
    {
        $robot = $this->makeRobot(['url' => "  https://example.com/robot/send?access_token=token-url\n"]);

        $this->assertSame(
            'https://example.com/robot/send?access_token=token-url',
            $robot->getRequestUrl()
        );
    }

    public function testUrlAndAccessTokenAreBothEmpty()
    {
        $robot = $this->makeRobot(['url' => '', 'access_token' => '']);

        $this->expectException(InvalidArgumentException::class);
        $robot->getRequestUrl();
    }

    public function testSignWithAccessToken()
    {
        $robot = $this->makeRobot(['access_token' => 'token-abc', 'secret' => 'secret-abc']);

        $query = $this->parseQuery($robot->getRequestUrl());

        $this->assertSame('token-abc', $query['access_token']);
        $this->assertArrayHasKey('timestamp', $query);
        $this->assertArrayHasKey('sign', $query);
        $this->assertSame(
            base64_encode(hash_hmac('sha256', $query['timestamp']."\n".'secret-abc', 'secret-abc', true)),
            $query['sign']
        );
    }

    public function testSignWithUrl()
    {
        $robot = $this->makeRobot([
            'url'    => 'https://example.com/robot/send?access_token=token-url',
            'secret' => 'secret-url',
        ]);

        $query = $this->parseQuery($robot->getRequestUrl());

        $this->assertSame('token-url', $query['access_token']);
        $this->assertSame(
            base64_encode(hash_hmac('sha256', $query['timestamp']."\n".'secret-url', 'secret-url', true)),
            $query['sign']
        );
    }

    public function testWithoutSecretNoSignature()
    {
        $robot = $this->makeRobot(['access_token' => 'token-abc']);

        $query = $this->parseQuery($robot->getRequestUrl());

        $this->assertArrayNotHasKey('timestamp', $query);
        $this->assertArrayNotHasKey('sign', $query);
    }

    public function testSendRequest()
    {
        $robot = $this->makeRobot(
            ['url' => 'https://example.com/robot/send?access_token=token-url', 'secret' => 'secret-url'],
            [$this->jsonResponse(['errcode' => 0, 'errmsg' => 'ok'])]
        );

        $results = $robot->send(new Text('hello'));

        $this->assertSame(0, $results['errcode']);
        $this->assertCount(1, $this->history);

        $request = $this->getLastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('example.com', $request->getUri()->getHost());
        $this->assertSame('/robot/send', $request->getUri()->getPath());

        $query = $this->parseQuery((string) $request->getUri());
        $this->assertSame('token-url', $query['access_token']);
        $this->assertArrayHasKey('timestamp', $query);
        $this->assertArrayHasKey('sign', $query);

        $this->assertSame(
            ['msgtype' => 'text', 'text' => ['content' => 'hello']],
            json_decode((string) $request->getBody(), true)
        );
        $this->assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function testSendDefaultUrl()
    {
        $robot = $this->makeRobot(['access_token' => 'token-abc']);

        $robot->send(new Text('hello'));

        $this->assertSame('oapi.dingtalk.com', $this->getLastRequest()->getUri()->getHost());
        $this->assertSame('access_token=token-abc', $this->getLastRequest()->getUri()->getQuery());
    }

    public function testSendThrowsWhenDingTalkReturnsError()
    {
        $robot = $this->makeRobot(
            ['access_token' => 'token-abc'],
            [$this->jsonResponse(['errcode' => 310000, 'errmsg' => 'keywords not in content'])]
        );

        try {
            $robot->send(new Text('hello'));
            $this->fail('An exception should have been thrown.');
        } catch (Exception $exception) {
            $this->assertSame('keywords not in content', $exception->getMessage());
            $this->assertSame(310000, $exception->getCode());
        }
    }

    public function testSendThrowsOnNegativeErrcode()
    {
        $robot = $this->makeRobot(
            ['access_token' => 'token-abc'],
            [$this->jsonResponse(['errcode' => -1, 'errmsg' => 'system error'])]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-1);
        $this->expectExceptionMessage('system error');

        $robot->send(new Text('hello'));
    }

    public function testSendThrowsOnErrorWithoutErrmsg()
    {
        $robot = $this->makeRobot(
            ['access_token' => 'token-abc'],
            [$this->jsonResponse(['errcode' => 300001])]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionCode(300001);

        $robot->send(new Text('hello'));
    }

    public function testSendThrowsOnInvalidJson()
    {
        $robot = $this->makeRobot(
            ['access_token' => 'token-abc'],
            [new \GuzzleHttp\Psr7\Response(200, [], '<html>gateway error</html>')]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Unknown dingtalk robot response/');

        $robot->send(new Text('hello'));
    }

    public function testSendThrowsWhenErrcodeMissing()
    {
        $robot = $this->makeRobot(
            ['access_token' => 'token-abc'],
            [$this->jsonResponse(['errmsg' => 'ok'])]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Unknown dingtalk robot response/');

        $robot->send(new Text('hello'));
    }

    public function testSendThrowsOnHttpError()
    {
        $robot = $this->makeRobot(
            ['access_token' => 'token-abc'],
            [new \GuzzleHttp\Psr7\Response(500, [], 'internal server error')]
        );

        $this->expectException(Exception::class);

        $robot->send(new Text('hello'));
    }

    public function testSendWithStringErrcode()
    {
        $robot = $this->makeRobot(
            ['access_token' => 'token-abc'],
            [$this->jsonResponse(['errcode' => '0', 'errmsg' => 'ok'])]
        );

        $results = $robot->send(new Text('hello'));

        $this->assertSame('0', $results['errcode']);
    }
}
