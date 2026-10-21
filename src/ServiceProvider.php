<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/18
 * Time: 10:32 下午.
 */

namespace HughCube\Laravel\DingTalk;

use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use Laravel\Lumen\Application as LumenApplication;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Boot the provider.
     */
    public function boot()
    {
        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $source = realpath(dirname(__DIR__).'/config/config.php');
            $this->publishes([$source => config_path(sprintf('%s.php', DingTalk::getFacadeAccessor()))]);
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure(DingTalk::getFacadeAccessor());
        }
    }

    /**
     * Register the provider.
     */
    public function register()
    {
        $this->app->singleton(DingTalk::getFacadeAccessor(), function ($app) {
            return new Manager();
        });
    }
}
