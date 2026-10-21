<?php

return [
    /**
     * default config.
     */
    'defaults' => [],

    'robots' => [
        'default' => [
            /**
             * Whether the robot is enabled, the consumer decides whether to send.
             */
            'enabled' => env('DING_TALK_ROBOT_ENABLED', true),

            /**
             * The full webhook url, e.g. https://oapi.dingtalk.com/robot/send?access_token=xxx.
             * Takes precedence over access_token when both are configured.
             */
            'url' => env('DING_TALK_ROBOT_URL', ''),

            /**
             * The robot access_token, the default url will be generated automatically.
             */
            'access_token' => env('DING_TALK_ROBOT_ACCESS_TOKEN', ''),

            /**
             * The robot secret, timestamp and sign will be appended automatically.
             */
            'secret' => env('DING_TALK_ROBOT_SECRET', ''),
        ],
    ],
];
