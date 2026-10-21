<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:50 下午.
 */

namespace HughCube\Laravel\DingTalk\Tests;

use HughCube\Laravel\DingTalk\Robot\Client as Robot;
use HughCube\Laravel\DingTalk\Robot\Messages\Text;

/**
 * Integration test against a real robot, only runs when DING_TALK_ROBOT_URL is configured.
 *
 * DING_TALK_ROBOT_URL='https://oapi.dingtalk.com/robot/send?access_token=xxx' ./vendor/bin/phpunit
 */
class RealRobotTest extends TestCase
{
    /**
     * @return string
     */
    protected function getWebhookUrl(): string
    {
        $url = (string) getenv('DING_TALK_ROBOT_URL');
        if ('' === $url) {
            $this->markTestSkipped('The environment variable DING_TALK_ROBOT_URL is not configured.');
        }

        return $url;
    }

    public function testSendWithFullUrl()
    {
        $robot = new Robot(['url' => $this->getWebhookUrl(), 'secret' => (string) getenv('DING_TALK_ROBOT_SECRET')]);

        $results = $robot->send(new Text('laravel-dingtalk integration test: url mode'));

        $this->assertArrayHasKey('errcode', $results);
        $this->assertEquals(0, $results['errcode']);
    }

    public function testSendWithAccessToken()
    {
        parse_str((string) parse_url($this->getWebhookUrl(), PHP_URL_QUERY), $query);

        $robot = new Robot([
            'access_token' => (string) ($query['access_token'] ?? ''),
            'secret'       => (string) getenv('DING_TALK_ROBOT_SECRET'),
        ]);

        $results = $robot->send(new Text('laravel-dingtalk integration test: access_token mode'));

        $this->assertArrayHasKey('errcode', $results);
        $this->assertEquals(0, $results['errcode']);
    }
}
