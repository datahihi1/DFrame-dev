<?php
namespace DFrame\Database\Traits;

/**
 * #### SoftDelete trait
 *
 * This trait provides functionality to mark an object as deleted without actually removing it from the database.
 * 
 * It allows for soft deletion and restoration of the object.
 */
trait SoftDelete
{
    protected function checkDeletedAtColumn()
    {
        // Lấy adapter và tên bảng từ $this
        $adapter = $this->adapter ?? ($this->db ?? null);
        $table = $this->table ?? null;
        if (!$adapter || !$table) {
            throw new \RuntimeException("SoftDelete: Adapter or table not set in model.");
        }

        $adapterClass = get_class($adapter);
        if (stripos($adapterClass, 'sqlite') !== false) {
            $sql = "PRAGMA table_info(\"{$table}\")";
            $res = $adapter->query($sql);
            $rows = $adapter->fetchAll($res);
            foreach ($rows as $r) {
                $name = $r['name'] ?? $r['Name'] ?? null;
                $type = strtolower($r['type'] ?? '');
                $nullable = !($r['notnull'] ?? 1);
                if ($name === 'deleted_at') {
                    if (strpos($type, 'date') === false && strpos($type, 'time') === false) {
                        throw new \RuntimeException("SoftDelete: 'deleted_at' column must be DATETIME or TIMESTAMP, got '$type'");
                    }
                    if (!$nullable) {
                        throw new \RuntimeException("SoftDelete: 'deleted_at' column must be nullable.");
                    }
                    return true;
                }
            }
        } else {
            $sql = "SHOW COLUMNS FROM `{$table}` LIKE 'deleted_at'";
            $res = $adapter->query($sql);
            $rows = $adapter->fetchAll($res);
            if (!empty($rows)) {
                $row = $rows[0];
                $type = strtolower($row['Type'] ?? '');
                $nullable = strtolower($row['Null'] ?? '') === 'yes';
                if (strpos($type, 'date') === false && strpos($type, 'time') === false) {
                    throw new \RuntimeException("SoftDelete: 'deleted_at' column must be DATETIME or TIMESTAMP, got '$type'");
                }
                if (!$nullable) {
                    throw new \RuntimeException("SoftDelete: 'deleted_at' column must be nullable.");
                }
                return true;
            }
        }
        throw new \RuntimeException("SoftDelete: 'deleted_at' column not found in table '{$table}'.");
    }

    /**
     * The timestamp when the object was soft deleted
     *
     * @var string|null
     */
    public function softDelete()
    {
        $this->checkDeletedAtColumn();
        if ($this->isDeleted()) {
            return false;
        }
        $this->deleted_at = date('Y-m-d H:i:s');
        return $this->save();
    }
    /**
     * Restore the object from soft deletion
     *
     * @return bool
     */
    public function restore($id = null)
    {
        $this->checkDeletedAtColumn();
        if (!$this->isDeleted()) {
            return false;
        }
        $this->deleted_at = null;
        return $this->save();
    }
    /**
     * Check if the object is soft deleted
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted_at !== null;
    }
}