<?php

namespace DFrame\Application\Drive\Cache;

class Redis
{
    private ?\Redis $client = null;

    private string $host;
    private int $port;
    private float $timeout;
    private ?string $auth;
    private string $prefix;
    private bool $debug;
    private int $defaultTTL;
    private bool $compression;

    public function __construct(array $config = [])
    {
        $this->host = env('REDIS_HOST') ?? $config['host'] ?? '127.0.0.1';
        $this->port = env('REDIS_PORT') ?? $config['port'] ?? 6379;
        $this->timeout = env('REDIS_TIMEOUT') ?? $config['timeout'] ?? 0.0;
        $this->auth = env('REDIS_PASSWORD') ?? $config['auth'] ?? null;
        $this->prefix = rtrim(env('REDIS_PREFIX') ?? $config['prefix'] ?? '', ':') . ':';
        $this->debug = env('REDIS_DEBUG') ?? $config['debug'] ?? false;
        $this->defaultTTL = env('REDIS_DEFAULT_TTL') ?? $config['ttl'] ?? 3600;
        $this->compression = env('REDIS_COMPRESSION') ?? $config['compression'] ?? false;
    }

    /** Lazy connection */
    private function connect(): void
    {
        if ($this->client !== null) {
            return;
        }

        $this->client = new \Redis();

        try {
            $this->client->connect($this->host, $this->port, $this->timeout);
            if ($this->auth) {
                $this->client->auth($this->auth);
            }
            if ($this->debug) {
                echo "[Redis] Connected to {$this->host}:{$this->port}\n";
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException("Redis connection failed: " . $e->getMessage());
        }
    }

    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    private function encode($value): string
    {
        $json = json_encode($value);

        return $this->compression
            ? gzcompress($json)
            : $json;
    }

    private function decode(?string $value)
    {
        if ($value === null || $value === false) {
            return null;
        }

        $json = $this->compression ? gzuncompress($value) : $value;

        return json_decode($json, true);
    }

    /** Set cache */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $this->connect();

        $ttl = $ttl ?? $this->defaultTTL;
        $key = $this->key($key);
        $data = $this->encode($value);

        try {
            $result = ($ttl > 0)
                ? $this->client->setex($key, $ttl, $data)
                : $this->client->set($key, $data);

            if ($this->debug) {
                echo "[Redis] SET {$key}, TTL={$ttl}\n";
            }

            return $result;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Get cache */
    public function get(string $key, $fallback = null, ?int $ttl = null)
    {
        $this->connect();
        $raw = $this->client->get($this->key($key));

        if ($raw === false) {
            if ($fallback !== null) {
                $this->set($key, $fallback, $ttl);
                return $fallback;
            }
            return null;
        }

        return $this->decode($raw);
    }

    /** Laravel-style remember() */
    public function remember(string $key, int $ttl, callable $callback)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $data = $callback();
        $this->set($key, $data, $ttl);

        return $data;
    }

    /** rememberForever() */
    public function rememberForever(string $key, callable $callback)
    {
        return $this->remember($key, 0, $callback);
    }

    public function delete(string $key): bool
    {
        $this->connect();
        return (bool) $this->client->del($this->key($key));
    }

    public function exists(string $key): bool
    {
        $this->connect();
        return $this->client->exists($this->key($key)) > 0;
    }

    /**
     * Clear by prefix using SCAN (safe for production)
     */
    public function clearPrefix(): void
    {
        $this->connect();

        $iterator = null;
        $pattern = $this->prefix . '*';

        while ($keys = $this->client->scan($iterator, $pattern)) {
            foreach ($keys as $key) {
                $this->client->del($key);
            }
        }

        if ($this->debug) {
            echo "[Redis] Cleared prefix: {$this->prefix}\n";
        }
    }

    public function getClient(): \Redis
    {
        $this->connect();
        return $this->client;
    }
}
