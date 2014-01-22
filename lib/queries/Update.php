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
class Update extends \SimpleAR\Query\Where
{
    /**
     * This query is critical.
     *
     * @var bool true
     */
    protected static $_isCriticalQuery = true;

    protected static $_options = array('conditions', 'fields', 'values');

    protected function _compile()
    {
		$this->_sql = $this->_context->useAlias
            ? 'UPDATE ' . $this->_context->rootTableName . ' ' .  $this->_context->rootTableAlias
            : 'UPDATE ' . $this->_context->rootTableName
            ;

        $this->_sql .= ' SET ' . implode(' = ?, ', (array) $this->_columns) . ' = ?';
		$this->_sql .= $this->_where();
    }

    protected function _values(Option $option)
    {
        $this->_values = array_merge($this->_values, $option->build());
    }

    protected function _fields(Option $option)
    {
        $this->_columns = array_merge($this->_columns, $option->build());
    }

    protected function _initContext($sRoot)
    {
        parent::_initContext($sRoot);
    }
}
