<?php

namespace DFrame\Application;

use DFrame\Database\Adapter\MysqliAdapter;
use DFrame\Database\Adapter\Sqlite3Adapter;
use DFrame\Database\Adapter\PdoMysqlAdapter;
use DFrame\Database\Adapter\PdoSqliteAdapter;
use DFrame\Database\DatabaseManager;

/**
 * #### Database handler
 * Database proxy to Mapper/Builder.
 *
 * The actual implementation behind this model depends on env('DB_DESIGN'):
 * - 'builder': forwards to Query Builder with fluent methods
 * - 'mapper': forwards to Mapper with CRUD helpers
 *
 * @method \Craft\Database\Interfaces\BuilderInterface table(string $table)
 * @method \Craft\Database\Interfaces\BuilderInterface select($columns = ['*'])
 * @method \Craft\Database\Interfaces\BuilderInterface where($column, $value = null, $operator = null)
 * @method \Craft\Database\Interfaces\BuilderInterface insert(array $data)
 * @method \Craft\Database\Interfaces\BuilderInterface update(array $data)
 * @method \Craft\Database\Interfaces\BuilderInterface delete()
 * @method mixed execute()
 * @method array fetchAll()
 * @method mixed fetch(string $type = 'assoc')
 * @method mixed first(string $type = 'assoc')
 * @method array get()
 *
 * @method array|null find($id)
 * @method array all()
 * @method array where($column, $value, $operator)
 * @method mixed create(array $data)
 * @method mixed update($id, array $data)
 * @method mixed delete($id)
 * @method mixed insertGetId(array $data)
 * @method bool executeUpdate(array $data)
 * @method bool executeDelete()
 */
class DB extends DatabaseManager
{
    /**
     * Summary of table
     * @var string
     */
    protected $table;

    /**
     * Initialize the DB instance.
     */
    public function __construct()
    {
        parent::__construct();
        // Chỉ khởi tạo mapper nếu $table đã có giá trị
        if ($this->table) {
            $this->mapper = $this->getMapper($this->table);
        }
    }

    /**
     * Set the table for the query.
     * @param string $table
     * @return static
     */
    public static function table(string $table): self
    {
        $instance = new static();
        $instance->table = $table;
        $instance->mapper = $instance->getMapper($table);
        return $instance;
    }

    /**
     * Handle dynamic method calls into the model.
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->mapper, $method], $args);
    }

    /**
     * Handle dynamic static method calls into the model.
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $instance = new static();
        return call_user_func_array([$instance->mapper, $method], $args);
    }

    /**
     * Get the database adapter instance.
     * @return MysqliAdapter|PdoMysqlAdapter|PdoSqliteAdapter|Sqlite3Adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }
}
