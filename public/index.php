<?php

declare(strict_types=1);

use W4OS\Helpers\App;

require dirname(__DIR__) . '/vendor/autoload.php';

App::create()->run();

// What waits for the answer: the answer goes first and its client is freed, then a simulator that registered is read and the tasks
// that are due run
W4OS\Helpers\Http\Action\Engine::finish();
