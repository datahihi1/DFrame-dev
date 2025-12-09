<?php

$cli->register('hello', [\App\Command\Hello::class, 'handle']);
$cli->register('choice', [\App\Command\Hello::class, 'choice']);
$cli->register('hi', [\App\Command\Hello::class,'num']);

$cli->register('sample', [\App\Command\Sample::class, 'handle']);
