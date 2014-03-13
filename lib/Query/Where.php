<?php namespace SimpleAR\Query;
/**
 * This file contains the Where class.
 *
 * @author Lebugg
 */

use \SimpleAR\Query;
use \SimpleAR\Query\Arborescence as Arbo;
use \SimpleAR\Exception;
use \SimpleAR\Facades\DB;

/**
 * This class is the super classe for queries that handle conditions (WHERE statements).
 */
abstract class Where extends Query
{
    protected $_arborescence = null;

    protected $_where;
	protected $_groups = array();
    protected $_havings = array();

    /**
     * Join a relation to the arborescence.
     *
     * @param  string|array $relation The relation to join
     * @return $this
     */
    public function _join($relation, $type = Arbo::JOIN_INNER, $force = true)
    {
        $this->_useAlias = true;

        if ($this->_useModel)
        {
            return $this->_arborescence->add($relation, $type, true);
        }

        throw new Exception('Model classes must be used in order to use "join()".');
    }

    public function join($relation, $type = Arbo::JOIN_INNER, $force = true)
    {
        $this->_join($relation, $type, $force);

        return $this;
    }

    protected function _compileWhere()
    {
		$this->_sql .= $this->_where();
    }

    protected function _buildConditions(Option\Conditions $o)
    {
        foreach ($o->groups as $group)
        {
            $this->_buildGroupOption($group); 
        }

        foreach ($o->aggregates as $aggregate)
        {
            $this->_buildAggregate($aggregate); 
        }

        foreach ($o->havings as $having)
        {
            $this->_buildHaving($having);
        }

        foreach ($o->joins as $join)
        {
            $this->_join($join);
        }

        if ($this->_where)
        {
            $this->_where->merge($o->where);
        }
        else
        {
            $this->_where = $o->where;
        }
    }

    protected function _buildHas(Option\Has $o)
    {
        return $this->_buildConditions($o);
    }

    /**
     * Initialize query context from within Where scope.
     *
     * It initializes an Arborescence if _useModel is true.
     *
     * @override
     */
    public function rootModel($root)
    {
        parent::rootModel($root);

        $this->_arborescence = new Arborescence($this->_model);
        $this->_arborescence->alias = $this->_rootAlias;

        return $this;
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
            list($sql, $values) = $this->_where->compile($this->_arborescence, $this->_useAlias, $this->_useModel);

            // Add condition values. $values is a flatten array.
            // @see Condition::flatte_values()
            $this->_values = array_merge($this->_values, $values);

            return $sql ? ' WHERE ' . $sql : '';
        }

        return '';
    }

}
