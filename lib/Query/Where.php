<?php namespace SimpleAR\Query;
/**
 * This file contains the Where class.
 *
 * @author Lebugg
 */

use \SimpleAR\Query;

use SimpleAR\Query\Condition\ExistsCondition;
use SimpleAR\Query\Condition\RelationCondition;
use SimpleAR\Query\Condition\SimpleCondition;

/**
 * This class is the super classe for queries that handle conditions (WHERE statements).
 */
abstract class Where extends Query
{
    protected $_where;
	protected $_groups = array();
    protected $_havings = array();

    protected function _compileWhere()
    {
		$this->_sql .= $this->_where();
    }

    protected function _handleOption(Option $option)
    {
        switch (get_class($option))
        {
            case 'SimpleAR\Query\Option\Conditions':
            case 'SimpleAR\Query\Option\Has':
                if ($this->_where)
                {
                    $this->_where->combine($option->conditions);
                }
                else
                {
                    $this->_where = $option->conditions;
                }

                if ($option->havings)
                {
                    $this->_havings  = array_merge($this->_havings, $option->havings);
                }

                if ($option->groups)
                {
                    $this->_groups   = array_merge($this->_groups,  $option->groups);
                }

                if ($option->columns)
                {
                    $this->_columns  = array_merge($this->_columns, $option->columns);
                }
                break;
            default:
                parent::_handleOption($option);
        }
    }

    /**
     * Initialize query context from within Where scope.
     *
     * It initializes an Arborescence if _context->useModel is true.
     *
     * @override
     */
    protected function _initContext($root)
    {
        parent::_initContext($root);

        // Arborescence can be used only when we are using models.
        if ($this->_context->useModel)
        {
            $this->_context->arborescence = new Arborescence(
                $this->_context->rootModel,
                $this->_context->rootTable
            );
        }
    }

    /**
     * Fire arborescence processing.
     *
     * If _context->useModel is false, _context->arborescence does not exist, and this 
     * function will *always* return the empty string ('').
     *
     * @return string
     *
     * @see Arborescence::toSql()
     */
    protected function _join()
    {
        if (! isset($this->_context->arborescence))
        {
            return '';
        }

        return $this->_context->arborescence->toSql();
    }


    /**
     * Compute WHERE clause.
     *
     * @return string
     *
     * @see Condition::arra_toSql()
     */
    protected function _where()
    {
        if ($this->_where && ! $this->_where->isEmpty())
        {
            // We made all wanted treatments; get SQL out of Condition array.
            $c = $this->_context;
            list($sql, $values) = $this->_where->toSql($c->useAlias, $c->useModel);

            // Add condition values. $values is a flatten array.
            // @see Condition::flatte_values()
            $this->_values = array_merge($this->_values, $values);

            return $sql ? ' WHERE ' . $sql : '';
        }

        return '';
    }

}
