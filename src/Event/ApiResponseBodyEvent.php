<?php

namespace WordPressImportBundle\Event;

use GuzzleHttp\Client;
use Symfony\Contracts\EventDispatcher\Event;

class ApiResponseBodyEvent extends Event
{
    /**
     * @var string
     */
    private $body;
    /**
     * @var Client
     */
    private $client;
    /**
     * @var string
     */
    private $endpoint;

    public function __construct(string $body, Client $client, string $endpoint)
    {
        $this->body = $body;
        $this->client = $client;
        $this->endpoint = $endpoint;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }
}