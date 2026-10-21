<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/11/5
 * Time: 17:50.
 */

namespace HughCube\Laravel\DingTalk\Tests;

use HughCube\Laravel\DingTalk\Robot\Messages\Message;
use HughCube\Laravel\DingTalk\Robot\Messages\Text;

class MessagesTest extends TestCase
{
    public function testText()
    {
        $message = new Text('hello');

        $this->assertInstanceOf(Message::class, $message);
        $this->assertSame([
            'msgtype' => 'text',
            'text'    => ['content' => 'hello'],
        ], $message->getMessage());
    }

    public function testTextWithMultibyteContent()
    {
        $message = new Text('中文消息');

        $this->assertSame('中文消息', $message->getMessage()['text']['content']);
    }
}
