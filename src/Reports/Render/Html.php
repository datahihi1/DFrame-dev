<?php
namespace DFrame\Reports\Render;

use DFrame\Reports\Interface\RenderInterface;

/**
 * HTML Renderer for error and exception reporting.
 */
class Html implements RenderInterface
{
    private static $configs = [
        'error' => ['color' => '#7c3aed', 'icon' => 'Oops, an error occurred!', 'title' => 'Error', 'gradient' => 'linear-gradient(135deg, #8b5cf6, #7c3aed)'],
        'exception' => ['color' => '#dc2626', 'icon' => 'Uncaught exception detected!', 'title' => 'Exception', 'gradient' => 'linear-gradient(135deg, #ef4444, #dc2626)'],
        'parse' => ['color' => '#2563eb', 'icon' => 'Parsing bug detected!', 'title' => 'Parse', 'gradient' => 'linear-gradient(135deg, #3b82f6, #2563eb)'],
        'runtime' => ['color' => '#d97706', 'icon' => 'Application shutdown detected!', 'title' => 'Runtime', 'gradient' => 'linear-gradient(135deg, #f59e0b, #d97706)'],
    ];

    public function render(string $type, string $message, string $file, int $line, array $context = []): void
    {
        /** Check DFrame version */
        $dfver = class_exists(\DFrame\Application\App::class)
            ? \DFrame\Application\App::version
            : 'Non-DFrame Environment';

        $config = self::$configs[$type] ?? self::$configs['error'];

        while (ob_get_level())
            ob_end_clean();

        // set HTTP 500 when rendering an error/exception report
        http_response_code(500);

        // build a small SVG favicon whose fill color is the same as the error color
        $favColor = $config['color'] ?? '#7c3aed';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="12" fill="' . $favColor . '"/><text x="50%" y="55%" font-size="36" text-anchor="middle" fill="#ffffff" font-family="Arial,Helvetica,sans-serif" font-weight="700">!</text></svg>';
        $favicon = 'data:image/svg+xml;utf8,' . rawurlencode($svg);
        ?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= $config['title'] ?>: <?= htmlspecialchars($message) ?></title>

            <!-- colored favicon to visually distinguish error type -->
            <link rel="icon" href="<?= $favicon ?>" type="image/svg+xml">
            <meta name="theme-color" content="<?= $favColor ?>">

            <style>
                /* [CSS ĐÃ ĐƯỢC TỐI ƯU – XEM FILE RIÊNG] */
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box
                }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #f8f9fa;
                    color: #333;
                    line-height: 1.5;
                    min-width: 320px
                }

                .container {
                    margin: 20px;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, .1);
                    overflow: hidden
                }

                @media(max-width:600px) {
                    .container {
                        margin: 0;
                        border-radius: 0;
                        width: 100vw;
                        min-width: 320px;
                        max-width: 100vw;
                        box-shadow: none
                    }
                }

                .header {
                    background: <?= $config['gradient'] ?>;
                    color: white;
                    padding: 15px 20px;
                    font-weight: 500;
                    display: flex;
                    justify-content: space-between;
                    align-items: center
                }

                .tabs {
                    display: flex;
                    gap: 8px
                }

                .tab {
                    background: rgba(255, 255, 255, .2);
                    padding: 4px 12px;
                    border-radius: 4px;
                    font-size: 12px;
                    cursor: pointer
                }

                .tab.active {
                    background: rgba(255, 255, 255, .3)
                }

                .title {
                    padding: 20px;
                    border-bottom: 1px solid #e5e7eb
                }

                .title h2 {
                    color:
                        <?= $config['color'] ?>
                    ;
                    font-size: 18px;
                    font-weight: 600;
                    display: flex;
                    gap: 8px;
                    align-items: center
                }

                .message {
                    color: #6b7280;
                    font-size: 14px
                }

                .code-container {
                    background: #fafafa;
                    border-top: 1px solid #e5e7eb
                }

                .code-header {
                    background: #f3f4f6;
                    padding: 12px 20px;
                    border-bottom: 1px solid #e5e7eb;
                    display: flex;
                    justify-content: space-between;
                    font-size: 12px;
                    color: #6b7280
                }

                .code-viewer {
                    font-family: Monaco, Consolas, monospace;
                    font-size: 13px;
                    line-height: 1.6;
                    background: white;
                    overflow-x: auto;
                    padding: 16px
                }

                .code-line {
                    display: flex
                }

                .line-number {
                    background: #f8f9fa;
                    color: #9ca3af;
                    padding: 0 12px;
                    text-align: right;
                    min-width: 60px;
                    border-right: 1px solid #e5e7eb;
                    font-size: 12px
                }

                .line-content {
                    padding: 0 16px;
                    flex: 1;
                    white-space: pre
                }

                .highlight-line {
                    background-color: <?= $config['color'] ?>20 !important; /* 20 = ~12% opacity */
                    color: <?= $config['color'] ?>;
                    font-weight: 600;
                    border-left: 4px solid <?= $config['color'] ?>;
                    padding-left: 12px;
                }

                /* dim non-highlighted lines slightly to make the highlighted line stand out */
                .code-viewer .code-line:not(.highlight-line) .line-content,
                .code-viewer .code-line:not(.highlight-line) .line-number {
                    opacity: 0.6;
                }

                .php-keyword {
                    color: #0ea5e9
                }

                .php-string {
                    color: #059669
                }

                .php-comment {
                    color: #6b7280;
                    font-style: italic
                }

                .php-variable {
                    color: #d97706
                }

                .php-function {
                    color: #7c3aed
                }

                .php-constant {
                    color: #dc2626
                }

                .php-number {
                    color: #ea580c
                }

                .php-operator {
                    color: #374151
                }
            </style>
        </head>

        <body>
            <div class="container">
                <div class="header">
                    <span><?= $config['icon'] ?></span>
                    <div class="tabs">
                        <span class="tab active" onclick="show('full')">Full</span>
                        <span class="tab" onclick="show('raw')">Raw</span>
                    </div>
                </div>

                <div id="full" class="content">
                    <div class="title">
                        <h2><?= ucfirst($type) ?></h2>
                        <div class="message"><?= nl2br(htmlspecialchars($message)) ?></div>
                        <div>DFrame: <?= htmlspecialchars($dfver) ?> | PHP: <?= htmlspecialchars(PHP_VERSION) ?></div>
                    </div>
                    <?php if ($file && file_exists($file)): ?>
                        <div class="code-container">
                            <div class="code-header">
                                <span><?= htmlspecialchars(basename($file)) ?></span>
                                <span>Line <?= $line ?></span>
                            </div>
                            <div class="code-viewer">
                                <?php
                                $lines = file($file);
                                $start = max($line - 6, 0);
                                $end = min($line + 5, count($lines));
                                for ($i = $start; $i < $end; $i++):
                                    $num = $i + 1;
                                    $code = rtrim($lines[$i]);
                                    ?>
                                    <div class="code-line <?= $num == $line ? 'highlight-line' : '' ?>">
                                        <div class="line-number"><?= $num ?></div>
                                        <div class="line-content"><?= $this->highlight($code) ?></div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="raw" class="content"
                    style="display:none;padding:20px;font-family:monospace;background:#f8f9fa;white-space:pre-wrap;">
                    <?= htmlspecialchars("Type: $type\nMessage: $message\nFile: $file\nLine: $line\nTime: " . date('c')) ?>
                </div>

                <script>
                    function show(id) {
                        document.querySelectorAll('.content').forEach(el => el.style.display = 'none');
                        document.getElementById(id).style.display = 'block';
                        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                        event.target.classList.add('active');
                    }
                </script>
            </div>
        </body>

        </html>
        <?php
        exit;
    }

    private function highlight(string $code): string
    {
        // Remove trailing whitespace but preserve leading whitespace
        $leadingSpace = '';
        if (preg_match('/^(\s+)/', $code, $matches)) {
            $leadingSpace = $matches[1];
        }
        $code = trim($code);

        if (empty($code)) {
            return $leadingSpace;
        }

        $code = htmlspecialchars($code, ENT_NOQUOTES);

        // Handle comments first (to avoid highlighting inside comments)
        $code = preg_replace('/(\/\/.*$)/', '<span class="php-comment">$1</span>', $code);
        $code = preg_replace('/(\/\*.*?\*\/)/s', '<span class="php-comment">$1</span>', $code);

        // Handle strings (single and double quotes)
        $code = preg_replace('/"([^"\\\\]*(\\\\.[^"\\\\]*)*)"/', '<span class="php-string">"$1"</span>', $code);
        $code = preg_replace("/'([^'\\\\]*(\\\\.[^'\\\\]*)*)'/", '<span class="php-string">\'$1\'</span>', $code);

        // Keywords
        $keywords = [
            'abstract',
            'and',
            'array',
            'as',
            'break',
            'callable',
            'case',
            'catch',
            'class',
            'clone',
            'const',
            'continue',
            'declare',
            'default',
            'die',
            'do',
            'echo',
            'else',
            'elseif',
            'empty',
            'enddeclare',
            'endfor',
            'endforeach',
            'endif',
            'endswitch',
            'endwhile',
            'eval',
            'exit',
            'extends',
            'final',
            'finally',
            'for',
            'foreach',
            'function',
            'false',
            'global',
            'goto',
            'get',
            'if',
            'implements',
            'include',
            'include_once',
            'instanceof',
            'insteadof',
            'interface',
            'isset',
            'list',
            'namespace',
            'new',
            'null',
            'or',
            'print',
            'private',
            'protected',
            'public',
            'require',
            'require_once',
            'return',
            'static',
            'switch',
            'throw',
            'trait',
            'try',
            'true',
            'unset',
            'use',
            'var',
            'while',
            'xor',
            'yield',
        ];

        foreach ($keywords as $keyword) {
            $code = preg_replace('/\b(' . preg_quote($keyword) . ')\b(?![^<]*>)/', '<span class="php-keyword">$1</span>', $code);
        }

        // Variables (but not inside already highlighted content)
        $code = preg_replace('/(\$[a-zA-Z_][a-zA-Z0-9_]*)(?![^<]*>)/', '<span class="php-variable">$1</span>', $code);

        // Object operators and array access
        $code = preg_replace('/(-&gt;)/', '<span class="php-operator">-></span>', $code);
        $code = preg_replace('/(::)/', '<span class="php-operator">::</span>', $code);

        // Numbers
        $code = preg_replace('/\b(\d+\.?\d*)\b(?![^<]*>)/', '<span class="php-number">$1</span>', $code);

        // Function calls (word followed by opening parenthesis, but not keywords)
        $code = preg_replace('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*(?=\()(?![^<]*>)(?!.*<span class="php-keyword">)/', '<span class="php-function">$1</span>', $code);

        // Constants (all caps words)
        $code = preg_replace('/\b([A-Z_][A-Z0-9_]{2,})\b(?![^<]*>)/', '<span class="php-constant">$1</span>', $code);

        // Operators
        $operators = [
            '=',
            '+',
            '-',
            '*',
            '/',
            '%',
            '==',
            '!=',
            '&lt;',
            '&gt;',
            '&lt;=',
            '&gt;=',
            '&amp;&amp;',
            '||',
            '!',
            '&amp;',
            '|',
            '^',
            '&lt;&lt;',
            '&gt;&gt;'
        ];
        foreach ($operators as $op) {
            $pattern = '/(' . preg_quote($op, '/') . ')(?![^<]*>)/';
            $code = preg_replace($pattern, '<span class="php-operator">$1</span>', $code);
        }

        return $leadingSpace . $code;
    }
}