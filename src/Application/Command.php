<?php

namespace DFrame\Application;

/**
 * CLI Command Application for DFrame
 */
class Command
{
    protected array $commands = [];

    public function __construct()
    {
        // Đăng ký các lệnh mặc định
        $this->register('help', function () {
            echo "DLI - DFrame CLI Core Help:\n";
            echo "Version: " . \DFrame\Application\App::VERSION . "\n";
            echo "\n";
            echo "Usage: php dli <command> [options]\n";
            echo "\n";
            echo "  -v, version, core:version      Show framework version\n";
            echo "  help, core:help                Show this help\n";
            echo "  server, core:server            Start built-in PHP server\n";
            echo "  <command>                      Run custom command\n";
        });

        $this->register('-v', function () {
            echo "DFrame version: " . \DFrame\Application\App::VERSION . "\n";
        });
        $this->register('version', function () {
            echo "DFrame version: " . \DFrame\Application\App::VERSION . "\n";
        });

        $this->register('core:help', function () {
            echo "DFrame CLI Help:\n";
            echo "Available commands:\n";
            foreach (array_keys($this->commands) as $command) {
                echo "  - $command\n";
            }
        });

        $this->register('core:version', function () {
            echo "DFrame version: " . \DFrame\Application\App::VERSION . "\n";
        });
        $this->register('server', function () {
            $host = '0.0.0.0:8000';
            $publicDir = defined('INDEX_DIR') ? INDEX_DIR : __DIR__ . '/../../public';
            echo "Starting PHP built-in server at http://$host\n";
            echo "Press Ctrl+C to stop.\n";
            passthru("php -S $host -t $publicDir");
        });

        $this->register('core:server', function () {
            $host = '0.0.0.0:8000';
            $publicDir = defined('INDEX_DIR') ? INDEX_DIR : __DIR__ . '/../../public';
            echo "Starting PHP built-in server at http://$host\n";
            echo "Press Ctrl+C to stop.\n";
            passthru("php -S $host -t $publicDir");
        });
    }

    /**
     * Đăng ký lệnh mới
     */
    public function register(string $name, callable $handler): void
    {
        $this->commands[$name] = $handler;
    }

    /**
     * Chạy ứng dụng CLI
     */
    public function run(): void
    {
        global $argv;
        $cmd = $argv[1] ?? 'help';

        if (isset($this->commands[$cmd])) {
            $this->commands[$cmd]();
        } else {
            echo "Unknown command: $cmd\n";
            $this->commands['help']();
        }
    }
}
