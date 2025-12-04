<?php

namespace App\Model;

use DFrame\Application\DB;
use DFrame\Database\Traits\SoftDelete;

class Products extends DB{
    use SoftDelete;
    protected $table = "products";
}