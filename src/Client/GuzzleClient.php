<?php

namespace RAPL\RAPL\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;

class GuzzleClient implements HttpClient
{
    /**
     * @var ClientInterface
     */
    private $guzzleClient;

    /**
     * @param ClientInterface $guzzleClient
     */
    public function __construct(ClientInterface $guzzleClient)
    {
        $this->guzzleClient = $guzzleClient;
    }

    /**
     * @param string     $baseUrl
     * @param callable[] $middleware
     *
     * @return GuzzleClient
     */
    public static function create($baseUrl, array $middleware = [])
    {
        $stack = new HandlerStack(new CurlHandler());

        foreach ($middleware as $element) {
            $stack->push($element);
        }

        $client = new Client(
            [
                'base_uri' => $baseUrl,
                'handler'  => $stack,
            ]
        );

        return new self($client);
    }

    /**
     * @param string $method
     * @param string $uri
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function request($method, $uri)
    {
        return $this->guzzleClient->request($method, $uri);
    }
}
