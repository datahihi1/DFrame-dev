<?php

use App\Controller\UserController;

use App\Middleware\UserAuthencation;
use DFrame\Application\Router;
use DFrame\Application\View;

UserAuthencation::sign();

$router = new DFrame\Application\Router();

$router->sign('GET /', function () {
    return "Hello, World!";
})->name('home');

$router->sign('GET /minesv', function () {
    return View::render('minesv');
})->name('minesv');

$router->sign('GET /user/list', [UserController::class, 'listUsers'])->name('user.list');
$router->sign('GET /user/store', [UserController::class, 'addUser'])->name('user.add');
$router->sign('POST /user/store', [UserController::class, 'storeUser'])->name('user.store');
$router->sign('GET /user/edit/{id}', [UserController::class, 'editUser'])->name('user.edit');
$router->sign('POST /user/edit/{id}', [UserController::class, 'updateUser'])->name('user.update');
$router->sign('DELETE /user/delete/{id}', [UserController::class, 'deleteUser'])->name('user.delete');

Router::group('/DMail')::action(function (Router $router) {
    $router->sign('GET /send', function () {
        $mail = new DFrame\Application\Mail();
        $mail   ->to('datndph42403@gmail.com')
                ->subject('Test Email from DFrame')
                ->body('This is a test email sent from DFrame.')
                ->send();
        return 'Email sent successfully!';
    })->name('dmail.send');
});


$router->default(function () {
    return get404pages() ?? '404 Not Found';
});

$router->scanControllerAttributes([
        App\Controller\UserController::class,
    ]);