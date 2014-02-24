<?php namespace SimpleAR\Query;
/**
 * This file contains the Update class.
 *
 * @author Lebugg
 */

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

    protected $_table;
    protected $_set;

    protected static $_components = array(
        'table',
        'set',
        'where',
    );

    public function __construct($root)
    {
        parent::__construct($root);

        $this->_table = $root;
    }

    protected function _compileTable()
    {
        $this->_sql = 'UPDATE ';

        $c = $this->_context;
		$this->_sql .= $c->useAlias
            ? '`' . $c->rootTableName . '` `' .  $c->rootTableAlias . '`'
            : '`' . $c->rootTableName . '`'
            ;
    }

    protected function _compileSet()
    {
        $this->_sql .= ' SET ' . implode(' = ?, ', $this->_set) . ' = ?';
    }

    protected function _handleOption(Option $option)
    {
        switch (get_class($option))
        {
            case 'SimpleAR\Query\Option\Fields':
                $this->_set = $option->columns;
                break;
            case 'SimpleAR\Query\Option\Values':
                $this->_values = $option->values;
                break;
            default:
                parent::_handleOption($option);
        }
    }
}
