<?php namespace SimpleAR\Query\Option;

use \SimpleAR\Query;
use \SimpleAR\Query\Option;
use \SimpleAR\Query\Arborescence;

use \SimpleAR\Exception\MalformedOption;

class With extends Option
{
    public $columns = array();
    public $groups;

    public function build()
    {
        $c = $this->_context;

        if (! $c->useModel)
        {
            throw new MalformedOption('Cannot use "with" option when not using models.');
        }

        foreach((array) $this->_value as $relation)
        {
            $this->buildValue($relation, $c->useAlias, $c->useResultAlias, $c->rootResultAlias, $c->rootTable);
        }
    }

    /**
     * Build option value.
     *
     * @param  string $value A "/"-separated relation string.
     * @return array A list of columns to select.
     */
    public function buildValue($value, $useAlias, $useResultAlias, $rootResultAlias, $rootTable)
    {
        $node      = $this->_arborescence;
        $valueData = $this->parseRelationString($value);

        $relations = $valueData->specialChar
            ? $valueData->relations
            : $valueData->allRelations;

        foreach ($relations as $relation)
        {
            // Add relation to arborescence.
            $node = $node->add($relation, Arborescence::JOIN_LEFT, true);
            
            // Add columns to select.
            $this->selectColumns($node, $useAlias);
        }

        if ($valueData->specialChar)
        {
            switch ($valueData->specialChar)
            {
                case self::SYMBOL_COUNT:
                    $node = $node->add($valueData->lastRelation, Arborescence::JOIN_LEFT, true);
                    $this->selectCount($node, $useAlias, $useResultAlias, $rootResultAlias, $rootTable);
                    break;
            }
        }
    }

    public function selectColumns(Arborescence $node, $useAlias)
    {
        $relation = $node->relation;

        $lmClass  = $relation->lm->class;
        $columns  = $lmClass::columnsToSelect($relation->filter);

        $tableAlias = $useAlias
            ? $relation->lm->alias . ($node->depth ?: '')
            : ''
            ;

        $columns = Query::columnAliasing($columns, $tableAlias, $relation->name);

        $this->columns = array_merge($this->columns, $columns);
    }

    public function selectCount(Arborescence $node, $useAlias, $useResultAlias, $rootResultAlias, $rootTable)
    {
        $relation = $node->relation;

        // Depth string to suffix table alias if used.
        $depth = (string) ($node->depth ?: '');

        $column = $relation->lm->t->primaryKeyColumns;
        // We assure that column is a string because it might be an array (in
        // case of relationship over several attributes) and we need only one
        // field for the COUNT().
        $column = is_string($column) ? $column : $column[0];

        $tableAlias = $useAlias
            ? '`' . $relation->lm->t->alias . $depth . '`.'
            : '';

        // What we put inside the COUNT.
        $countAttribute = $useAlias
            ? '`' . $relation->lm->t->alias . $depth . '`.`' . $column . '`'
            : '`' . $column . '`';

        // What will be returned by Select query.
        $resultAttribute = $useResultAlias
            ? '`' . ($node->parent->relation ? $node->parent->relation->name : $rootResultAlias) . '.' . self::SYMBOL_COUNT . $relation->name . '`'
            : '`' . self::SYMBOL_COUNT . $relation->name . '`'
            ;

        // Count alias: `<result alias>.#<relation name>`;
        $this->columns[] = 'COUNT(' . $countAttribute . ') AS ' . $resultAttribute;

        // No need to handle ($previousDepth == -1) case. We would not be in
        // this function: there is at least one relation specified in attribute.
        // And first relation has a depth of 1. So $previousDepth minimum is 0.
        $previousDepth = (string) ($node->depth - 1 ?: '');

        // We have to group rows on something if we want the COUNT to make
        // sense.
        $tableToGroupOn = $node->parent->relation ? $node->parent->relation->cm->t : $rootTable;
        $tableAlias     = $useAlias ? '`' . $tableToGroupOn->alias . $previousDepth . '`.' : '';
        foreach ((array) $tableToGroupOn->primaryKeyColumns as $column)
        {
            $this->groups[] = $tableAlias . '`' . $column . '`';
        }
    }
}
