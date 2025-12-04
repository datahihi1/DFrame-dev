<?php
namespace App\Model;

use DFrame\Application\DB;
use DFrame\Database\Traits\SoftDelete;

/**
 * Users model - represents the 'users' table in the database.
 */
class Users extends DB{
    use SoftDelete;
    protected $table = "users";
}
