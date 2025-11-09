<?php

use PHPUnit\Framework\TestCase;
use DFrame\Application\Router;

/**
 * Router unit tests.
 */
final class RouterUnitTest extends TestCase
{
    public function testDemoRoute()
    {
        // Giả lập request cho trang demo
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/demo';

        // Khởi tạo router và đăng ký route
        $router = new Router();
        $router->get('/demo', function () {
            return 'Demo page';
        });

        // Bắt kết quả output
        ob_start();
        $router->runInstance();
        $output = ob_get_clean();

        // Kiểm tra kết quả
        $this->assertEquals('Demo page', $output);
    }
    public function testNotFoundRoute()
    {
        // Giả lập request cho một trang không tồn tại
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/non-existent-page';

        // Khởi tạo router và đăng ký route mặc định
        $router = new Router();
        $router->default(function () {
            return '404 Not Found';
        });

        // Bắt kết quả output
        ob_start();
        $router->runInstance();
        $output = ob_get_clean();

        // Kiểm tra kết quả
        $this->assertEquals('404 Not Found', $output);
    }
}
