<?php namespace SimpleAR\Database\Query;

use \SimpleAR\Database\Builder\DeleteBuilder;

class Delete extends Where
{
    public $type = 'delete';

    /**
     * This query is critical.
     *
     * @var bool true
     */
    protected static $_isCriticalQuery = true;

    public $deleteFrom;
    public $using;
    public $where;

    protected function _newBuilder()
    {
        return new DeleteBuilder();
    }
}
