<?php namespace SimpleAR\Database\Builder;

use \SimpleAR\Database\Builder;
// use \SimpleAR\Database\Condition\Exists as ExistsCond;
// use \SimpleAR\Database\Condition\Nested as NestedCond;
// use \SimpleAR\Database\Condition\Simple as SimpleCond;
// use \SimpleAR\Database\Condition\Attribute as AttrCond;
// use \SimpleAR\Database\Condition\SubQuery as SubQueryCond;
use \SimpleAR\Database\Expression;
use \SimpleAR\Database\JoinClause;
use \SimpleAR\Database\Query;

use \SimpleAR\Exception\MalformedOptionException;
use \SimpleAR\Exception;

use \SimpleAR\Facades\Cfg;
use \SimpleAR\Facades\DB;

use \SimpleAR\Orm\Relation;
use \SimpleAR\Orm\Table;

class WhereBuilder extends Builder
{
    public $availableOptions = array(
        'root',
        'conditions',
    );

    /**
     *
     * @var array of Table
     */
    protected $_involvedTables = array();

    /**
     *
     * @var array of strings
     */
    protected $_involvedModels = array();

    /**
     *
     * @var array of JoinClause
     */
    protected $_joinClauses = array();

    /**
     * The query option relation separator.
     *
     * It is fetched from config.
     *
     * @var string
     */
    protected $_queryOptionRelationSeparator;

    /**
     * The query option attribute separator.
     *
     * @var string
     */
    protected $_queryOptionAttributeSeparator = ',';

    /**
     * Add a condition.
     *
     * @param string $attribute
     * @param mixed  $val
     * @param mixed  $op
     *
     * @return void
     */
    public function where($attribute, $op = null, $val = null, $logic = 'AND', $not = false)
    {
        // It allows short form: `$builder->where('attribute', 'myVal');`
        if (func_num_args() === 2)
        {
            list($val, $op) = array($op, '=');
        }

        // Default operator is '='.
        if ($op === null)
        {
            $op = '=';
        }

        // Allow user to set a bunch of attributes in one line.
        if (is_array($attribute))
        {
            foreach ($attribute as $i => $attr)
            {
                $this->where($attr, $op, $val[$i], $logic);
            }
        }
        else
        {
            list($table, $cols) = $this->_processExtendedAttribute($attribute);

            $type = 'Basic';
            $cond = compact('type', 'table', 'cols', 'op', 'val', 'logic', 'not');
            $this->_components['where'][] = $cond;
            $this->addValueToQuery($val, 'where');
            //$this->_options['conditions'][] = array($attribute, $op, $val, $logic);
        }
    }

    /**
     * Add a condition between two attributes.
     *
     * @param string $leftAttr An extended attribute string.
     * @param string $op A conditional operator.
     * @param string $rightAttr An extended attribute string.
     * @param string $logic The logical operator (AND, OR).
     */
    public function whereAttr($leftAttr, $op = null, $rightAttr = null, $logic = 'AND')
    {
        if (func_num_args() === 2)
        {
            list($rightAttr, $op) = array($op, '=');
        }

        list($lTable, $lCols) = $this->_processExtendedAttribute($leftAttr);
        list($rTable, $rCols) = $this->_processExtendedAttribute($rightAttr);

        $type = 'Attribute';
        $cond = compact('type', 'lTable', 'lCols', 'op', 'rTable', 'rCols', 'logic');
        //$cond = new AttrCond($leftAlias, $leftCols, $op, $rightAlias, $rightCols, $logic);

        $this->_components['where'][] = $cond;
    }

    /**
     * Add a condition on a sub-query.
     *
     * @param Query  $query The sub-query.
     * @param string $op The condition operator.
     * @param mixed  $value The condition value.
     */
    public function whereSub(Query $query, $op = null, $val = null, $logic = 'AND')
    {
        //$cond = new SubQueryCond($q, $op, $value, $logic);
        $subqueryValues = $query->getValues();
        $subqueryValues && $this->addValueToQuery($subqueryValues, 'where');
        $this->addValueToQuery($val, 'where');

        $type = 'Sub';
        $cond = compact('type', 'query', 'op', 'val', 'logic');
        $this->_components['where'][] = $cond;
    }

    /**
     * Add an exists condition to the query.
     *
     * @param Query $q The Select sub-query.
     * @return $this
     */
    public function whereExists(Query $query, $logic = 'AND')
    {
        $type = 'Exists';
        $this->_components['where'][] = compact('type', 'query', 'logic');
        $this->addValueToQuery($query->getComponentValues());

        return $this;
    }

    /**
     * Get the separation character of relation names in options.
     *
     * If the separator is not set yet, it fetches it from Config and cache it 
     * in current builder instance.
     *
     * @return string The separator.
     *
     * @see Config::$_queryOptionRelationSeparator
     */
    public function getQueryOptionRelationSeparator()
    {
        // Not here? Fetch it.
        if (! $this->_queryOptionRelationSeparator)
        {
            $sep = Cfg::get('queryOptionRelationSeparator');
            $this->setQueryOptionRelationSeparator($sep);
        }

        return $this->_queryOptionRelationSeparator;
    }

    /**
     * Set the query option relation separator.
     *
     * @param string $separator
     */
    public function setQueryOptionRelationSeparator($separator)
    {
        $this->_queryOptionRelationSeparator = $separator;
    }

    /**
     * Get the separation character of attribute names in query options.
     *
     * @return string The separator
     */
    public function getQueryOptionAttributeSeparator()
    {
        return $this->_queryOptionAttributeSeparator;
    }

    /**
     * Split the attribute string in two parts: proper attribute and relations 
     * string.
     *
     * An extended attribute string respects the following syntax:
     *
     *  `<relations><SEP><attribute>`
     *
     * where:
     *
     *  <relations> is: `<relation name>(<SEP><relation name>)*`
     *  <SEP>       is: Config::$_queryOptionRelationSeparator
     *  <attribute> is: `<attribute name>(,<attribute name>)*`
     *
     * We just have to find the last occurence of the separator and split the 
     * string there.
     *
     * @param string $extendedAttributeString
     * @return array [0 => <relations>, 1 => <attribute>]
     */
    public function separateAttributeFromRelations($attribute)
    {
        $sep = $this->getQueryOptionRelationSeparator();
        $pos = strrpos($attribute, $sep);

        return $pos === false
            ? array('', $attribute)
            : array(substr($attribute, 0, $pos), substr($attribute, $pos + 1))
            ;
    }

    /**
     * Extract attribute list from an attribute string.
     *
     * An extended attribute string has the following format:
     *
     *  <relations><SEP><attribute>
     *
     * The attribute part follows this syntax:
     *
     *  <attributeName>[,<attributeName>]*
     *
     * This function decompose the attribute part into an attribute array.
     *
     * @param string $attribute The attribute string.
     * @return array
     */
    public function decomposeAttribute($attribute)
    {
        return explode($this->getQueryOptionAttributeSeparator(), $attribute);
    }

    /**
     * Transform relation string to correct table alias.
     *
     * @param string $relations The relation string.
     * @return string The table alias.
     */
    public function relationsToTableAlias($relations)
    {
        // Alias separators is a dot: '.', so we just have to replace relation 
        // separators with dots.
        return str_replace($this->getQueryOptionRelationSeparator(), '.', $relations);
    }

    /**
     * Check if the given table alias is known.
     *
     * Known table alias are the keys of $_involvedTables.
     * @see $_involvedTables
     *
     * @param string $alias The table alias to check on.
     * @return bool True if the alias is known, false otherwise.
     */
    public function isKnownTableAlias($alias)
    {
        return isset($this->_involvedTables[$alias]);
    }

    /**
     * Get an involved model by table alias.
     *
     * @param string $alias The table alias.
     * @return string The model class name.
     */
    public function getInvolvedModel($alias)
    {
        if ($alias === $this->getRootAlias())
        {
            return $this->getRootModel();
        }

        return $this->_involvedModels[$alias];
    }

    /**
     * Set an involved model.
     *
     * @param string $alias The table alias.
     * @param string $model The model class name.
     */
    public function setInvolvedModel($alias, $model)
    {
        $this->_involvedModels[$alias] = $model;
    }

    /**
     * Return the Table object matching the given table alias.
     *
     * If the alias is unknown, this function tries to add it using 
     * addInvolvedTable().
     *
     * @param string $tableAlias The table alias.
     * @return Table
     * 
     * @see addInvolvedTable()
     * @see $_involvedTables
     */
    public function getInvolvedTable($tableAlias)
    {
        if ($tableAlias === $this->getRootAlias())
        {
            if (($t = $this->getRootTable()) === null)
            {
                throw new Exception('Root is not set.');
            }

            return $t;
        }

        if (! $this->isKnownTableAlias($tableAlias))
        {
            $this->addInvolvedTable($tableAlias);
        }

        return $this->_involvedTables[$tableAlias];
    }

    /**
     * Set an involved table.
     *
     * @param string $alias The table alias.
     * @param Table  $table The table itself.
     */
    public function setInvolvedTable($alias, Table $table)
    {
        $this->_involvedTables[$alias] = $table;
    }

    /**
     * Get the JoinClause matching given table alias.
     *
     * @param string $tableAlias The table alias.
     * @return JoinClause
     */
    public function getJoinClause($tableAlias)
    {
        return $this->_joinClauses[$tableAlias];
    }

    /**
     * Add a Table to the list of involved tables according to the given table 
     * alias.
     *
     * When parsing a condition, we have to parse the attribute part of the 
     * condition. This is what we call the "extended attribute string". It is 
     * divided in two parts: the relations part and the proper attribute part.
     * The relations part is used as an alias for the attribute's table.
     *
     * For each table alias we have, we want to fetch the matching Table object: 
     * we will use it to convert attribute name into column name, get the DB 
     * table name...
     *
     * A table alias, is a dot-separated list of relation names.
     * At the beginning, we know only one Table: the root Table; but thanks to 
     * the relations (given by their names), we can know following tables. The 
     * process looks like: Table -> Relation -> next Table -> next Relation ...
     *
     * @param string $tableAlias The table alias used in the extended attribute 
     * string (i.e. the <relations> part).
     *
     * @return void
     */
    public function addInvolvedTable($tableAlias, $joinType = JoinClause::INNER)
    {
        $alias   = '';
        $relNames = explode('.', $tableAlias);

        // $this->_table is the "root" table.
        $currentModel = $this->_model;
        $currentRel   = null;

        // The first alias is the root table's alias.
        $previousAlias = $this->getRootAlias();

        // For each possible table alias, we check whether it known or not. If 
        // not, we get to know it.
        foreach ($relNames as $relName)
        {
            $alias .= $alias ? '.' . $relName : $relName;

            // Is this one known to us?
            if (! $this->isKnownTableAlias($alias))
            {
                // $previousAlias will always be known.
                $previousModel = $this->getInvolvedModel($previousAlias);
                $currentRel    = $this->getNextRelationByModel($previousModel, $relName);
                $currentModel  = $this->getNextModelByRelation($currentRel);

                $currentTable = $currentModel::table();
                $this->setInvolvedModel($alias, $currentModel);
                $this->setInvolvedTable($alias, $currentTable);
                $this->_addJoinClause(
                    $currentTable, $alias,
                    $currentRel, $previousAlias,
                    $joinType
                );
            }

            $previousAlias = $alias;
        }
    }

    /**
     * Get a relation object according to a relation name.
     *
     * @param string $model The model class name to which the relation is bound.
     * @param string $relation The relation name.
     */
    public function getNextRelationByModel($model, $relation)
    {
        return $model::relation($relation);
    }

    public function getNextModelByRelation(Relation $relation)
    {
        return $relation->lm->class;
    }

    /**
     * Add a JoinClause to the list of join to make.
     *
     * We have to create a new JoinClause to join $new Table. $new Table will be 
     * tied to $joined Table.
     *
     * @param Table
     * @param string
     * @param Table
     * @param string
     */
    protected function _addJoinClause(Table $toJoin, $toJoinAlias,
        Relation $rel = null, $previousAlias = '',
        $joinType = JoinClause::INNER
    ) {
        $jc = new JoinClause($toJoin->name, $toJoinAlias, $joinType);

        // We may have to connect it to a previous JoinClause.
        if ($rel)
        {
            // $rel->cm corresponds to previous model.
            foreach ($rel->getJoinColumns() as $leftCol => $rightCol)
            {
                $jc->on($previousAlias, $leftCol, $toJoinAlias, $rightCol);
            }
        }

        $this->_joinClauses[$toJoinAlias] = $jc;
    }

    /**
     * Parse a condition array.
     *
     * This function is only a syntax analyzer. It does not make any assumption 
     * about used tables, attribute names, value type.
     *
     * @param array $conditions The condition array
     * @return array An array of Condition constructed out of given raw 
     * conditions.
     */
    public function parseConditionArray(array $conditions, $defaultLogic = 'AND')
    {
        $wheres = array();
        $logic  = $defaultLogic;

        foreach ($conditions as $key => $value)
        {
            // This is bound to be a condition. 'myAttribute' => 'myValue'
            //
            // $key: <attribute> ; $value: <value>
            if (is_string($key))
            {
                $this->where($key, null, $value, $logic);
            }

            // What can we get, otherwise?
            // $key is numeric, the relevant stuff is, here, $value.

            // Firstable, an easy guess: a logical operator: 'AND' or 'OR'.
            elseif ($value === 'AND' || $value === 'OR')
            {
                $logic = $value; continue;
            }

            // Secondly, the whole condition can be an Expression.
            elseif ($value instanceof Expression)
            {
                $this->where(null, null, $value, $logic);
            }

            elseif (is_array($value))
            {
                // We are facing a "complete" condition form.
                // [0]: <attribute> ; [1]: <operator> ; [2]: <value>
                //
                // We check that [0] is a string to be a bit surer that it is a 
                // condition array.
                if (isset($value[0], $value[1]) && (isset($value[2]) || array_key_exists(2, $value))
                    && (is_string($value[0]) || $value[0] instanceof Expression)
                ) {
                    $wheres[] = $this->where($value[0], $value[1], $value[2], $logic);
                }

                // This is (at least, we suppose this is) a condition group. 
                // That means that we are wrapping following conditions with 
                // parenthesis.
                else
                {
                    $this->_buildConditionGroup($value, $logic);
                }
            }

            // No guess; let's complain!
            else
            {
                throw new MalformedOptionException(var_export($key, true) . ' => ' .var_export($value, true));
            }

            // Reset logical operator to its default value.
            $logic = $defaultLogic;
        }

        return $wheres;
    }

    /**
     * Parse an extended attribute string and extract data of it.
     *
     * What is an extended attribute?
     * ------------------------------
     *
     * An extended attribute string respects the following syntax:
     *
     *  `<relations><SEP><attribute>`
     *
     * where:
     *
     *  <relations> is: `<relation name>(<SEP><relation name>)*`
     *  <SEP>       is: Config::$_queryOptionRelationSeparator
     *  <attribute> is: `<attribute name>(,<attribute name>)*`
     *
     *
     * The goal of this function is to find out the table to which the 
     * attribute belongs.
     *
     * Here are the steps to make it:
     *
     *  1) Separate the <relations> part from the <attribute> part.
     *  2) Retrieve the involved Table by <relations>. @see getInvolvedTable()
     *  3) Decompose <attribute> and convert it to column(s).
     *
     * @param string $attribute The extended attribute string to process.
     *
     * @return array [tableAlias, [columns]].
     */
    protected function _processExtendedAttribute($attribute)
    {
        if ($attribute instanceof Expression)
        {
            return array('', (array) $attribute->val());
        }

        // 1)
        list($relations, $attribute) = $this->separateAttributeFromRelations($attribute);

        // 2)
        $tableAlias = $relations
            ? $this->relationsToTableAlias($relations)
            : $this->getRootAlias();
        $table = $this->getInvolvedTable($tableAlias);

        // 3)
        $attributes = $this->decomposeAttribute($attribute);
        $attributes = isset($attributes[1]) ? $attributes : $attributes[0];
        $columns = (array) $this->convertAttributesToColumns($attributes, $table);

        return array($tableAlias, $columns);
    }

    /**
     * Build 'conditions' option.
     *
     * @param array $conditions The condition array
     */
    protected function _buildConditions(array $conditions)
    {
        $this->parseConditionArray($conditions);
    }

    /**
     * Build a condition.
     *
     * The goal of this function is to build a Condition out of given 
     * parameters and add it to query's components.
     *
     * To correctly build this Condition, we need to know on which table we 
     * have to apply it, on which attribute-s and we need to know what kind of 
     * condition this is.
     *
     * The two first requirements will be resolved thanks to $attribute 
     * parameter. The last will be guess with all parameters.
     *
     * @param string $attribute The complete attribute string.
     * @param string $operator  The condition operator to use ('=', '<='...).
     * @param mixed  $value
     * @param string $logic The logical operator to use to link this 
     * condition to the previous one ('AND', 'OR').
     *
     * @param string $findAName @TODO Flag to tell whether to do a 'any' or a 
     * 'all' condition.
     *
     * @return Condition
     */
    // protected function _buildCondition($attribute, $op, $val, $logic, $findAName = 'any')
    // {
    //     list($table, $cols) = $this->_processExtendedAttribute($attribute);
    //
    //     $type = 'Basic';
    //     $cond = compact('type', 'table', 'cols', 'op', 'val', 'logic');
    //     //$cond = new SimpleCond($tableAlias, $columns, $operator, $value, $logic);
    //     $this->addValueToQuery($val, 'where');
    //
    //     return $cond;
    // }

    /**
     * Build a condition group (i.e. a nested condition).
     *
     * @param array  $conditions The nested raw conditions.
     * @param string $logic The logical operator to use to link this 
     * condition to the previous one ('AND', 'OR').
     */
    protected function _buildConditionGroup(array $conditions, $logic, $not = false)
    {
        // where() method updates $_components['where'] without 
        // returning anything. In order to construct the nested 
        // conditions. We are going to bufferize it.
        $components = &$this->_components;
        $currentWhere = isset($components['where']) ? $components['where'] : array();
        unset($components['where']);

        // $conditions is an array of raw conditions. We need to parse them 
        // before create the nested condition.
        $this->parseConditionArray($conditions);

        // Now, nested conditions are in $_components['where'].
        // We have to construct our nested condition and restore components.
        $nested = $this->_components['where'];
        $type = 'Nested';
        $cond = compact('type', 'nested', 'logic', 'not');

        $currentWhere[] = $cond;
        $this->_components['where'] = $currentWhere;

        //$cond = new NestedCond($conditions, $logic);

        return $cond;
    }
}
