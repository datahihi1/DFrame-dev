<?php

namespace DFrame\Application;

/**
 * #### Command Line Interface Application
 *
 * Command class to handle CLI commands and options.
 */
class Command
{
    protected array $commands = [];
    protected string $version = App::version;
    protected bool $debug = false;

    public function __construct()
    {
        $this->register();
    }

    protected function register()
    {
        $this->commands['search'] = new \DFrame\Command\Search();
        // bạn có thể thêm lệnh khác ở đây
    }

    public function run()
    {
        global $argv;
        $args = array_slice($argv, 1);

        // Nếu không có args, mặc định hiển thị help
        if (empty($args)) {
            $this->showHelp();
            exit(0);
        }

        // Xử lý các option chung
        if (in_array('-h', $args) || in_array('--help', $args)) {
            $this->showHelp();
            exit(0);
        }

        if (in_array('-v', $args) || in_array('--version', $args)) {
            echo "\033[34mDFramework DLI version {$this->version}\033[0m" . PHP_EOL;
            exit(0);
        }

        if (in_array('--debug', $args)) {
            $this->debug = true;
            $args = array_filter($args, fn($arg) => $arg !== '--debug');
            $args = array_values($args);
        }

        $command = $args[0] ?? null;
        $commandArgs = array_slice($args, 1);

        if (!$command || !isset($this->commands[$command])) {
            echo "Command not found.\n";
            // $this->showHelp();  // Nếu lệnh không tồn tại, cũng show help
            exit(1);
        }

        if ($this->debug) {
            echo "[DEBUG] Running command '$command' with args: " . implode(' ', $commandArgs) . PHP_EOL;
        }

        $this->commands[$command]->handle($commandArgs, $this->debug);
    }

    protected function showHelp()
    {
        // Màu sắc cho terminal
        $green = "\033[32m";
        $cyan  = "\033[36m";
        $yellow = "\033[33m";
        $reset = "\033[0m";

        echo $green . "DFramework CLI Application" . $reset . "\n";
        echo $yellow . "Version: " . App::VERSION . $reset . "\n\n";

        echo $cyan . "Usage:" . $reset . "\n";
        echo "  php dli [options] [command] [arguments]\n\n";

        echo $cyan . "Options:" . $reset . "\n";
        printf("  %-15s Show this help message\n", "-h, --help");
        printf("  %-15s Show application version\n", "-v, --version");
        printf("  %-15s Enable debug mode\n\n", "--debug");

        echo $cyan . "Commands:" . $reset . "\n";
        // foreach ($this->commands as $name => $cmd) {
        //     printf("  %-15s %s\n", $name);
        // }
    }
}
