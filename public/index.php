<?php

declare(strict_types=1);

use W4OS\Helpers\App;

require dirname(__DIR__) . '/vendor/autoload.php';

App::create()->run();

// What waits for the answer: the answer goes first, then a simulator that registered is read and the tasks that are due run
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
}
W4OS\Helpers\Http\Action\Engine::finish();
