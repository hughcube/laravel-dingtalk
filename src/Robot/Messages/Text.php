<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/11/5
 * Time: 17:47.
 */

namespace HughCube\Laravel\DingTalk\Robot\Messages;

class Text extends Message
{
    public function __construct($content)
    {
        $this->message = [
            'msgtype' => 'text',
            'text'    => [
                'content' => $content,
            ],
        ];
    }
}
