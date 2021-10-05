<?php

namespace App\Kyc;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Request
{
    /**
     * @var string $path
     */
    private $path;

    /**
     * @var string $method
     */
    private $method = 'POST';

    /**
     * @var string $url
     */
    private $url;

    /**
     * @var array $params
     */
    private $params = [];

    /**
     * @var array $headers
     */
    private $headers = [];

    /**
     * Request constructor.
     */
    public function __construct()
    {
        $this->http = new Client([
            'base_uri' => 'http://192.168.53.5:5000/'
        ]);
    }

    public function process()
    {
        try {
            $response = $this->http->request($this->method, $this->path, [
                'multipart' => [
                    [
                        'Content-type' => 'multipart/form-data',
                        'name' => 'image',
                        'contents' => fopen($this->params['image'], 'r')
                    ]
                ],
                'headers' => $this->headers
            ]);
           
            $body = $response->getBody()->getContents();
            return \GuzzleHttp\json_decode($body, true);

        } catch (\GuzzleHttp\Exception\BadResponseException $exception) {
            $error = $exception->getResponse()->getBody()->getContents();
            // Log::error($error);
            return response()->json(json_decode($error, true), 400);
        }
    }

    public function setParams(array $params): self
    {
        $this->params = $params;
        return $this;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function setHeaders($headers)
    {
        $this->headers = $headers;
        return $this;
    }
}