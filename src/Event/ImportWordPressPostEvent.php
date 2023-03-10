<?php

declare(strict_types=1);

/*
 * This file is part of the WordPressImport Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 */

namespace WordPressImportBundle\Event;

use Contao\NewsModel;
use GuzzleHttp\Client;
use Symfony\Contracts\EventDispatcher\Event;

class ImportWordPressPostEvent extends Event
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var object
     */
    private $post;

    /**
     * @var NewsModel
     */
    private $news;

    public function __construct(Client $client, $post, NewsModel $news)
    {
        $this->client = $client;
        $this->post = $post;
        $this->news = $news;
    }

    /**
     * The HTTP client used for accessing the WordPress API.
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * The WordPress post received from the API.
     *
     * @return object
     */
    public function getPost()
    {
        return $this->post;
    }

    /**
     * The generated NewsModel instance for this WordPress post.
     */
    public function getNews(): NewsModel
    {
        return $this->news;
    }
}
