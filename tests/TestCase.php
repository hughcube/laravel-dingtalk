<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 11:36 下午.
 */

namespace HughCube\Laravel\DingTalk\Tests;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use HughCube\Laravel\DingTalk\DingTalk;
use HughCube\Laravel\DingTalk\Robot\Client as Robot;
use HughCube\Laravel\DingTalk\ServiceProvider as PackageServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class TestCase extends OrchestraTestCase
{
    /**
     * @var array<int, array{request: RequestInterface, response: ResponseInterface, options: array, error: \Throwable|null}>
     */
    protected array $history = [];

    /**
     * @param Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            PackageServiceProvider::class,
        ];
    }

    /**
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        /** @var Repository $appConfig */
        $appConfig = $app['config'];
        $appConfig->set(DingTalk::getFacadeAccessor(), (require dirname(__DIR__).'/config/config.php'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        DingTalk::clearResolvedInstances();
        $this->history = [];
    }

    /**
     * @param ResponseInterface|\Throwable|mixed $response
     *
     * @return ResponseInterface
     */
    protected function jsonResponse($data, int $status = 200): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($data));
    }

    /**
     * Create a Guzzle HandlerStack using the mock transport.
     *
     * @param array $queue
     *
     * @return HandlerStack
     */
    protected function mockHandler(array $queue = []): HandlerStack
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        return $stack;
    }

    /**
     * @param array $config
     * @param array $queue
     *
     * @return Robot
     */
    protected function makeRobot(array $config = [], array $queue = []): Robot
    {
        if ([] === $queue) {
            $queue = [$this->jsonResponse(['errcode' => 0, 'errmsg' => 'ok'])];
        }

        $config['http'] = array_replace(
            ['handler' => $this->mockHandler($queue)],
            $config['http'] ?? []
        );

        return new Robot($config);
    }

    /**
     * @return RequestInterface
     */
    protected function getLastRequest(): RequestInterface
    {
        $item = end($this->history);

        return $item['request'];
    }
}
