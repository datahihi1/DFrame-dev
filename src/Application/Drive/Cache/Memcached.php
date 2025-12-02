<?php

namespace DFrame\Application\Drive\Cache;

/**
 * Memcached Cache Driver by DFrame (Lazy Connection + Prefix + Safe API)
 */
class Memcached
{
    private \Memcached|null $client = null;
    private string $host;
    private int $port;
    private string $prefix;
    private int $defaultTtl;

    /**
     * Constructor
     */
    public function __construct(array $config)
    {
        $this->host       = env('MEMCACHED_HOST') ?? $config['host'] ?? '127.0.0.1';
        $this->port       = env('MEMCACHED_PORT') ?? $config['port'] ?? 11211;
        $this->prefix     = env('MEMCACHED_PREFIX') ?? $config['prefix'] ?? '';
        $this->defaultTtl = env('MEMCACHED_DEFAULT_TTL') ?? $config['default_ttl'] ?? 3600;
    }

    /**
     * Lazy connection: chỉ tạo khi thực sự cần
     */
    private function connect(): \Memcached
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $mem = new \Memcached();
        $mem->addServer($this->host, $this->port);

        // Kiểm tra kết nối thật
        $check = $mem->getVersion();
        if (!is_array($check) || empty($check)) {
            throw new \RuntimeException("Cannot connect to Memcached at {$this->host}:{$this->port}");
        }

        $this->client = $mem;
        return $this->client;
    }

    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->connect()->get($this->key($key));
        return ($value === false && $this->connect()->getResultCode() === \Memcached::RES_NOTFOUND)
            ? $default
            : $value;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->connect()->set(
            $this->key($key),
            $value,
            $ttl ?? $this->defaultTtl
        );
    }

    public function has(string $key): bool
    {
        $this->connect()->get($this->key($key));
        return $this->connect()->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    public function delete(string $key): bool
    {
        return $this->connect()->delete($this->key($key));
    }

    public function increment(string $key, int $by = 1): int|false
    {
        return $this->connect()->increment($this->key($key), $by);
    }

    public function decrement(string $key, int $by = 1): int|false
    {
        return $this->connect()->decrement($this->key($key), $by);
    }

    public function flush(): bool
    {
        return $this->connect()->flush();
    }
}
