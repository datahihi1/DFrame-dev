<?php
namespace DFrame\Reports;

use DFrame\Reports\Interface\HandlerInterface;
use DFrame\Reports\Interface\RenderInterface;

class Handler extends HandlerInterface
{
    private bool $saveLog;
    private string $logFile;
    private RenderInterface $renderer;

    /**
     * Resignter a new error and exception handler
     * 
     * @param bool $saveLog Whether to save logs to a file
     * @param string $logFile The log file path
     * @param mixed $renderer The renderer instance to use
     */
    public function __construct(bool $saveLog = false, string $logFile = 'errors.log', ?RenderInterface $renderer = null)
    {
        $this->saveLog = $saveLog;
        $this->logFile = $logFile;
        $this->renderer = $renderer ?? $this->detectRenderer();

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleRuntime']);
    }

    private function detectRenderer(): RenderInterface
    {
        return php_sapi_name() === 'cli'
            ? new \DFrame\Reports\Render\Cli()
            : new \DFrame\Reports\Render\Html();
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno))
            return false;

        $type = match ($errno) {
            E_PARSE => 'parse',
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'runtime',
            default => 'error',
        };

        $this->log($type, $errstr, $errfile, $errline, ['code' => $errno]);
        $this->renderer->render($type, $errstr, $errfile, $errline, ['code' => $errno]);
        return true;
    }

    public function handleException(\Throwable $exception): void
    {
        $this->log('exception', $exception->getMessage(), $exception->getFile(), $exception->getLine());
        $this->renderer->render('exception', $exception->getMessage(), $exception->getFile(), $exception->getLine());
    }

    /**
     * Handle parse errors on shutdown
     * @return void 
     */
    public function handleParse(): void
    {
        // Handled via shutdown
    }

    public function handleRuntime(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $type = $error['type'] === E_PARSE ? 'parse' : 'runtime';
            $this->log($type, $error['message'], $error['file'], $error['line'], ['code' => $error['type']]);
            $this->renderer->render($type, $error['message'], $error['file'], $error['line'], ['code' => $error['type']]);
        }
    }

    public function log(string $type, string $message, string $file, int $line, array $context = []): void
    {
        if (!$this->saveLog)
            return;

        $dir = dirname($this->logFile);
        if (!is_dir($dir))
            mkdir($dir, 0755, true);

        $log = sprintf(
            "[%s] %s | %s:%d | %s | %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($type),
            $file,
            $line,
            $message,
            json_encode($context)
        );
        file_put_contents($this->logFile, $log, FILE_APPEND | LOCK_EX);
    }
}