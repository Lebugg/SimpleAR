<?php namespace SimpleAR\Database\Query;

class Delete extends Where
{
    public $type = 'delete';

    /**
     * This query is critical.
     *
     * @var bool true
     */
    protected static $_isCriticalQuery = true;

    public $from;
    public $using;
    public $where;

    protected function _newBuilder()
    {
        return new InsertBuilder();
    }
}
