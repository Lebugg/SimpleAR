<?php namespace SimpleAR\Database\Condition;
/**
 * This file contains the ConditionGroup class.
 *
 * @author Lebugg
 */

use \SimpleAR\Database\Condition;
use \SimpleAR\Database\Condition\Group;
use \SimpleAR\Database\Arborescence;
use \SimpleAR\Exception;

class Group extends Condition
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
        if ($c instanceof Group)
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

    public function compile(Arborescence $node, $useAlias = false, $toColumn = false)
    {
        $sql = array();
        $val = array();

        if (! $this->_elements)
        {
            throw new Exception('ConditionGroup has no element.');
        }

        foreach ($this->_elements as $element)
        {
            $res = $element->compile($node, $useAlias, $toColumn);

            $sql[] = $res[0];
            $val   = array_merge($val, $res[1]);
        }

        $operator = $this->_type === self::T_AND ? ' AND ' : ' OR ';
        $sql      = '(' . implode($operator, $sql) . ')';

        return array($sql, $val);
    }
}
