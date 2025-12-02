<?php

$router = new DFrame\Application\Router();

$router->signApi('GET /', function () {
    return "Hello, World!";
})->name('api.home');

$router->signApi('GET /demo/mail', function () {
    $mail = new DFrame\Application\Mail();
    $mail   ->to('datd5400@gmail.com')
            ->subject('Test Email from DFrame')
            ->body('This is a test email sent from DFrame.')
            ->send();
    return 'Email sent successfully!';
})->name('api.demo.mail');

// Demo Memcached
$router->signApi('GET /demo/cache', function () {
        $cache = new \DFrame\Application\Drive\Cache\Memcached([
            'host'        => '127.0.0.1',
            'port'        => 11211,
            'prefix'      => 'dframe:',
            'default_ttl' => 60,
        ]);

        // set value
        $cache->set('counter', 0);
        // increment
        $cache->increment('counter');
        // read back
        $counter = $cache->get('counter', 0);

        // store complex value
        $cache->set('user:1', ['id' => 1, 'name' => 'Dat']);
        $user = $cache->get('user:1', null);

        // check exists
        $hasUser = $cache->has('user:1');

        // cleanup example
        $cache->delete('temp');

        return json_encode([
            'ok'       => true,
            'counter'  => $counter,
            'user'     => $user,
            'has_user' => $hasUser,
        ]);
})->name('api.demo.cache');