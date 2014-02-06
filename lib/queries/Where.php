<?php
/**
 * This file contains the Where class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

use SimpleAR\Query\Condition\ExistsCondition;
use SimpleAR\Query\Condition\RelationCondition;
use SimpleAR\Query\Condition\SimpleCondition;

/**
 * This class is the super classe for queries that handle conditions (WHERE statements).
 */
abstract class Where extends \SimpleAR\Query
{
	protected $_conditions = array();

    protected function _conditions(Option $option)
    {
        // @see Option\Conditions::build() to check returned array format.
        $res = $option->build();

        if ($this->_conditions)
        {
            $this->_conditions->combine($res['conditions']);
        }
        else
        {
            $this->_conditions = $res['conditions'];
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

    protected function _has(Option $option)
    {
        // @see Option\Has::build() to check returned array format.
        $res = $option->build();

        if ($this->_conditions)
        {
            $this->_conditions->combine($res['conditions']);
        }
        else
        {
            $this->_conditions = $res['conditions'];
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
        if ($this->_conditions && ! $this->_conditions->isEmpty())
        {
            // We made all wanted treatments; get SQL out of Condition array.
            list($sql, $values) = $this->_conditions->toSql();

            // Add condition values. $values is a flatten array.
            // @see Condition::flatte_values()
            $this->_values = array_merge($this->_values, $values);

            return $sql ? ' WHERE ' . $sql : '';
        }

        return '';
    }

}
