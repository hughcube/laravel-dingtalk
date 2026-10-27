<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:45 下午.
 */

namespace HughCube\Laravel\DingTalk\Tests;

use HughCube\Laravel\DingTalk\DingTalk;
use HughCube\Laravel\DingTalk\Manager;
use HughCube\Laravel\DingTalk\Robot\Client as Robot;
use HughCube\Laravel\DingTalk\Robot\Messages\Text;
use InvalidArgumentException;

class ManagerTest extends TestCase
{
    /**
     * @return array
     */
    protected function parseQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    public function testIsManager()
    {
        $this->assertInstanceOf(Manager::class, DingTalk::getFacadeRoot());
    }

    public function testRobot()
    {
        $this->assertInstanceOf(Robot::class, DingTalk::robot());
    }

    public function testRobotIsSingleton()
    {
        $this->assertSame(DingTalk::robot(), DingTalk::robot());
        $this->assertSame(DingTalk::robot('default'), DingTalk::robot());
    }

    public function testCustomRobot()
    {
        $this->app['config']->set('dingtalk.robots.custom', [
            'url' => 'https://example.com/robot/send?access_token=custom-token',
        ]);

        $this->assertSame(
            'https://example.com/robot/send?access_token=custom-token',
            DingTalk::robot('custom')->getRequestUrl()
        );
    }

    public function testRobotsAreIsolated()
    {
        $this->app['config']->set('dingtalk.robots.a', ['access_token' => 'token-a']);
        $this->app['config']->set('dingtalk.robots.b', ['access_token' => 'token-b']);

        $this->assertSame('token-a', $this->parseQuery(DingTalk::robot('a')->getRequestUrl())['access_token']);
        $this->assertSame('token-b', $this->parseQuery(DingTalk::robot('b')->getRequestUrl())['access_token']);
        $this->assertNotSame(DingTalk::robot('a'), DingTalk::robot('b'));
    }

    public function testDefaultsAppliedToRobot()
    {
        $this->app['config']->set('dingtalk.defaults', ['secret' => 'global-secret']);
        $this->app['config']->set('dingtalk.robots.default', ['access_token' => 'token-a']);

        $query = $this->parseQuery(DingTalk::robot()->getRequestUrl());

        $this->assertArrayHasKey('timestamp', $query);
        $this->assertArrayHasKey('sign', $query);
        $this->assertSame(
            base64_encode(hash_hmac('sha256', $query['timestamp']."\n".'global-secret', 'global-secret', true)),
            $query['sign']
        );
    }

    public function testRobotWithoutConfigThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        DingTalk::robot('not-exists')->getRequestUrl();
    }

    public function testSendWithUrlFromConfig()
    {
        $this->app['config']->set('dingtalk.robots.default', [
            'url'  => 'https://example.com/robot/send?access_token=config-token',
            'http' => ['handler' => $this->mockHandler([
                $this->jsonResponse(['errcode' => 0, 'errmsg' => 'ok']),
            ])],
        ]);

        $results = DingTalk::robot()->send(new Text('hello'));

        $this->assertSame(0, $results['errcode']);
        $this->assertSame('config-token', $this->parseQuery((string) $this->getLastRequest()->getUri())['access_token']);
    }

    public function testManagerWithContainer()
    {
        $this->app['config']->set('dingtalk.robots.default', ['access_token' => 'token-a']);

        $manager = new Manager($this->app);

        $this->assertSame('token-a', $this->parseQuery($manager->robot()->getRequestUrl())['access_token']);
    }

    public function testManagerWithGlobalContainer()
    {
        $this->app['config']->set('dingtalk.robots.default', ['access_token' => 'token-a']);

        $manager = new Manager();

        $this->assertSame('token-a', $this->parseQuery($manager->robot()->getRequestUrl())['access_token']);
    }

    public function testLegacyConfigFallback()
    {
        $this->app['config']->set('dingTalk.robots.legacy', ['access_token' => 'legacy-token']);

        $this->assertSame('legacy-token', $this->parseQuery(DingTalk::robot('legacy')->getRequestUrl())['access_token']);
    }
}
