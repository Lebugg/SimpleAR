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
        $this->_conditions = array_merge($this->_conditions, $option->build());
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
        $this->_conditions = array_merge($this->_conditions, $option->build());
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
        // We made all wanted treatments; get SQL out of Condition array.
        list($sql, $values) = Condition::arrayToSql($this->_conditions, $this->_context->useAlias, $this->_context->useModel);

        // Add condition values. $aValues is a flatten array.
        // @see Condition::flattenValues()
        $this->_values = array_merge($this->_values, $values);

		return $sql ? ' WHERE ' . $sql : '';
    }

}
