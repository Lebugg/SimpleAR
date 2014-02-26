<?php
namespace SimpleAR\Query;

use \SimpleAR\Query\Condition;
use \SimpleAR\Table;
use \SimpleAR\Relation;

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
     * A Model class name.
     *
     * @var string
     */
    public $model;

    /**
     * A Table instance associated to the model class.
     *
     * @var Table
     */
    public $table;

    /**
     * A relation object.
     *
     * @var Relation
     */
    public $relation;

    /**
     * The parent node of the current node.
     *
     * @var Arborescence
     */
    public $parent;

    /**
     * An array of conditions that is used to figure out if the node should be
     * joined when it is a leaf (Sometimes, it is not useful to compute the
     * join).
     *
     * @var array
     */
    public $conditions = array();

    /**
     * The depth of the node from the arborescence root.
     *
     * @var int
     */
    public $depth;

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
     * @param string        $model     The model class name.
     * @param Table         $table     The table object associated to the model.
     * @param Relation      $relation  An optional relation associated to the current node.
     * @param int           $joinType  The type of the join that should be performed.
     * @param int           $depth     The depth of the node.
     * @param Arborescence  $parent    The parent node.
     * @param bool          $forceJoin Should the join be mandatory?
     */
    public function __construct(
        $model,
        Table $table,
        Relation $relation     = null,
        $joinType              = self::JOIN_INNER,
        $depth                 = 0,
        Arborescence $parent   = null,
        $forceJoin             = false
    ) {
        $this->model     = $model;
        $this->table     = $table;
        $this->relation  = $relation;
        $this->joinType  = $joinType;
        $this->depth     = $depth;

        $this->forceJoin = $forceJoin;

        $this->parent    = $parent;
        $this->children  = array();
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
        $relations = (array) $relations;

        // NOTE: "n" prefix in variable names means stands for "node", that is
        // an Arborescence instance.
        $nCurrent  = $this;
        $nPrevious = null;

        // "cm" stands for Current Model.
        $cm = $this->model;

        $relation = null;

        // Foreach relation to add, set some "config" elements.
        //
        // $i will be used to calculate node's depth.  $i starts at 0; detph
        // equals $i + 1. That means that first relation will have a depth of 1.
        foreach ($relations as $i => $relation)
        {
            $nPrevious = $nCurrent;
            // $relation is now a Relation object.
            $relation = $cm::relation($relation);
            $cm       = $relation->lm->class;

            // Add a new node if needed.
            if (! $nCurrent->hasChild($relation))
            {
                // @see Arborescence::__construct().
                $new = new self($cm, $relation->lm->t, $relation, $joinType, $i +1, $nCurrent, $forceJoin);
                $nCurrent->addChild($new);

                // Go forward.
                $nCurrent = $new;
            }
            // Already present? We may have to change some values.
            else
            {
                $nCurrent = $nCurrent->getChild($relation);
                $nCurrent->updateJoinType($joinType);
            }

            // Force join on last node if required.
            $nCurrent->forceJoin = $forceJoin || $nCurrent->forceJoin;
        }

        // Return arborescence leaf.
        return $nCurrent;
    }

    /**
     * Transform the arborescence to SQL JOIN clause.
     *
     * This a recursive function, it calls itself on each node.
     *
     * @param array $joinedTables The currently joined tables. It is used to not join a
     * table several times (for a same depth).
     *
     * @return string
     */
	public function toSql(array $joinedTables = array())
	{
        // The SQL string.
        $res = '';

        if (! isset($joinedTables[$this->depth]))
        {
            $joinedTables[$this->depth] = array();
        }

		foreach ($this->children as $relation => $next)
		{
			$relation  = $next->relation;
            $tableName = $relation->lm->table;

            // It is possible to join a table several times if the depth is
            // different each time.
            $fullTableName = $tableName . $this->depth;

            // We *have to* to join table if not already joined *and* it is not
            // the last relation.
            //
            // "force" bypasses the last relation condition. True typically when
            // we have to join this table for a "with" option.
            if (! in_array($tableName, $joinedTables[$this->depth]) && ($next->children || $next->forceJoin) )
            {
                $res .= $relation->joinLinkedModel($next->depth, self::$_joinTypes[$next->joinType]);

                // Add it to joined tables array.
                $joinedTables[$this->depth][] = $tableName;
            }

            // We have stuff to do with conditions:
            // 1. Join the relation as last if not already joined (to be able to apply potential
            // conditions).
            if ($next->conditions)
            {
                // If not already joined, do it as last relation.
                if (! in_array($tableName, $joinedTables[$this->depth]))
                {
                    // $s is false if joining table was not necessary.
                    if ($s = $relation->joinAsLast($next->conditions, $next->depth, self::$_joinTypes[$next->joinType]));
                    {
                        $res .= $s;

                        // Add it to joined tables array.
                        $joinedTables[$this->depth][] = $tableName;
                    }
                }
            }

            // Go through arborescence.
            if ($next->children)
            {
				$res .= $next->toSql($joinedTables);
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
    public function getChild(Relation $r)
    {
        return $this->children[$r->name];
    }

    /**
     * Does the node has a child associated to the given relation?
     *
     * @param Relation $r The relation to test on.
     *
     * @return bool True if there is a child, false otherwise.
     */
    public function hasChild(Relation $r)
    {
        return isset($this->children[$r->name]);
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
