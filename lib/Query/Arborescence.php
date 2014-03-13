<?php
namespace SimpleAR\Query;

use \SimpleAR\Query\Condition;
use \SimpleAR\Relation;
use \SimpleAR\Exception;

/**
 * This class modelizes an arborescence of nodes that represent a join between
 * two models. This arborescence is used to construct joins and conditions of
 * Where queries.
 *
 * An Arborescence object is equivalent to a node of this arborescence:
 *
 * Example:
 * --------
 *
 * Root --> N1 --> N2
 *      `-> N3
 *      `-> N4 --> N5 --> N6
 *
 *
 * In this pretty schema, Root and the Ni are all Arborescence objects.
 */
class Arborescence
{
    /**
     * The parent node of the current node.
     *
     * @var Arborescence
     */
    public $parent;

    /**
     * A relation object.
     *
     * @var Relation
     */
    public $relation;

    public $alias;

    /**
     * An array of conditions that is used to figure out if the node should be
     * joined when it is a leaf (Sometimes, it is not useful to compute the
     * join).
     *
     * @var array
     */
    public $conditions = array();

    /**
     * The type of join that should be computed (INNER, LEFT, RIGHT, FULL).
     *
     * @var int
     */
    public $joinType;

    /**
     * Should the node be joined in any case?
     *
     * @var bool
     */
    public $forceJoin = false;

    /**
     * Children of the current node.
     *
     * @var array of Arborescence objects
     */
    public $children = array();

    /**
     * Inner join type.
     */
    const JOIN_INNER = 30;

    /**
     * Left outer join type.
     */
    const JOIN_LEFT  = 20;

    /**
     * Right outer join type.
     */
    const JOIN_RIGHT = 21;

    /**
     * Full outer join type.
     */
    const JOIN_OUTER = 10;

    /**
     * Associate an join type to the corresponding SQL keyword(s).
     *
     * @var array
     */
    private static $_joinTypes = array(
        self::JOIN_INNER => 'INNER',
        self::JOIN_LEFT  => 'LEFT',
        self::JOIN_RIGHT => 'RIGHT',
        self::JOIN_OUTER => 'OUTER',
    );

    /**
     * Constructor.
     *
     * @param string        $relation  The name of the relation associated to the current node.
     * @param int           $joinType  The type of the join that should be performed.
     * @param Arborescence  $parent    The parent node.
     * @param bool          $forceJoin Is the join mandatory?
     */
    public function __construct(
        $model,
        Relation $relation     = null,
        $joinType              = self::JOIN_INNER,
        Arborescence $parent   = null,
        $forceJoin             = false
    ) {
        $this->model     = $model;
        $this->table     = $model::table();

        $this->relation  = $relation;
        $this->joinType  = $joinType;

        $this->forceJoin = $forceJoin;

        $this->parent    = $parent;
        $this->children  = array();

        if ($this->parent !== null)
        {
            $this->alias = $this->parent->alias . '.' . $this->relation->name;
        }
    }

    /**
     * Add a relation array into the arborescence.
     *
     * @param array|string $relations Array containing relations ordered by depth to
     * add to arborescence. Can be a string (<=> one relation array shortcut).
     * @param int $joinType The type of join to use to join this relation.
     * Default: inner join.
     * @param bool $forceJoin Does relation *must* be joined?
     *
     * @return the last added or accessed node. It allows calling function to
     * get more information about its context.
     */
    public function add($relations, $joinType = self::JOIN_INNER, $forceJoin = false)
    {
        // If no relation to add, return 
        if (! $relations)
        {
            return $this;
        }

        // Allow string for first parameter.
        if (is_string($relations))
        {
            $relations = explode('/', $relations);
        }

        // NOTE: "n" prefix in variable names means stands for "node", that is
        // an Arborescence instance.
        $nCurrent  = $this;
        $nPrevious = null;

        $cm       = $this->model;
        $relation = null;

        // Foreach relation to add, set some "config" elements.
        //
        // $relation is a relation name.
        foreach ($relations as $relation)
        {
            $nPrevious = $nCurrent;
            $relation  = $cm::relation($relation);
            $cm        = $relation->lm->class;

            // Add a new node if needed.
            if (! $nCurrent->hasChild($relation->name))
            {
                // @see Arborescence::__construct().
                $new = new self($cm, $relation, $joinType, $nCurrent, $forceJoin);
                $nCurrent->addChild($new);

                // Go forward.
                $nCurrent = $new;
            }
            // Already present? We may have to change some values.
            else
            {
                $nCurrent = $nCurrent->getChild($relation->name);
                $nCurrent->updateJoinType($joinType);
            }

            // Force join on last node if required.
            $nCurrent->forceJoin = $forceJoin || $nCurrent->forceJoin;
        }

        // Return arborescence leaf.
        return $nCurrent;
    }

    public function find(array $relations)
    {
        $nCurrent = $this;
        foreach ($relations as $rel)
        {
            if ($nCurrent->hasChild($rel))
            {
                $nCurrent = $nCurrent->getChild($rel);
            }
            else
            {
                throw new Exception('Cannot find "' . implode('/', $relations)
                        . '". Current: "' . $rel . '".');
            }
        }

        return $nCurrent;
    }


    /**
     * Transform the arborescence to SQL JOIN clause.
     *
     * This is a recursive function, it calls itself on each node.
     *
     * @return string
     */
	public function toSql()
	{
        // The SQL string.
        $res = '';

		foreach ($this->children as $relationName => $next)
		{
			$relation = $next->relation;

            // We *have to* to join table if it is not the last relation.
            //
            // "force" bypasses the last relation condition. True typically when
            // we have to join this table for a "with" option.
            if ($next->children || $next->forceJoin)
            {
                $res .= $relation->joinLinkedModel($this->alias, $next->alias, self::$_joinTypes[$next->joinType]);
            } 

            // If some conditions need to be built on this node, join it as
            // last.
            elseif ($next->conditions)
            {
                // $s is false if joining table was not necessary.
                if ($s = $relation->joinAsLast($next->conditions, $this->alias, $next->alias, self::$_joinTypes[$next->joinType]));
                {
                    $res .= $s;
                }
            }

            // Go through arborescence.
            if ($next->children)
            {
				$res .= $next->toSql($next->alias);
            }

		}

        return $res;
	}

    /**
     * Add a child to children list.
     *
     * I made a function for this because I think the way to add children may
     * change.
     *
     * @param Arborescence $child The child to add.
     *
     * @return void
     */
    public function addChild(Arborescence $child)
    {
        $child->parent = $this;

        $this->children[$child->relation->name] = $child;
    }

    /**
     * Add a condition to condition list.
     *
     * @param Condition $c The condition to add.
     *
     * @return void
     */
    public function addCondition(Condition $c)
    {
        $this->conditions[] = $c;
    }

    /**
     * Return a child that from a relation.
     *
     * @param Relation $r The relation through which retrieve the child
     * node.
     *
     * @return Arborescence
     */
    public function getChild($relationName)
    {
        return $this->children[$relationName];
    }

    /**
     * Does the node has a child associated to the given relation?
     *
     * @param Relation $r The relation to test on.
     *
     * @return bool True if there is a child, false otherwise.
     */
    public function hasChild($relationName)
    {
        return isset($this->children[$relationName]);
    }

    /**
     * Is the node a leaf?
     *
     * @return bool
     */
    public function isLeaf()
    {
        return ! $this->children;
    }

    /**
     * Is the node the root?
     *
     * @return bool True if the node is the root, false otherwise.
     */
    public function isRoot()
    {
        return $this->parent === null;
    }

    /**
     * Update the node join type.
     *
     * It always chooses the most restrictive JOIN when there is a conflict.
     *
     * @param int $newJoinType The new join type to apply.
     */
    private function updateJoinType($newJoinType)
    {
        if ($this->joinType === $newJoinType)
        {
            return;
        }

        // If new join type is *stronger* (more restrictive) than old join type,
        // use new type.
        if ($this->joinType / 10 < $newJoinType / 10)
        {
            $this->joinType = $newJoinType;
        }

        // Special case for LEFT and RIGHT conflict. Use INNER.
        elseif ($this->joinType !== $newJoinType && $this->joinType / 10 === $newJoinType / 10)
        {
            $this->joinType = self::JOIN_INNER;
        }
    }
}
