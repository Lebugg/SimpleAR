<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query\Arborescence;
use \SimpleAR\Query\Condition\Attribute;

use \SimpleAR\MalformedOptionException;

class OrderBy extends Option
{
    /**
     * We use class members because an "order by" option can cause "order by",
     * "group by", and "select" clauses. Moreover, this class uses several functions that
     * can produce these two clauses above. Class members make it easy to handle
     * this case.
     */
    private $_orderBy = array();
    private $_groupBy = array();
    private $_selects = array();

    const DEFAULT_DIRECTION = 'ASC';

    const CLAUSE_STRING = 'ORDER BY ';

    private static $_sqlFunctions = array(
        'RAND',
    );

    private $_useSqlFunction = false;

    /**
     *
     * @return array
     *
     *  array(
     *      'order_by' => <array>,
     *      'group_by' => <array>,
     *      'selects'  => <array>,
     *  );
     *
     */
	public function build()
	{
        // First we check that the user is not using a SQL function (Ex:
        // RAND()).
        if (is_string($this->_value) && in_array($this->_value, self::$_sqlFunctions))
        {
            return $this->_buildSqlFunction($this->_value);
        }

        // Merge order by arrays.
        //
        // _context->rootTable->orderBy corresponds to static::$_orderBy.
        // @see Model::wakeup()
        //
        // If there are common keys between static::$_order and $order,
        // entries of static::$_order will be overwritten.
        $orderBy = array_merge($this->_context->rootTable->orderBy, (array) $this->_value);

        // Two array entry format possibilities:
        //
        //  array(
        //     <attribute> => <direction>,
        //     <attribute>, // <direction> will be the one specified in
        //                  // self::DEFAULT_DIRECTION.
        //  ),
        foreach ($orderBy as $attribute => $direction)
        {
            // Allows for a without-ASC/DESC syntax.
            if (is_int($attribute))
            {
                $attribute = $direction;
                $direction = self::DEFAULT_DIRECTION;
            }

            // It may happen. So tell user he made a mistake.
            if ($attribute == null)
            {
                throw new MalformedOptionException('"order_by" option malformed: attribute is empty.');
            }

            // Now, $attribute is an object.
            $attribute = Attribute::parse($attribute);

            // Handle special "order by" clauses.
            if ($attribute->specialChar)
            {
                switch ($attribute->specialChar)
                {
                    // Order by a count.
                    // Example: order schools by number of students.
                    case self::SYMBOL_COUNT:
                        $this->_count($attribute, $direction);
                        break;
                    default:
                        throw new MalformedOptionException('"order_by" option malformed: unknown special character: "' .  $attribute->specialChar . '".');
                }

                // Next!
                continue;
            }

            // Add related model(s) in join arborescence.
            $node = $this->_arborescence->add($attribute->relations);

            // Order by is made on related model's attribute.
            if ($node->relation)
            {
                $this->_relation($attribute, $direction, $node);
                continue;
            }

            // Classic order by. It is made on root model's attribute.

            // We always assume there are several columns for simplicity.
            $columns = (array) ($this->_context->useModel
                ? $this->_context->rootTable->columnRealName($attribute->attribute)
                : $attribute->attribute)
                ;

            $tableAlias = $this->_context->useAlias
                ? $this->_context->rootTableAlias . ($node->depth ?: '')
                : '';

            foreach ($columns as $column)
            {
                $this->_orderBy[] = '`' . $tableAlias . '`.`' .  $column . '` ' . $direction;
            }
        }

        return array(
            'group_by' => $this->_groupBy,
            'selects'  => $this->_selects,
        );
	}

    public function compile()
    {
        if ($this->_useSqlFunction)
        {
            return self::CLAUSE_STRING . $this->_value . '()';
        }

        return $this->_orderBy
            ? self::CLAUSE_STRING . implode(',', $this->_orderBy)
            : ''
            ;
    }

    private function _buildSqlFunction($fn)
    {
        $this->_useSqlFunction = true;

        return array(
            'group_by' => array(),
            'selects'  => array(),
        );
    }

    /**
     * Handle "order by" option on linked model row count.
     *
     * @param StdClass $attribute An attribute object returned by
     * Attribute::parse().
     * @param string   $direction The order direction.
     *
     * @return void
     */
    private function _count($attribute, $direction)
    {
        // Add related model(s) in join arborescence.
        //
        // We couldn't use second parameter of Attribute::parse() in
        // build() to specify that there must be relations only in the raw
        // attribute because we weren't able to know if it was an "order by
        // count" case.
        $attribute->relations[] = $attribute->attribute;

        $node     = $this->_arborescence->add($attribute->relations, Arborescence::JOIN_LEFT, true);
        // Note: $node->relation cannot be null (because $attribute->relations
        // is never empty).
        $relation = $node->relation;

        // Depth string to suffix table alias if used.
        $depth = (string) ($node->depth ?: '');

        // Will be used:
        // - in BelongsTo case;
        // - to group rows.
        //
        // No need to handle ($previousDepth == -1) case. We would not be in
        // this function: there is at least one relation specified in attribute.
        // And first relation has a depth of 1. So $previousDepth minimum is 0.
        $previousDepth = (string) ($node->depth - 1 ?: '');

        switch (get_class($relation))
        {
            case 'SimpleAR\HasMany':
            case 'SimpleAR\HasOne':
                if ($this->_context->useAlias)
                {
                    $tableAlias = $relation->lm->alias . $depth;
                }

                $column = $relation->lm->column;
                break;

            case 'SimpleAR\ManyMany':
                if ($this->_context->useAlias)
                {
                    $tableAlias = $relation->jm->alias . $depth;
                }

                $column = $relation->jm->from;
                break;

            case 'SimpleAR\BelongsTo':
                // No need to order on the linked model attribute, we have it in
                // the current model.

                if ($this->_context->useAlias)
                {
                    $tableAlias = $relation->cm->alias . $previousDepth;
                }

                $column = $relation->cm->column;

                // We do not *need* to join this relation since we have a corresponding
                // attribute in current model.
                //
                // Moreover, this is stupid to COUNT on a BelongsTo relationship
                // since it would return 0 or 1. But what! we are not here to
                // judge.
                $node->__force = false;

                break;
        }

        // We assure that column is a string because it might be an array (in
        // case of relationship over several attributes) and we need only one
        // field for the COUNT().
        $column = is_string($column) ? $column : $column[0];

        // What we put inside the COUNT.
        $countAttribute = $this->_context->useAlias
            ? '`' . $tableAlias . '`.`' . $column . '`'
            : '`' . $column . '`'
            ;

        // What will be returned by Select query.
        $resultAttribute = $this->_context->useResultAlias
            ? '`' . ($attribute->lastRelation ?: $this->_context->rootResultAlias) . '.' . self::SYMBOL_COUNT . $attribute->attribute . '`'
            : '`' . self::SYMBOL_COUNT . $attribute->attribute . '`'
            ;

        // Count alias: `<result alias>.#<relation name>`;
        $this->_selects[] = 'COUNT(' . $countAttribute . ') AS ' . $resultAttribute;

        // We have to group rows on something if we want the COUNT to make
        // sense.
        $tableToGroupOn = $relation->cm->t;
        $tableAlias     = $this->_context->useAlias ? '`' . $tableToGroupOn->alias . $previousDepth . '`.' : '';
        foreach ((array) $tableToGroupOn->primaryKeyColumns as $column)
        {
            $this->_groupBy[] = $tableAlias . '`' . $column . '`';
        }

        // Order by the COUNT, that the point of all that.
        $this->_orderBy[] = $resultAttribute . ' ' . $direction;
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
     * @throws MalformedOptionException if context->useModel is false.
     */
    private function _relation($attribute, $direction, $node)
    {
        if (! $this->_context->useModel)
        {
            throw new MalformedOptionException('Cannot use relations when not using models. "order_by" option: "' . $attribute->original . '".');
        }

        // We *have to* include relation if we have to order on one of its
        // fields.
        if ($attribute->attribute !== 'id')
        {
            $node->force = true;
        }

        // Depth string to suffix table alias if used.
        $depth = (string) ($node->depth ?: '');

        switch (get_class($relation = $node->relation))
        {
            case 'SimpleAR\HasMany':
            case 'SimpleAR\HasOne':
                // We have to include it even if we order on linked model ID. (The
                // linked model ID field is not in the current model table.)
                $node->force = true;

                if ($this->_context->useAlias)
                {
                    $tableAlias = $relation->lm->alias . $depth;
                }

                $columns = (array) $relation->lm->t->columnRealName($attribute->attribute);

                break;

            case 'SimpleAR\ManyMany':
                if ($attribute->attribute === 'id')
                {
                    if ($this->_context->useAlias)
                    {
                        $tableAlias = $relation->jm->alias . $depth;
                    }

                    $columns = (array) $relation->jm->to;
                }
                else
                {
                    if ($this->_context->useAlias)
                    {
                        $tableAlias = $relation->lm->alias . $depth;
                    }

                    $columns = (array) $relation->lm->t->columnRealName($attribute->attribute);
                }

                break;

            case 'SimpleAR\BelongsTo':
                if ($attribute->attribute === 'id')
                {
                    // No need to order on the linked model attribute, we have it in
                    // the current model.

                    // No need to handle ($previousDepth == -1) case. We would not be in
                    // this function: there is at least one relation specified in attribute.
                    // And first relation has a depth of 1. So $previousDepth minimum is 0.
                    $previousDepth = (string) ($node->depth - 1 ?: '');

                    if ($this->_context->useAlias)
                    {
                        $tableAlias = $relation->cm->alias . $previousDepth;
                    }

                    $columns = (array) $relation->cm->column;
                }
                else
                {
                    if ($this->_context->useAlias)
                    {
                        $tableAlias = $relation->lm->alias . $depth;
                    }

                    $columns = (array) $relation->lm->t->columnRealName($attribute->attribute);
                }

                break;
        }

        if (! isset($tableAlias))
        {
            $tableAlias = '';
        }

        foreach ($columns as $column)
        {
            $this->_orderBy[] = '`' . $tableAlias . '`.`' .  $column . '` ' . $direction;
        }
    }
}
