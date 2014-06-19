<?php namespace SimpleAR\Database\Query;
/**
 * This file contains the Insert class.
 *
 * @author Lebugg
 */

use \SimpleAR\Facades\DB;
use \SimpleAR\Database\Compiler\InsertCompiler;
use \SimpleAR\Database\Builder\InsertBuilder;

/**
 * This class handles INSERT statements.
 */
class Insert extends \SimpleAR\Database\Query
{
    public $type = 'insert';

    public $into;
    public $columns;
    public $values;

    /**
     * Last inserted ID getter.
     *
     * @see Database\Connection::lastInsertId()
     *
     * @return mixed
     */
    public function insertId()
    {
        return $this->getConnection()->lastInsertId();
    }

    public function rootModel($class)
    {
        parent::rootModel($class);
        $this->into = $class::table()->name;
    }

    public function rootTable($table)
    {
        parent::rootTable($table);
        $this->into = $table;
    }

    public function getValues()
    {
        if (is_array($this->values[0]))
        {
            // We also need to flatten value array.
            return call_user_func_array('array_merge', $this->values);
        }

        return $this->values;
    }

    protected function _newBuilder()
    {
        return new InsertBuilder();
    }
}
