<?php

namespace WordPressImportBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WordPressImportBundle\Event\ApiResponseBodyEvent;

class ApiResponseBodyListener implements EventSubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return [
            ApiResponseBodyEvent::class => 'onApiResponseBodyEvent',
        ];
    }

    public function onApiResponseBodyEvent(ApiResponseBodyEvent $event)
    {
        $json = $event->getBody();

        // Remove hidden characters from json (https://stackoverflow.com/questions/17219916/json-decode-returns-json-error-syntax-but-online-formatter-says-the-json-is-ok)
        for ($i = 0; $i <= 31; ++$i) {
            $json = str_replace(\chr($i), '', $json);
        }
        $json = str_replace(\chr(127), '', $json);

        if (0 === strpos(bin2hex($json), 'efbbbf')) {
            $json = substr($json, 3);
        }

        $event->setBody($json);
    }

}