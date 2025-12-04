<?php

use App\Controller\UserController;

use App\Middleware\UserAuthencation;
use DFrame\Application\Router;
use DFrame\Application\View;

UserAuthencation::sign();

$router = new DFrame\Application\Router();

$router->sign('GET /', function () {
    return "<h1>Hello, World!</h1>";
})->name('home');

Router::group('/user')::action(function (Router $router) {
    $router->sign('GET /list', [UserController::class, 'listUsers'])->name('user.list');
    $router->sign('GET /store', [UserController::class, 'addUser'])->name('user.add');
    $router->sign('POST /store', [UserController::class, 'storeUser'])->name('user.store');
    $router->sign('GET /edit/{id}', [UserController::class, 'editUser'])->name('user.edit');
    $router->sign('POST /edit/{id}', [UserController::class, 'updateUser'])->name('user.update');
    $router->sign('DELETE /delete/{id}', [UserController::class, 'deleteUser'])->name('user.delete');
});

$router->sign('GET /sitemap.xml', [\App\Controller\SitemapController::class, 'index'])->name('sitemap');

$router->default(function () {
    return get404pages() ?? '404 Not Found';
});

$router->scanControllerAttributes([
        App\Controller\UserController::class,
]);