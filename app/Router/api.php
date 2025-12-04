<?php

$router = new DFrame\Application\Router();

$router->signApi('GET /', function () {
    return "Hello, World!";
})->name('api.home');

// Demo Send Mail
$router->signApi('GET /demo/mail', function () {
    $mail = new DFrame\Application\Mail();
    $mail   ->to(email: 'datndph42403@gmail.com')
            ->subject(subject: 'Test Email from DFrame Mailer 2.0')
            ->body('This is a test email sent from DFrame Mailer 2.0.');
    $mail->send();
    return 'Email sent successfully!';
})->name('api.demo.mail');

// Demo Redis Cache
$router->signApi('GET /demo/cache', function () {
        $cache = new \DFrame\Application\Drive\Cache\Redis([
            'host'        => '127.0.0.1',
            'port'        => 6379,
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

$router->signApi('GET /products', function(){
    $products = new App\Model\Products();
    $allProducts = $products->fetchAll();
    return ([
        'ok' => true,
        'products' => $allProducts
    ]);
});

$router->signApi('GET /products/{id}', function($id){
    $products = new App\Model\Products();
    $product = $products->where('id', $id)->first();
    if(!$product){
        http_response_code(404);
        return ([
            'ok' => false,
            'message' => 'Product not found'
        ]);
    }
    return ([
        'ok' => true,
        'product' => $product
    ]);
});

$router->signApi('POST /products', function(){
    // Nếu là JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
    } else {
        // Nếu là form-data hoặc x-www-form-urlencoded
        $data = $_POST;
    }

    if (!is_array($data)) {
        return [
            'ok' => false,
            'message' => 'Invalid input data'
        ];
    }

    $products = new App\Model\Products();
    $newProductId = $products->insert($data)->execute();
    return [
        'ok' => true,
        'message' => 'Product created',
        'product_id' => $newProductId
    ];
});

$router->signApi('PUT /products/{id}', function($id){
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
    } elseif (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
        parse_str(file_get_contents('php://input'), $data);
    } else {
        $data = [];
    }

    // dd($data); // kiểm tra dữ liệu

    if (!is_array($data) || empty($data)) {
        return [
            'ok' => false,
            'message' => 'Invalid or empty input data'
        ];
    }

    // Loại bỏ trường id khỏi $data để tránh update id
    unset($data['id']);

    $products = new App\Model\Products();
    $existingProduct = $products->where('id', $id)->first();
    if(!$existingProduct){
        http_response_code(404);
        return [
            'ok' => false,
            'message' => 'Product not found'
        ];
    }
    
    $test = $products->where('id', $id)->update($data);
    $test->execute();
    return [
        'ok' => true,
        'message' => 'Product updated'
    ];
});

$router->signApi('DELETE /products/{id}', function($id){
    $products = new App\Model\Products();
    $existingProduct = $products->where('id', $id)->first();
    if(!$existingProduct){
        http_response_code(404);
        return [
            'ok' => false,
            'message' => 'Product not found'
        ];
    }
    $products->softDelete()->where('id', $id)->execute();
    return [
        'ok' => true,
        'message' => 'Product deleted'
    ];
});

$router->signApi('GET /sitemap.xml', [App\Controller\SitemapController::class, 'index'])->name('api.sitemap');