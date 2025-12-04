<?php

namespace App\Controller;

class SitemapController
{
    // TTL cache in seconds
    protected int $cacheTtl = 3600;

    public function index()
    {
        $cacheFile = realpath(__DIR__ . '/../../public') . '/sitemap.xml';
        // fallback if public not resolvable
        if ($cacheFile === false) {
            $cacheFile = dirname(__DIR__, 3) . '/public/sitemap.xml';
        }

        // Regenerate if missing or expired
        if (!file_exists($cacheFile) || (time() - filemtime($cacheFile)) > $this->cacheTtl) {
            $xml = $this->buildSitemapXml();
            // ensure directory exists
            @file_put_contents($cacheFile, $xml, LOCK_EX);
        }

        // Output cached sitemap
        header('Content-Type: application/xml; charset=UTF-8');
        echo file_get_contents($cacheFile);
    }

    protected function buildSitemapXml(): string
    {
        // Use APP_URL env if present; fallback to SERVER
        $base = rtrim(getenv('APP_URL') ?: ($_SERVER['APP_URL'] ?? ''), "/");
        if (empty($base)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }

        $routesInfo = \DFrame\Application\Router::getRegisteredRoutes();
        $paths = [];

        // Collect GET static routes (exclude api and dynamic ones with {)
        foreach ($routesInfo['static'] as $method => $map) {
            if ($method !== 'GET') continue;
            foreach ($map as $path => $meta) {
                // ignore group placeholders / catch-all or dynamic segments
                if (strpos($path, '{') !== false) continue;
                // ignore special routes (health, assets) if needed - user can adjust
                $paths[$path] = [
                    'loc' => rtrim($base, '/') . $path,
                    'lastmod' => date('c'),
                    'changefreq' => 'weekly',
                    'priority' => '0.5'
                ];
            }
        }

        // Optionally include named routes (can provide priority different)
        foreach ($routesInfo['names'] as $name => $info) {
            // skip api routes
            if (!empty($info['api'])) continue;
            $p = $info['path'];
            if (strpos($p, '{') !== false) continue;
            $loc = rtrim($base, '/') . $p;
            $paths[$p] = [
                'loc' => $loc,
                'lastmod' => date('c'),
                'changefreq' => 'weekly',
                'priority' => '0.6'
            ];
        }

        // Build XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        foreach ($paths as $p => $info) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($info['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</loc>\n";
            $xml .= "    <lastmod>{$info['lastmod']}</lastmod>\n";
            $xml .= "    <changefreq>{$info['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$info['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>' . PHP_EOL;
        return $xml;
    }
}