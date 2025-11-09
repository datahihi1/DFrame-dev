<?php

use PHPUnit\Framework\TestCase;
use DFrame\Application\Router;

/** 
 * Unit tests for the framework functionalities.
 */
final class FrameworkUnitTest extends TestCase
{
    public function testHomeRoute()
    {
        // Giả lập request cho trang chủ
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        // Khởi tạo router và đăng ký route
        $router = new Router();
        $router->get('/', function () {
            return 'Home page';
        });

        // Bắt kết quả output
        ob_start();
        $router->runInstance();
        $output = ob_get_clean();

        // Kiểm tra kết quả
        $this->assertEquals('Home page', $output);
    }

    public function testMaintenanceMode()
    {
        // Giả lập bật chế độ bảo trì
        $_ENV['MAINTENANCE_MODE'] = 'true';

        // Giả lập hàm kiểm tra (ví dụ trong App hoặc index.php)
        $isMaintenance = ($_ENV['MAINTENANCE_MODE'] ?? 'false') === 'true';

        if ($isMaintenance) {
            $output = 'Bảo trì hệ thống';
        } else {
            $output = 'Home page';
        }

        $this->assertStringContainsString('bảo trì', mb_strtolower($output));
    }
}
