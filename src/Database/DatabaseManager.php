<?php

namespace DFrame\Database;

use DFrame\Database\Adapter\MysqliAdapter;
use DFrame\Database\Adapter\PdoMysqlAdapter;
use DFrame\Database\Adapter\Sqlite3Adapter;
use DFrame\Database\Adapter\PdoSqliteAdapter;

use DFrame\Database\Mapper\MysqlMapper;
use DFrame\Database\Mapper\SqliteMapper;

use DFrame\Database\QueryBuilder\MysqlBuilder;
use DFrame\Database\QueryBuilder\SqliteBuilder;

/**
 * #### DatabaseManager class
 * 
 * Manages database connections and mappers/builders based on configuration.
 * 
 * It supports different database drivers and design patterns:
 * - Drivers: `mysqli`, `pdo_mysql`, `sqlite3`, `pdo_sqlite`, and more if needed
 * - Designs: `mapper` (active record style) and `builder` (query builder style)
 */
class DatabaseManager
{
    /** Supported database drivers */
    protected const SUPPORTED_DRIVERS = ['mysqli', 'pdo_mysql', 'sqlite3', 'pdo_sqlite'];
    /** Supported database designs */
    protected const SUPPORTED_DESIGNS = ['mapper', 'builder'];
    /** Adapter instance */
    protected $adapter;
    protected $mapper;
    protected $builder;
    /** Mapper or Builder class name based on design */
    protected $mapperClass;

    public function __construct()
    {
        $driver = env('DB_DRIVER');
        if (!$driver) {
            throw new \InvalidArgumentException("DB_DRIVER is not set.");
        }
        if (!in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw new \InvalidArgumentException("Invalid DB_DRIVER: $driver" . ". Accepts: " . implode(', ', self::SUPPORTED_DRIVERS) . ".");
        }

        $design = env('DB_DESIGN');
        if (!$design) {
            throw new \InvalidArgumentException("DB_DESIGN is not set.");
        }
        if (!in_array($design, self::SUPPORTED_DESIGNS, true)) {
            throw new \InvalidArgumentException("Invalid DB_DESIGN: $design" . ". Accepts: " . implode(', ', self::SUPPORTED_DESIGNS) . ".");
        }

        $config = $this->getConfig($driver);

        switch ($driver) {
            case 'mysqli':
                $this->adapter = new MysqliAdapter();
                break;
            case 'sqlite3':
                $this->adapter = new Sqlite3Adapter();
                break;
            case 'pdo_sqlite':
                $this->adapter = new PdoSqliteAdapter();
                break;
            case 'pdo_mysql':
            default:
                $this->adapter = new PdoMysqlAdapter();
        }
        $this->adapter->connect($config);

        if ($design === 'mapper') {
            if (in_array($driver, ['mysqli', 'pdo_mysql'])) {
                $this->mapperClass = MysqlMapper::class;
            } elseif (in_array($driver, ['sqlite3', 'pdo_sqlite'])) {
                $this->mapperClass = SqliteMapper::class;
            }
        } elseif ($design === 'builder') {
            if (in_array($driver, ['mysqli', 'pdo_mysql'])) {
                $this->mapperClass = MysqlBuilder::class;
            } elseif (in_array($driver, ['sqlite3', 'pdo_sqlite'])) {
                $this->mapperClass = SqliteBuilder::class;
            }
        }
    }

    /**
     * Get database configuration based on driver
     * @param mixed $driver
     * 
     */
    protected function getConfig($driver)
    {
        if (strpos($driver, 'sqlite') !== false) {
            return [
                'database' => env('DB_NAME') . '.db',
            ];
        } else if (strpos($driver, 'mysql') !== false) {
            return [
                'host'     => env('DB_HOST'),
                'port'     => env('DB_PORT'),
                'user'     => env('DB_USER'),
                'password' => env('DB_PASS') ?? null,
                'database' => env('DB_NAME'),
            ];
        }
    }

    /**
     * Get the current database adapter
     * @return MysqliAdapter|PdoMysqlAdapter|PdoSqliteAdapter|Sqlite3Adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Get a mapper instance for a specific table
     * 
     *  **Note:**
     * - For 'mapper': the Mapper class receives ($adapter, $table)
     * - For 'builder': the Builder also receives ($adapter, $table) and executes through the adapter
     * @param mixed $table
     * @return object
     */
    public function getMapper($table)
    {
        return new $this->mapperClass($this->adapter, $table);
    }
}
