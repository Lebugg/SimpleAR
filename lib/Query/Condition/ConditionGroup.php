<?php
/**
 * This file contains the ConditionGroup class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query\Condition;

use SimpleAR\Query\Condition;

use \SimpleAR\Exception;


class ConditionGroup extends Condition
{
    const T_AND = 0;
    const T_OR  = 1;

    private $_type;

    /**
     *
     * @var array
     */
    protected $_elements = array();

    public function __construct($type)
    {
        if ($type !== self::T_AND && $type !== self::T_OR)
        {
            throw new Exception('Unknown condition group type.');
        }

        $this->_type = $type;
    }

    /**
     * Add a condition or a condition group to the current group.
     *
     * @param  Condition $c The condition to add.
     * @return void
     */
    public function add(Condition $c)
    {
        $this->_elements[] = $c;
    }
    /*
    public function add(Condition $c)
    {
        if (! $c instanceof \SimpleAR\Query\Condition\ConditionGroup)
        {
            // We check to see if we can combine the condition to add with an
            // existing condition. We can combine conditions if they apply on same
            // Relation.
            foreach ($this->_elements as $element)
            {
                // I don't think we want this.
                if ($element instanceof \SimpleAR\Query\Condition\ConditionGroup)
                {
                    continue;
                }

                // @see Condition::canMergeWith()
                if ($element->canMergeWith($c))
                {
                    $element->merge($c);
                    return;
                }
            }
        }

        // But, basically, we just want to add an item to the element array.
        $this->_elements[] = $c;
    }
    */

    /**
     * Merge two condition groups or add a condition to the condition group
     * ($this).
     *
     * @param Condition $c The condition or condition group to merge with the
     * current condition group.
     *
     * @return void.
     */
    public function merge(Condition $c)
    {
        if ($c instanceof \SimpleAR\Query\Condition\ConditionGroup)
        {
            foreach ($c->_elements as $element)
            {
                $this->add($element);
            }
        }
        else
        {
            $this->add($c);
        }
    }

    public function isEmpty()
    {
        return ! $this->_elements;
    }

    public function elements()
    {
        return $this->_elements;
    }

    public function type()
    {
        return $this->_type;
    }

    public function setTableAlias($alias)
    {
        foreach ($this->_elements as $element)
        {
            $element->setTableAlias($alias);
        }
    }

    public function toSql($useAliases = true, $toColumn = true)
    {
        $sql = array();
        $val = array();

        if (! $this->_elements)
        {
            throw new Exception('ConditionGroup has no element.');
        }

        foreach ($this->_elements as $element)
        {
            $res = $element->toSql($useAliases, $toColumn);

            $sql[] = $res[0];
            $val   = array_merge($val, $res[1]);
        }

        $operator = $this->_type === self::T_AND ? ' AND ' : ' OR ';
        $sql      = '(' . implode($operator, $sql) . ')';

        return array($sql, $val);
    }
}
