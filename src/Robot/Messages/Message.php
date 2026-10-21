<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/11/5
 * Time: 17:46.
 */

namespace HughCube\Laravel\DingTalk\Robot\Messages;

abstract class Message
{
    protected array $message = [];

    /**
     * @return array
     */
    public function getMessage(): array
    {
        return $this->message;
    }
}
