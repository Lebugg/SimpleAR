<?php
namespace SimpleAR\Query;

use \SimpleAR\Query\Condition;

class Arborescence
{
    public $model;
    public $table;
    public $relation;
    public $parent;
    public $conditions = array();
    public $depth;
    public $joinType;
    public $forcejoin = false;

    public $children = array();

    const JOIN_INNER = 30;
    const JOIN_LEFT  = 20;
    const JOIN_RIGHT = 21;
    const JOIN_OUTER = 10;

    private static $_joinTypes = array(
        self::JOIN_INNER => 'INNER',
        self::JOIN_LEFT  => 'LEFT',
        self::JOIN_RIGHT => 'RIGHT',
        self::JOIN_OUTER => 'OUTER',
    );

    public function __construct(
        $model,
        $table,
        $relation = null,
        $joinType = self::JOIN_INNER,
        $depth = 0,
        $parent = null,
        $forceJoin = false
    ) {
        $this->model     = $model;
        $this->table     = $table;
        $this->relation  = $relation;
        $this->joinType  = $joinType;
        $this->depth     = $depth;

        $this->forceJoin = $forceJoin;

        $this->parent    = $parent;
        $this->children  = array();

        // Initiliaze join type "int to string" translation array.
        /* $this->__joinTypes = array( */
        /*     self::JOIN_INNER => 'INNER', */
        /*     self::JOIN_LEFT  => 'LEFT', */
        /*     self::JOIN_RIGHT => 'RIGHT', */
        /*     self::JOIN_OUTER => 'OUTER', */
        /* ); */
    }

    /**
     * Add a relation array into the join arborescence of the query.
     *
     * @param array $relatio_names Array containing relations ordered by depth to add to join
     * arborescence.
     * @param int $joinType The type of join to use to join this relation. Default: inner join.
     * @param bool $forceJoin Does relation *must* be joined?
     *
     * To be used as:
     *  ```php
     *  $a = $this->_ad_t_arborescence(...);
     *  $arborescence = &$a[0];
     *  $relation     = $a[1];
     *  ```
     * @return array
     */
    public function add($relations, $joinType = self::JOIN_INNER, $forceJoin = false)
    {
        if (! $relations)
        {
            return $this;
        }

        // NOTE: "n" prefix in variable names means stands for "node", that is
        // an Arborescence instance.

        $current         = $this;
        $previous = null;
        // "cm" stands for Current Model.
        $cm = $this->model;

        $relation         = null;

        // Foreach relation to add, set some "config" elements.
        //
        // $i will be used to calculate node's depth.  $i starts at 0; detph
        // equals $i + 1. That means that first relation will have a depth of 1.
        foreach ($relations as $i => $relation)
        {
            $previous = $current;
            // $relation is now a Relationship object.
            $relation = $cm::relation($relation);
            $cm       = $relation->lm->class;

            // Add a new node if needed.
            if (! $current->hasChild($relation))
            {
                // @see Arborescence::__construct().
                $new = new self($cm, $relation->lm->t, $relation, $joinType, $i +1, $current, $forceJoin);
                $current->addChild($new);

                // Go forward.
                $current = $new;
            }
            // Already present? We may have to change some values.
            else
            {
                $current = $current->getChild($relation);
                $current->updateJoinType($joinType);
            }

            // Force join on last node if required.
            $current->forceJoin = $forceJoin || $current->forceJoin;
        }

        // Return arborescence leaf.
        return $current;
    }

	public function process($joinedTables = array())
	{
        $res = '';

        // Construct joined table array according to depth.
        if (! isset($joinedTables[$this->depth]))
        {
            $joinedTables[$this->depth] = array();
        }

		foreach ($this->children as $relation => $next)
		{
			$relation  = $next->relation;
            $tableName = $relation->lm->table;

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
				$res .= $next->process($joinedTables);
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

    public function addCondition(Condition $condition)
    {
        $conditions = is_array($condition)
            ? $condition
            : array($condition)
            ;

        foreach ($conditions as $condition)
        {
            $this->conditions[] = $condition;
        }
    }

    public function getChild($relation)
    {
        return $this->children[$relation->name];
    }

    public function hasChild($relation)
    {
        return isset($this->children[$relation->name]);
    }

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
