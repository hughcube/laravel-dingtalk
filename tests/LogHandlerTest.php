<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/11/5
 * Time: 21:40.
 */

namespace HughCube\Laravel\DingTalk\Tests;

use HughCube\Laravel\DingTalk\Log\Handler;
use Monolog\Logger;

class LogHandlerTest extends TestCase
{
    /**
     * @param array $config
     * @param array $queue
     * @param bool  $enabled
     *
     * @return Logger
     */
    protected function makeLogger(array $config, array $queue, bool $enabled = true): Logger
    {
        $config['url'] = $config['url'] ?? 'https://example.com/robot/send?access_token=log-token';
        $config['http'] = ['handler' => $this->mockHandler($queue)];
        $this->app['config']->set('dingTalk.robots.default', $config);

        return new Logger('test', [new Handler(null, $enabled, Logger::DEBUG)]);
    }

    /**
     * @return array
     */
    protected function getSentMessage(): array
    {
        $requests = array_values($this->history);
        $this->assertCount(1, $requests);

        return json_decode((string) $requests[0]['request']->getBody(), true);
    }

    public function testDisabledDoesNotSend()
    {
        $logger = $this->makeLogger([], [], false);

        $logger->warning('boom');

        $this->assertCount(0, $this->history);
    }

    public function testBelowLevelDoesNotSend()
    {
        $logger = $this->makeLogger([], [], true);

        $logger->info('boom');

        $this->assertCount(0, $this->history);
    }

    public function testSendWarning()
    {
        $logger = $this->makeLogger([], [$this->jsonResponse(['errcode' => 0, 'errmsg' => 'ok'])]);

        $logger->warning('boom');

        $message = $this->getSentMessage();

        $this->assertSame('text', $message['msgtype']);
        $this->assertStringContainsString('boom', $message['text']['content']);
    }

    public function testLongMessageIsTruncated()
    {
        $logger = $this->makeLogger([], [$this->jsonResponse(['errcode' => 0, 'errmsg' => 'ok'])]);

        $logger->warning(str_repeat('a', 20000));

        $message = $this->getSentMessage();

        $this->assertSame(15000, strlen($message['text']['content']));
    }

    public function testDingTalkErrorIsSwallowed()
    {
        $logger = $this->makeLogger([], [$this->jsonResponse(['errcode' => 310000, 'errmsg' => 'error'])]);

        $logger->warning('boom');

        $this->assertCount(1, $this->history);
    }

    public function testInvalidConfigIsSwallowed()
    {
        $logger = $this->makeLogger(['access_token' => ''], []);

        $logger->warning('boom');

        $this->assertCount(0, $this->history);
    }
}
