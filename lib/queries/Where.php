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
    
    protected function _initContext($sRoot)
    {
        parent::_initContext($sRoot);

        $this->_context->arborescence = new Arborescence(
            $this->_context->rootModel,
            $this->_context->rootTable
        );
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
     * @return string
     *
     * @see Arborescence::process()
     */
    protected function _join()
    {
        return $this->_context->arborescence->process();
    }


    /**
     * Compute WHERE clause.
     *
     * @return string
     *
     * @see Condition::arrayToSql()
     */
    protected function _where()
    {
        if ($this->_conditions && ! $this->_conditions->isEmpty())
        {
            // We made all wanted treatments; get SQL out of Condition array.
            list($sql, $values) = $this->_conditions->toSql();

            // Add condition values. $values is a flatten array.
            // @see Condition::flattenValues()
            $this->_values = array_merge($this->_values, $values);

            return $sql ? ' WHERE ' . $sql : '';
        }

        return '';
    }

}
