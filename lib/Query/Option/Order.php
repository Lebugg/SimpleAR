<?php namespace SimpleAR\Query\Option;

use \SimpleAR\Database\Expression;
use \SimpleAR\Exception\MalformedOption;
use \SimpleAR\Query\Option;

class Order extends Option
{
    protected static $_name = 'order';

    /**
     * We use class members because an "order by" option can cause "order by",
     * "group by", and "select" clauses. Moreover, this class uses several functions that
     * can produce these two clauses above. Class members make it easy to handle
     * this case.
     */
    public $orders     = array();
    public $groups     = array();
    public $aggregates = array();


    const DEFAULT_DIRECTION = 'ASC';

    public function build($useModel, $model = null)
	{
        // Array-fy the option value.
        if (! is_array($this->_value))
        {
            $this->_value = array($this->_value);
        }

        // Fetch order clauses from model.
        $orders = $useModel
            ? $this->mergeWithModelOrders($this->_value, $model)
            : $this->_value;


        // Two array entry format possibilities:
        //
        //  array(
        //     <attribute> => <direction>,
        //     <attribute>, // <direction> will be the one specified in
        //                  // self::DEFAULT_DIRECTION.
        //  ),
        foreach ($orders as $attribute => $direction)
        {
            // Allows for a direction-less syntax.
            if (is_int($attribute))
            {
                list($attribute, $direction) = array($direction, self::DEFAULT_DIRECTION);
            }

            // It may happen. So tell user he made a mistake.
            if ($attribute == null)
            {
                throw new MalformedOption('"order_by" option malformed: attribute is empty.');
            }

            if ($attribute instanceof Expression)
            {
                $this->buildOrderByExpression($attribute);
            }
            else
            {
                $relations = explode('/', $attribute);
                $attribute = array_pop($relations);

                switch ($attribute[0])
                {
                    // Order by a count.
                    // Example: order schools by number of students.
                    case self::SYMBOL_COUNT:
                        $this->buildOrderByCount(substr($attribute, 1), $direction, $relations);

                        // For "continue", switch is a loop.
                        // http://www.php.net/manual/en/control-structures.continue.php
                        continue 2;
                }

                $this->orders[] = array(
                    'relations' => $relations,
                    'attribute' => $attribute,
                    'toColumn'  => $useModel,
                    'direction' => $direction,
                );
            }
        }
    }

            /* $node = $this->_arborescence->add($attribute->relations); */

            /* // Order by is made on related model's attribute. */
            /* if ($node->relation) */
            /* { */
            /*     $this->_relation($attribute, $direction, $node); */
            /*     continue; */
            /* } */

            // Classic order by. It is made on root model's attribute.

            // We always assume there are several columns for simplicity.
            /* $columns = (array) ($this->_context->useModel */
            /*     ? $this->_context->rootTable->columnRealName($attribute->attribute) */
            /*     : $attribute->attribute) */
            /*     ; */

            /* $tableAlias = $this->_context->useAlias */
            /*     ? $this->_context->rootTableAlias . ($node->depth ?: '') */
            /*     : ''; */

            /* foreach ($columns as $column) */
            /* { */
            /*     $this->orders[] = '`' . $tableAlias . '`.`' .  $column . '` ' . $direction; */
            /* } */
        /* } */
	//}

    /**
     * Handle "order by" option on linked model row count.
     *
     * @param StdClass $attribute An attribute object returned by
     * Attribute::parse().
     * @param string   $direction The order direction.
     *
     * @return void
     */
    private function buildOrderByCount($attribute, $direction, $relations)
    {
        $this->aggregates[] = array(
            'relations' => array_merge($relations, array($attribute)),
            'attribute' => 'id',
            'toColumn'  => true,
            'fn'        => 'COUNT',
            'asRelations' => $relations,
            'asAttribute' => self::SYMBOL_COUNT . $attribute,
        );

        $this->groups[] = array(
            'relations' => $relations,
            'attribute' => 'id',
            'toColumn'  => true,
        );

        $this->orders[] = array(
            'relations' => $relations,
            'attribute' => self::SYMBOL_COUNT . $attribute,
            'toColumn'  => false,
            'direction' => $direction,
        );

/*         // We assure that column is a string because it might be an array (in */
/*         // case of relationship over several attributes) and we need only one */
/*         // field for the COUNT(). */
/*         $column = is_string($column) ? $column : $column[0]; */

/*         // What we put inside the COUNT. */
/*         $countAttribute = $this->_context->useAlias */
/*             ? '`' . $tableAlias . '`.`' . $column . '`' */
/*             : '`' . $column . '`' */
/*             ; */

/*         // What will be returned by Select query. */
/*         $resultAttribute = $this->_context->useResultAlias */
/*             ? '`' . ($attribute->lastRelation ?: $this->_context->rootResultAlias) . '.' . self::SYMBOL_COUNT . $attribute->attribute . '`' */
/*             : '`' . self::SYMBOL_COUNT . $attribute->attribute . '`' */
/*             ; */

/*         // Count alias: `<result alias>.#<relation name>`; */
/*         $this->columns[] = 'COUNT(' . $countAttribute . ') AS ' . $resultAttribute; */

/*         // We have to group rows on something if we want the COUNT to make */
/*         // sense. */
/*         $tableToGroupOn = $relation->cm->t; */
/*         $tableAlias     = $this->_context->useAlias ? '`' . $tableToGroupOn->alias . $previousDepth . '`.' : ''; */
/*         foreach ((array) $tableToGroupOn->primaryKeyColumns as $column) */
/*         { */
/*             $this->groups[] = $tableAlias . '`' . $column . '`'; */
/*         } */

/*         // Order by the COUNT, that the point of all that. */
/*         $this->orders[] = $resultAttribute . ' ' . $direction; */
    }

    /**
     * Handle an "order by" option made through a relation.
     *
     * @param StdClass $attribute An attribute object returned by
     * Attribute::parse().
     * @param string   $direction The order direction.
     * @param StdClass $node      An arborescence node.
     *
     * @return void
     *
     * @throws MalformedOption if context->useModel is false.
     */
    /* private function _relation($attribute, $direction, $node) */
    /* { */
    /*     if (! $this->_context->useModel) */
    /*     { */
    /*         throw new MalformedOption('Cannot use relations when not using models. "order_by" option: "' . $attribute->original . '".'); */
    /*     } */

    /*     // We *have to* include relation if we have to order on one of its */
    /*     // fields. */
    /*     if ($attribute->attribute !== 'id') */
    /*     { */
    /*         $node->force = true; */
    /*     } */

    /*     // Depth string to suffix table alias if used. */
    /*     $depth = (string) ($node->depth ?: ''); */

    /*     switch (get_class($relation = $node->relation)) */
    /*     { */
    /*         case 'SimpleAR\Relation\HasMany': */
    /*         case 'SimpleAR\Relation\HasOne': */
    /*             // We have to include it even if we order on linked model ID. (The */
    /*             // linked model ID field is not in the current model table.) */
    /*             $node->force = true; */

    /*             if ($this->_context->useAlias) */
    /*             { */
    /*                 $tableAlias = $relation->lm->alias . $depth; */
    /*             } */

    /*             $columns = (array) $relation->lm->t->columnRealName($attribute->attribute); */

    /*             break; */

    /*         case 'SimpleAR\Relation\ManyMany': */
    /*             if ($attribute->attribute === 'id') */
    /*             { */
    /*                 if ($this->_context->useAlias) */
    /*                 { */
    /*                     $tableAlias = $relation->jm->alias . $depth; */
    /*                 } */

    /*                 $columns = (array) $relation->jm->to; */
    /*             } */
    /*             else */
    /*             { */
    /*                 if ($this->_context->useAlias) */
    /*                 { */
    /*                     $tableAlias = $relation->lm->alias . $depth; */
    /*                 } */

    /*                 $columns = (array) $relation->lm->t->columnRealName($attribute->attribute); */
    /*             } */

    /*             break; */

    /*         case 'SimpleAR\Relation\BelongsTo': */
    /*             if ($attribute->attribute === 'id') */
    /*             { */
    /*                 // No need to order on the linked model attribute, we have it in */
    /*                 // the current model. */

    /*                 // No need to handle ($previousDepth == -1) case. We would not be in */
    /*                 // this function: there is at least one relation specified in attribute. */
    /*                 // And first relation has a depth of 1. So $previousDepth minimum is 0. */
    /*                 $previousDepth = (string) ($node->depth - 1 ?: ''); */

    /*                 if ($this->_context->useAlias) */
    /*                 { */
    /*                     $tableAlias = $relation->cm->alias . $previousDepth; */
    /*                 } */

    /*                 $columns = (array) $relation->cm->column; */
    /*             } */
    /*             else */
    /*             { */
    /*                 if ($this->_context->useAlias) */
    /*                 { */
    /*                     $tableAlias = $relation->lm->alias . $depth; */
    /*                 } */

    /*                 $columns = (array) $relation->lm->t->columnRealName($attribute->attribute); */
    /*             } */

    /*             break; */
    /*     } */

    /*     if (! isset($tableAlias)) */
    /*     { */
    /*         $tableAlias = ''; */
    /*     } */

    /*     foreach ($columns as $column) */
    /*     { */
    /*         $this->orders[] = '`' . $tableAlias . '`.`' .  $column . '` ' . $direction; */
    /*     } */
    /* } */

    public function buildOrderByExpression(Expression $expr)
    {
        $this->orders[] = array(
            'relations' => array(),
            'attribute' => $expr->val(),
            'toColumn'  => false,
        );
    }

    /**
     * Merge order arrays.
     *
     * $table->orderBy corresponds to static::$_orderBy.
     * @see Model::wakeup()
     *
     * If there are common keys between static::$_order and $this->_value,
     * entries of static::$_order will be overwritten.
     */
    public function mergeWithModelOrders($value, $model)
    {
        return array_merge($model::table()->order, $value);
    }
}
