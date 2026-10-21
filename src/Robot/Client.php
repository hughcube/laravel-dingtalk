<?php
/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/20
 * Time: 4:21 下午.
 */

namespace HughCube\Laravel\DingTalk\Robot;

use Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use HughCube\Laravel\DingTalk\Robot\Messages\Message;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class Client
{
    /**
     * The default dingtalk robot url.
     */
    public const DEFAULT_URL = 'https://oapi.dingtalk.com/robot/send';

    /**
     * @var array
     */
    protected array $config;

    protected ?HttpClient $httpclient = null;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = array_replace_recursive($this->defaultConfig(), $config);
    }

    /**
     * @return array
     */
    public function defaultConfig(): array
    {
        return [
            'http' => [
                RequestOptions::TIMEOUT         => 10.0,
                RequestOptions::CONNECT_TIMEOUT => 10.0,
                RequestOptions::READ_TIMEOUT    => 10.0,
                RequestOptions::HTTP_ERRORS     => false,
            ],
        ];
    }

    /**
     * @return HttpClient
     */
    protected function getHttpClient(): HttpClient
    {
        if (!$this->httpclient instanceof HttpClient) {
            $this->httpclient = new HttpClient($this->get('http', []));
        }

        return $this->httpclient;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * Get the send url, fallback to the default url.
     *
     * @return string
     */
    public function getUrl(): string
    {
        $url = trim((string) $this->get('url', ''));

        return '' === $url ? static::DEFAULT_URL : $url;
    }

    /**
     * Get the final request url with access_token / timestamp / sign merged.
     *
     * @throws InvalidArgumentException
     *
     * @return string
     */
    public function getRequestUrl(): string
    {
        $url = $this->getUrl();

        $pairs = array_values(array_filter(explode('&', (string) parse_url($url, PHP_URL_QUERY)), function ($pair) {
            return '' !== $pair;
        }));

        if (!$this->hasQueryParameter($pairs, 'access_token')) {
            $accessToken = trim((string) $this->get('access_token', ''));
            if ('' !== $accessToken) {
                $pairs[] = 'access_token='.rawurlencode($accessToken);
            }
        }

        if (!$this->hasQueryParameter($pairs, 'access_token')) {
            throw new InvalidArgumentException(
                'The dingtalk robot "access_token" or "url" must be configured.'
            );
        }

        if ('' !== ($secret = trim((string) $this->get('secret', '')))) {
            /** The signature is time limited, drop the expired one before appending. */
            $pairs = array_values(array_filter($pairs, function ($pair) {
                return !$this->hasQueryParameter([$pair], 'timestamp') && !$this->hasQueryParameter([$pair], 'sign');
            }));

            $timestamp = (int) floor(microtime(true) * 1000);

            $pairs[] = 'timestamp='.$timestamp;
            $pairs[] = 'sign='.rawurlencode(base64_encode(hash_hmac('sha256', $timestamp."\n".$secret, $secret, true)));
        }

        return substr($url, 0, strcspn($url, '?#')).'?'.implode('&', $pairs);
    }

    /**
     * @param array  $pairs
     * @param string $name
     *
     * @return bool
     */
    protected function hasQueryParameter(array $pairs, string $name): bool
    {
        foreach ($pairs as $pair) {
            $parts = explode('=', $pair, 2);

            if (rawurldecode($parts[0]) === $name && isset($parts[1]) && '' !== rawurldecode($parts[1])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function send(Message $message)
    {
        $response = $this->getHttpClient()->post($this->getRequestUrl(), [
            RequestOptions::JSON => $message->getMessage(),
        ]);

        $content = $response->getBody()->getContents();
        $results = json_decode($content, true);

        if (!is_array($results) || !isset($results['errcode'])) {
            throw new Exception(sprintf('Unknown dingtalk robot response: %s', $content));
        }

        if (0 !== (int) $results['errcode']) {
            throw new Exception((string) ($results['errmsg'] ?? 'Unknown error!'), (int) $results['errcode']);
        }

        return $results;
    }
}
