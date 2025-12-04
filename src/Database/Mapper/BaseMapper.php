<?php
namespace DFrame\Database\Mapper;

use DFrame\Database\Interfaces\MapperInterface;

abstract class BaseMapper implements MapperInterface {
    protected $adapter;
    protected $table;

    // cache for existence of deleted_at column
    protected ?bool $hasDeletedAt = null;

    public function __construct($adapter, $table) {
        $this->adapter = $adapter;
        $this->table = $table;
    }

    /**
     * Detect whether the table has a deleted_at column.
     */
    protected function hasDeletedAt(): bool
    {
        if ($this->hasDeletedAt !== null) {
            return $this->hasDeletedAt;
        }

        try {
            // Kiểm tra adapter là MySQL hay SQLite
            $adapterClass = get_class($this->adapter);
            if (stripos($adapterClass, 'sqlite') !== false) {
                // SQLite
                $sql = "PRAGMA table_info(\"{$this->table}\")";
                $res = $this->adapter->query($sql);
                $rows = $this->adapter->fetchAll($res);
                foreach ($rows as $r) {
                    $name = $r['name'] ?? $r['Name'] ?? $r['field'] ?? $r['Field'] ?? null;
                    if ($name === 'deleted_at') {
                        $this->hasDeletedAt = true;
                        return true;
                    }
                }
            } else {
                // MySQL
                $sql = "SHOW COLUMNS FROM `{$this->table}` LIKE 'deleted_at'";
                $res = $this->adapter->query($sql);
                $rows = $this->adapter->fetchAll($res);
                if (!empty($rows)) {
                    $this->hasDeletedAt = true;
                    return true;
                }
            }
        } catch (\Throwable $e) {
            $this->hasDeletedAt = false;
            return false;
        }

        $this->hasDeletedAt = false;
        return false;
    }
}