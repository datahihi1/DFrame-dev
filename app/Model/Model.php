<?php
namespace App\Model;

use DFrame\Database\DatabaseManager;

/**
 * Dynamic model proxy to Mapper/Builder.
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
 * @method \Craft\Database\Interfaces\BuilderInterface execute()
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
class Model extends DatabaseManager
{
    /**
     * Summary of table
     * @var string
     */
    protected $table;

    public function __construct()
    {
        parent::__construct();
        $this->mapper = $this->getMapper($this->table);
    }

    /**
     * Handle dynamic method calls into the model.
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        // Adapt common method signatures between Mapper and Builder:
        // - update($id, array $data)  => Mapper style
        // - update(array $data)       => Builder style
        // - delete($id)               => Mapper style
        // - delete()                  => Builder style
        if ($method === 'update' && count($args) === 2 && (is_scalar($args[0]) || is_string($args[0])) && is_array($args[1])) {
            // If mapper's update accepts 2 params, call directly (mapper)
            if (method_exists($this->mapper, 'update')) {
                $rm = new \ReflectionMethod($this->mapper, 'update');
                if ($rm->getNumberOfParameters() > 1) {
                    return call_user_func_array([$this->mapper, 'update'], $args);
                }
            }
            // Fallback for builder: where('id', $id)->update($data)
            [$id, $data] = $args;
            return $this->mapper->where('id', $id)->update($data);
        }

        if ($method === 'delete' && count($args) === 1 && (is_scalar($args[0]) || is_string($args[0]))) {
            if (method_exists($this->mapper, 'delete')) {
                $rm = new \ReflectionMethod($this->mapper, 'delete');
                if ($rm->getNumberOfParameters() > 0) {
                    return call_user_func_array([$this->mapper, 'delete'], $args);
                }
            }
            $id = $args[0];
            return $this->mapper->where('id', $id)->delete();
        }

        return call_user_func_array([$this->mapper, $method], $args);
    }

    /**
     * Handle dynamic static method calls into the model.
     * Mirrors the instance logic to support static proxying (Model::update($id, $data)).
     */
    public static function __callStatic($method, $args)
    {
        $instance = new static();

        // reuse instance adaptation logic
        return $instance->__call($method, $args);
    }
}