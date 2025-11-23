<?php

use App\Controller\UserController;

use App\Middleware\UserAuthencation;
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

$router->default(function () {
    return get404pages() ?? '404 Not Found';
});

$router->scanControllerAttributes(
    [
        App\Controller\UserController::class,
    ]
    );