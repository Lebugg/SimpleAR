<?php namespace SimpleAR\Database\Builder;

use \Closure;

use \SimpleAR\Database\Builder;
use \SimpleAR\Database\Expression;
use \SimpleAR\Database\Expression\Func as FuncExpr;
use \SimpleAR\Database\JoinClause;
use \SimpleAR\Database\Query;
use \SimpleAR\Exception\MalformedOptionException;
use \SimpleAR\Exception;
use \SimpleAR\Facades\Cfg;
use \SimpleAR\Facades\DB;
use \SimpleAR\Orm\Relation;
use \SimpleAR\Orm\Relation\ManyMany;
use \SimpleAR\Orm\Table;

class WhereBuilder extends Builder
{
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
     * The goal of this function is to create a condition  out of given
     * parameters and add it to query's components.
     *
     * To correctly build this condition, we need to know on which table we
     * have to apply it, on which attribute-s and we need to know what kind of
     * condition this is.
     *
     * The two first requirements will be resolved thanks to $attribute
     * parameter. The last will be guess with all parameters.
     *
     * @param string $attribute
     * @param mixed  $val
     * @param mixed  $op
     * @param string $logic The logical operator to use to link this 
     * condition to the previous one ('AND', 'OR').
     */
    public function where($attribute, $op = null, $val = null, $logic = 'AND', $not = false)
    {
        // Allows for nested conditions.
        if ($attribute instanceof Closure)
        {
            return $this->whereNested($attribute, $logic, $not);
        }

        // It allows short form: `$builder->where('attribute', 'myVal');`
        if (func_num_args() === 2)
        {
            list($val, $op) = array($op, '=');
        }

        if ($op === null) { $op = '='; }

        // If condition is made over several attributes, `whereTuple()` is what
        // we need.
        // However, if $attribute contain only one attribute, we simply
        // dereference it.
        if (is_array($attribute))
        {
            if (isset($attribute[1]))
            {
                return $this->whereTuple($attribute, $val, $logic, $not);
            }

            $attribute = $attribute[0];
        }

        // Maybe user wants a IN condition?
        if (is_array($val))
        {
            // Let's dereference $val too if it contains a single element. It'll
            // avoid a IN condition.
            if (! isset($val[1]) && isset($val[0])) { $val = $val[0]; }

            elseif (in_array($op, array('=', '!=')))
            {
                return $this->whereIn($attribute, $val, $logic, $op === '!=');
            }
        }

        // User wants a WHERE NULL condition.
        if ($val === null)
        {
            return $this->whereNull($attribute, $logic, $op === '!=');
        }

        list($table, $cols) = $this->_processAttribute($attribute);

        $type = 'Basic';
        $cond = compact('type', 'table', 'cols', 'op', 'val', 'logic', 'not');
        $this->_addWhere($cond, $val);

        return $this;
    }

    public function whereNot($attribute, $op = null, $val = null, $logic = 'AND')
    {
        if (func_num_args() === 2) { list($val, $op) = array($op, '='); }
        return $this->where($attribute, $op, $val, $logic, true);
    }

    public function orWhere($attribute, $op = null, $val = null, $not = false)
    {
        if (func_num_args() === 2) { list($val, $op) = array($op, '='); }
        return $this->where($attribute, $op, $val, 'OR', $not);
    }

    public function andWhere($attribute, $op = null, $val = null, $not = false)
    {
        if (func_num_args() === 2) { list($val, $op) = array($op, '='); }
        return $this->where($attribute, $op, $val, 'AND', $not);
    }

    public function orWhereNot($attribute, $op = null, $val = null)
    {
        if (func_num_args() === 2) { list($val, $op) = array($op, '='); }
        return $this->whereNot($attribute, $op, $val, 'OR');
    }

    public function andWhereNot($attribute, $op = null, $val = null)
    {
        if (func_num_args() === 2) { list($val, $op) = array($op, '='); }
        return $this->whereNot($attribute, $op, $val, 'AND');
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

        list($lTable, $lCols) = $this->_processAttribute($leftAttr);
        list($rTable, $rCols) = $this->_processAttribute($rightAttr);

        return $this->whereCol($lTable, $lCols, $op, $rTable, $rCols, $logic);
    }

    /**
     * Add a condition between two columns.
     *
     * @param string $lTable The left table alias.
     * @param string $lCols The left column name.
     * @param string $op A conditional operator.
     * @param string $rTable The right table alias.
     * @param string $rCols The right column name.
     * @param string $logic The logical operator (AND, OR).
     */
    public function whereCol($lTable, $lCols, $op, $rTable, $rCols, $logic = 'AND')
    {
        $type = 'Attribute';
        $cond = compact('type', 'lTable', 'lCols', 'op', 'rTable', 'rCols', 'logic');

        $this->_addWhere($cond);
        return $this;
    }

    public function whereNested(Closure $fn, $logic = 'AND', $not = false)
    {
        // where() method updates $_components['where'] without
        // returning anything. In order to construct the nested
        // conditions. We are going to bufferize it.
        $components = &$this->_components;
        $currentWhere = isset($components['where']) ? $components['where'] : array();
        unset($components['where']);

        $fn($this);

        // Now, nested conditions are in $_components['where'].
        // We have to construct our nested condition and restore components.
        $nested = isset($components['where'])? $components['where'] : array();
        $type   = 'Nested';
        $cond   = compact('type', 'nested', 'logic', 'not');

        $currentWhere[]      = $cond;
        $components['where'] = $currentWhere;

        return $this;
    }

    /**
     * Add a condition on a sub-query.
     *
     * @param Query  $query The sub-query.
     * @param string $op The condition operator.
     * @param mixed  $val The condition value.
     *
     * Deprecated?
     */
    public function whereSub(Query $query, $op = null, $val = null, $logic = 'AND')
    {
        //$cond = new SubQueryCond($q, $op, $value, $logic);
        $subqueryValues = $query->getValues();
        $subqueryValues && $this->addValueToQuery($subqueryValues, 'where');

        $type = 'Sub';
        $cond = compact('type', 'query', 'op', 'val', 'logic');
        $this->_addWhere($cond, $val);

        return $this;
    }

    /**
     * Add an exists condition to the query.
     *
     * @param Query $q The Select sub-query.
     * @return $this
     */
    public function whereExists(Query $query, $logic = 'AND', $not = false)
    {
        $type = 'Exists';
        $cond = compact('type', 'query', 'logic', 'not');

        $this->_addWhere($cond);
        $this->addValueToQuery($query->getComponentValues());

        return $this;
    }

    /**
     * Add an not exists condition to the query.
     *
     * @param Query $q The Select sub-query.
     * @return $this
     */
    public function whereNotExists(Query $query, $logic = 'AND')
    {
        return $this->whereExists($query, $logic, true);
    }

    /**
     * Add a Where In clause to the query.
     *
     * @param string $attribute The extended attribute.
     * @param mixed  $val The condition value.
     * @param string $logic The logical operator.
     * @param bool   $not Turn it into a NOT IN clause?
     */
    public function whereIn($attribute, $val, $logic = 'AND', $not = false)
    {
        list($table, $cols) = $this->_processExtendedAttribute($attribute);

        $type = 'In';
        $cond = compact('type', 'table', 'cols', 'val', 'logic', 'not');
        $this->_addWhere($cond, $val);

        return $this;
    }

    /**
     * Add a WHERE NULL clause to the query.
     *
     * @param string $attribute The extended attribute.
     * @param string $logic The logical operator.
     */
    public function whereNull($attribute, $logic = 'AND', $not = false)
    {
        list($table, $cols) = $this->_processExtendedAttribute($attribute);

        $type = 'Null';
        $cond = compact('type', 'table', 'cols', 'logic', 'not');
        $this->_addWhere($cond);

        return $this;
    }

    /**
     * Add a WHERE NOT NULL clause to the query.
     *
     * @param string $attribute The extended attribute.
     * @param string $logic The logical operator.
     */
    public function whereNotNull($attribute, $logic = 'AND')
    {
        return $this->whereNull($attributes, $logic, true);
    }

    /**
     * Add a raw where clause.
     *
     * @param Expression $val The raw where clause.
     * @param string $logic The logical operator.
     */
    public function whereRaw(Expression $val, $logic = 'AND')
    {
        $type = 'Raw';
        $cond = compact('type', 'val', 'logic');
        $this->_addWhere($cond);

        return $this;
    }

    /**
     * @param FuncExpr $fnExpr The extended attribute.
     * @param string $op The condition operator.
     * @param mixed  $val The condition value.
     */
    public function whereFunc(FuncExpr $fnExpr, $op, $val, $logic = 'AND', $not = false)
    {
        list($table, $cols) = $this->_processFunctionExpression($fnExpr);

        $type = 'Basic';
        $cond = compact('type', 'table', 'cols', 'op', 'val', 'logic', 'not');
        $this->_addWhere($cond, $val);

        return $this;
    }

    /**
     * Add a condition over a relation.
     *
     * This is not the same as perform a join. An involved table will be used
     * and conditions given by the relation object will be added. But no JOIN
     * will be performed.
     *
     * This method is particularly useful to connect tables between subquery and
     * main query.
     *
     * Important:
     * ----------
     * Current Model (CM) of relation is the table to involve ; Linked Model
     * (LM) is the current builder's root model.
     *
     * @param Relation $rel
     * @param string   $lmAlias An alias for the linked model.
     */
    public function whereRelation(Relation $rel, $lmAlias = null)
    {
        if ($lmAlias === null) { $lmAlias = self::DEFAULT_ROOT_ALIAS; }

        $lmTable = $rel->cm->t;
        $this->setInvolvedTable($lmAlias, $lmTable);

        // Make the join between both tables:

        $sep = Cfg::get('queryOptionRelationSeparator');
        if ($rel instanceof ManyMany)
        {
            $mdTable = $rel->getMiddleTableName();
            $mdAlias = $rel->getMiddleTableAlias();
            $mdAlias = $lmAlias === self::DEFAULT_ROOT_ALIAS ? $mdAlias : $lmAlias . '.' . $mdAlias;

            // Join middle table.
            $jc = new JoinClause($mdTable, $mdAlias);
            $jc->on($this->getRootAlias(), $rel->lm->column, $mdAlias, $rel->jm->to);
            $this->setJoinClause($mdAlias, $jc);

            // Where on relation.
            $lmCols = $rel->cm->column; $mdCols = $rel->jm->from;
            $this->whereCol($mdAlias, $mdCols, '=', $lmAlias, $lmCols);
        }
        else
        {
            // $lmAttr: attribute of the model to link. (The relation CM attr)
            // $cmAttr: current builder root model attribute. (The relation LM attr)
            foreach ($rel->getJoinAttributes() as $lmAttr => $cmAttr)
            {
                $this->whereAttr($cmAttr, $lmAlias . $sep . $lmAttr);
            }
        }

        return $this;
    }

    /**
     * Add a condition over a tuple of attributes.
     *
     * @param array $attributes An array of extended attributes.
     * @param mixed $val The value to compare to.
     * @param string $logic The logical operator.
     */
    public function whereTuple(array $attributes, $val, $logic = 'AND', $not = false)
    {
        $cols  = array();
        $table = array();
        foreach ($attributes as $attribute)
        {
            // For now, last $table will be used. But in future, tuple
            // conditions will be able to use different tables for different
            // attributes.
            list($tmpTable, $tmpCols) = $this->_processExtendedAttribute($attribute);
            $table[] = $tmpTable;
            $cols = array_merge($cols, $tmpCols);
        }

        $type = 'Tuple';
        $cond = compact('type', 'table', 'cols', 'val', 'logic', 'not');
        $this->_addWhere($cond, $val);

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
        return $alias === $this->getRootAlias()
            || isset($this->_involvedTables[$alias]);
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
     * @param string $tAlias The table alias.
     * @return Table
     *
     * @see addInvolvedTable()
     * @see $_involvedTables
     */
    public function getInvolvedTable($tAlias)
    {
        if ($tAlias === $this->getRootAlias())
        {
            if (($t = $this->getRootTable()) === null)
            {
                throw new Exception('Root is not set.');
            }

            return $t;
        }

        return $this->_involvedTables[$tAlias];
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
     * @param string $tAlias The table alias.
     * @return JoinClause
     */
    public function getJoinClause($tAlias)
    {
        if (! isset($this->_joinClauses[$tAlias]))
        {
            throw new Exception('Undefined join clause for alias "' . $tAlias . '".');
        }

        return $this->_joinClauses[$tAlias];
    }

    /**
     * Set join clause for the given alias.
     *
     * @param string $alias
     * @param JoinClause $jc
     */
    public function setJoinClause($alias, JoinClause $jc)
    {
        $this->_joinClauses[$alias] = $jc;
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
     * @param string $tAlias The table alias used in the extended attribute
     * string (i.e. the <relations> part).
     *
     * @return void
     */
    public function addInvolvedTable($tAlias, $joinType = JoinClause::INNER)
    {
        $alias   = '';
        $relNames = explode('.', $tAlias);

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
                $currentRel    = $this->_getNextRelationByModel($previousModel, $relName);
                $currentModel  = $this->_getNextModelByRelation($currentRel);

                $currentTable = $currentModel::table();
                $this->setInvolvedModel($alias, $currentModel);
                $this->setInvolvedTable($alias, $currentTable);
                $this->_addJoinClause(
                    $currentTable->name, $alias,
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
    protected function _getNextRelationByModel($model, $relation)
    {
        return $model::relation($relation);
    }

    /**
     * Return the linked model class name of a relation.
     *
     * @param Relation $relation The relation
     * @return string The linked model class name.
     */
    protected function _getNextModelByRelation(Relation $relation)
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
    protected function _addJoinClause($lmTable, $lmAlias,
        Relation $rel = null, $cmAlias = '',
        $joinType = JoinClause::INNER
    ) {

        // If relation cardinality is many-to-many, we must join middle table.
        if ($rel instanceof ManyMany)
        {
            // _addJoinClauseManyMany returns the alias of the middle table. We
            // are going to use it to join the middle table with the LM table.
            $cmAlias = $this->_addJoinClauseManyMany($rel, $cmAlias, $joinType);
        }

        $jc = new JoinClause($lmTable, $lmAlias, $joinType);

        // We may have to connect it to a previous JoinClause.
        if ($rel)
        {
            // $rel->cm corresponds to previous model.
            foreach ($rel->getJoinColumns() as $leftCol => $rightCol)
            {
                $jc->on($cmAlias, $leftCol, $lmAlias, $rightCol);
            }
        }

        $this->setJoinClause($lmAlias, $jc);
    }

    /**
     * Join on middle table of a many-to-many relation.
     *
     * @param ManyMany $rel The relation.
     * @param string   $cmAlias The current model alias.
     * @param int      $joinType
     */
    protected function _addJoinClauseManyMany(ManyMany $rel, $cmAlias, $joinType)
    {
        // $md stands for "middle".
        $mdTable = $rel->getMiddleTableName();
        $mdAlias = $rel->getMiddleTableAlias();
        $mdAlias = $cmAlias === self::DEFAULT_ROOT_ALIAS ? $mdAlias : $cmAlias . '.' . $mdAlias;

        $jcMiddle = new JoinClause($mdTable, $mdAlias, $joinType);
        $jcMiddle->on($cmAlias, $rel->cm->column, $mdAlias, $rel->jm->from);

        $this->setJoinClause($mdAlias, $jcMiddle);

        return $mdAlias;
    }

    /**
     * Add a condition to the list of conditions.
     *
     * @param array $cond The condition array
     *
     * There is no second optional argument in order to allow function caller to
     * pass a null value.
     */
    protected function _addWhere(array $cond)
    {
        $this->_components['where'][] = $cond;

        if (func_num_args() > 1)
        {
            $this->addValueToQuery(func_get_arg(1), 'where');
        }
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
                $this->whereRaw($value, $logic);
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
                throw new MalformedOptionException('conditions', var_export($key, true) . ' => ' .var_export($value, true));
            }

            // Reset logical operator to its default value.
            $logic = $defaultLogic;
        }

        return $wheres;
    }

    /**
     * Process an attribute.
     *
     * The goal of this function is to choose the correct attribute processor 
     * according to parameter's type.
     *
     * @param  mixed $attribute
     * @return array [<table alias>, <cols>] (To put in condition array).
     */
    protected function _processAttribute($attribute)
    {
        if ($attribute instanceof FuncExpr)
        {
            return $this->_processFunctionExpression($attribute);
        }

        return $this->_processExtendedAttribute($attribute);
    }

    /**
     * Parse an extended attribute string and extract data from it.
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
     * @param mixed $attribute The extended attribute string to process.
     *
     * @return array [tAlias, [columns]].
     */
    protected function _processExtendedAttribute($attribute)
    {
        if ($attribute instanceof Expression)
        {
            return array('', (array) $attribute->val());
        }

        // We don't use model? Can't do nothing for you.
        if (! $this->_useModel)
        {
            return array('', (array) $attribute);
        }

        // 1)
        list($relations, $attribute) = $this->separateAttributeFromRelations($attribute);

        // 2)
        $tAlias = $relations
            ? $this->relationsToTableAlias($relations)
            : $this->getRootAlias();

        if (! $this->isKnownTableAlias($tAlias))
        {
            $this->addInvolvedTable($tAlias);
        }
        $table = $this->getInvolvedTable($tAlias);

        // 3)
        $attributes = $this->decomposeAttribute($attribute);
        $attributes = isset($attributes[1]) ? $attributes : $attributes[0];
        $columns = (array) $this->convertAttributesToColumns($attributes, $table);

        return array($tAlias, $columns);
    }

    protected function _processFunctionExpression(FuncExpr $expr)
    {
        list($table, $cols) = $this->_processExtendedAttribute($expr->getAttribute());
        $expr->setValue($cols);

        return array($table, array($expr));
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
     * Build a condition group (i.e. a nested condition).
     *
     * @param array  $conditions The nested raw conditions.
     * @param string $logic The logical operator to use to link this
     * condition to the previous one ('AND', 'OR').
     */
    protected function _buildConditionGroup(array $conditions, $logic, $not = false)
    {
        if (! $conditions) { return; }

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
        $nested = isset($components['where'])? $components['where'] : array();
        $type   = 'Nested';
        $cond   = compact('type', 'nested', 'logic', 'not');

        $currentWhere[]      = $cond;
        $components['where'] = $currentWhere;
    }
}
