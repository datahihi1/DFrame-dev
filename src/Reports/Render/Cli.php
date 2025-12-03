<?php
namespace DFrame\Reports\Render;

use DFrame\Reports\Interface\RenderInterface;

/**
 * CLI Renderer for DFrame Reports
 */
class Cli implements RenderInterface
{
    private static $colors = [
        'error' => "\033[35m",
        'exception' => "\033[31m",
        'parse' => "\033[34m",
        'runtime' => "\033[33m",
        'reset' => "\033[0m",
        'bold' => "\033[1m",
    ];

    public function render(string $type, string $message, string $file, int $line, array $context = []): void
    {
        $dfversion = class_exists(\DFrame\Application\App::class)
        ? \DFrame\Application\App::VERSION
        : 'Non-DFrame Environment';
        
        $phpversion = PHP_VERSION;
        
        $color = self::$colors[$type] ?? self::$colors['error'];
        $reset = self::$colors['reset'];
        $bold = self::$colors['bold'];

        echo "$color{$bold}DFrame Report$reset" . PHP_EOL;
        echo "$color Version: $reset$dfversion" . PHP_EOL;
        echo "$color PHP Version: $reset$phpversion" . PHP_EOL;
        echo PHP_EOL;
        echo "DFrame Report detected a bug!" . PHP_EOL;
        echo "$color{$bold}===== $type =====$reset" . PHP_EOL;
        echo "$color Message: $reset$message" . PHP_EOL;
        echo "$color File:    $reset$file" . PHP_EOL;
        echo "$color Line:    $reset$line" . PHP_EOL;
        echo "$color Time:    $reset" . date('Y-m-d H:i:s') . PHP_EOL;

        if (!empty($context['code'])) {
            echo "$color Code:    $reset" . $context['code'] . PHP_EOL;
        }

        if ($file && file_exists($file)) {
            $lines = file($file);
            $start = max($line - 3, 0);
            $end = min($line + 2, count($lines));
            echo "$color Nearby: $reset" . PHP_EOL;
            for ($i = $start; $i < $end; $i++) {
                $num = $i + 1;
                $prefix = $num == $line ? "$bold>$reset " : "  ";
                echo "  $prefix$num: " . rtrim($lines[$i]) . PHP_EOL;
            }
        }

        echo "$color{$bold}=================$reset" . PHP_EOL . PHP_EOL;
        exit(1);
    }
}