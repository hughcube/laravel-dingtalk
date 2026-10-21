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

class FacadeTest extends TestCase
{
    public function testIsFacade()
    {
        $this->assertInstanceOf(Manager::class, DingTalk::getFacadeRoot());
    }

    public function testRobot()
    {
        $this->assertInstanceOf(Robot::class, DingTalk::robot());
    }
}
