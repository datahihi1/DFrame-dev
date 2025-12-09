<?php

namespace DFrame\Command;

use DFrame\Application\App;

class Core
{
    public App $app;
    public static function help()
    {
        $dfver = App::VERSION ?? "unknown";
        return function () use ($dfver) {
            echo "DLI - DFrame CLI Core Help Show\n";
            echo "Version: " . cli_green($dfver) . " | PHP: " . phpversion() . " (" . php_sapi_name() . ")" . "\n";
            echo "Usage: dli <command> [options]\n\n";
            echo "Available commands:\n";
            echo "  help, -h        Show this help message\n";
            echo "  version, -v     Show application version\n";
            echo "  server, -s      Start the development server\n";
            echo "  list            List all available commands\n";
            echo "  npm-install     Run npm install in the project directory\n";
            echo "  vite            Start the Vite development server\n";
            echo "\n";
            echo "Options:\n";
            echo " Server command options: php dli -s[server] <options>\n";
            echo "  --host          Bind to a specific host (default: localhost)\n";
            echo "  --port          Specify a port (default: 8000)\n";
            echo "  --mode          Specify the mode (default: lan)\n";
        };
    }

    public static function version()
    {
        return function () {
            echo "Version: " . cli_green(App::VERSION ?? "unknown") . "\n";
        };
    }

    public static function list(array $commands)
    {
        return function () use ($commands) {
            echo "Available commands:\n";
            foreach ($commands as $cmd) {
                echo "  - $cmd\n";
            }
        };
    }

}
