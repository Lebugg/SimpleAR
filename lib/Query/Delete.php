<?php
/**
 * This file contains the Update class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

/**
 * This class handles UPDATE statements.
 */
class Delete extends \SimpleAR\Query\Where
{
    /**
     * This query is critical.
     *
     * @var bool true
     */
    protected static $_isCriticalQuery = true;

    protected static $_options = array('conditions');

    protected $_from;

    protected static $_components = array(
        'from',
        'where'
    );

    public function __construct($root)
    {
        parent::__construct($root);
        
        $this->_from = $this->_context->rootTableName;
    }

    protected function _compileFrom()
    {
        $this->_sql = 'DELETE ';

        $c = $this->_context;
		$this->_sql .= $c->useAlias
            ? '`' . $c->rootTableAlias . '` FROM `' .  $c->rootTableName . '` AS `' .  $c->rootTableAlias . '`'
            : 'FROM `' . $c->rootTableName . '`'
            ;

        // Equivalent JOIN clause for DELETE queries.
        $join = $this->_join();
        $this->_sql .= $join ? ' USING ' . $join : '';
    }

    protected function _initContext($root)
    {
        parent::_initContext($root);

        $this->_context->useAlias = false;
    }
}
