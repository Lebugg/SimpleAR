<?php namespace SimpleAR\Orm;

use SimpleAR\Database\Builder as QueryBuilder;
use SimpleAR\Database\Builder\InsertBuilder;
use SimpleAR\Database\Builder\SelectBuilder;
use SimpleAR\Database\Builder\UpdateBuilder;
use SimpleAR\Database\Builder\DeleteBuilder;
use SimpleAR\Database\Compiler;
use SimpleAR\Database\Connection;
use SimpleAR\Database\Query;

use SimpleAR\Facades\Cfg;
use SimpleAR\Facades\DB;

class Builder
{
    /**
     * The query compiler instance.
     *
     * @var Compiler
     */
    protected $_compiler;

    /**
     * The DB connection instance.
     *
     * @var Connection
     */
    protected $_connection;

    /**
     * The query being built.
     *
     * @var Query
     */
    protected $_query;

    /**
     * The model class name.
     *
     * @var string
     */
    protected $_root;

    /**
     * Do we have eager loading processing?
     *
     * @var bool
     */
    protected $_eagerLoad = false;

    /**
     * A row fetched from DB but stored here because not used yet.
     *
     * @var array
     */
    protected $_pendingRow = false;

    /**
     * Is it worth calling Connection?
     *
     * True if there is no more row to fetch from DB, false otherwise.
     *
     * @var bool
     */
    protected $_noRowToFetch = false;

    public function __construct($root = null)
    {
        $root && $this->root($root);
    }

    /**
     * Get the root class.
     *
     * @return string The current root class.
     */
    public function getRoot()
    {
        return $this->_root;
    }

    /**
     * Set the root model.
     *
     * @return $this
     */
    public function root($root)
    {
        $this->_root = $root;

        // If a query is already instanciated, set root of it.
        $this->_query && $this->_query->root($root);

        return $this;
    }

    public function findOne(array $options)
    {
        $this->setOptions($options);
        return $this->one();
    }

    public function findMany(array $options)
    {
        $this->setOptions($options);
        return $this->all();
    }

    /**
     * Add a condition that check existence of related model instances for the 
     * root model.
     *
     * @param string $relation The name of the relation to check on.
     * @return $this
     */
    public function has($relation, $op = null, $value = null, \Closure $callback = null)
    {
        $mainQuery = $this->getQueryOrNewSelect();
        $mainQueryRootAlias = $mainQuery->getBuilder()->getRootAlias();

        $model = $this->_root;
        $rel   = $model::relation($relation);

        // We construct the sub-query. It can be the sub-query of an Exists 
        // clause or a Count (if $op and $value are given).
        $hasQuery = $this->newQuery(new SelectBuilder);
        $hasQuery->getBuilder()->setRootAlias($mainQueryRootAlias . '_');
        $hasQuery->setInvolvedTable($mainQueryRootAlias, $mainQuery->getBuilder()->getRootTable());
        $hasQuery->root($rel->lm->class);

        // Make the join between both tables.
        $sep = Cfg::get('queryOptionRelationSeparator');
        foreach ($rel->getJoinAttributes() as $mainAttr => $hasAttr)
        {
            $hasQuery->whereAttr($hasAttr, $mainQueryRootAlias . $sep . $mainAttr);
        }

        // We want a Count sub-query.
        if (func_num_args() >= 3)
        {
            $hasQuery->count();
            $mainQuery->selectSub($hasQuery, '#' . $relation);
            $mainQuery->where(DB::expr('#' . $relation), $op, $value);
            //$mainQuery->whereSub($hasQuery, $op, $value);
        }

        // We don't want anything special. This will be a simple Select 
        // sub-query.
        else
        {
            $hasQuery->select(array('*'), false);
            $mainQuery->whereExists($hasQuery);
        }

        if ($callback !== null || ($callback = $op) instanceof \Closure)
        {
            $callback($hasQuery);
        }

        $mainQuery->getCompiler()->useTableAlias = true;

        return $this;
    }

    /**
     * Set several options.
     *
     * @param array $options The options to set.
     * @return $this
     */
    public function setOptions(array $options)
    {
        $q = $this->getQueryOrNewSelect();

        foreach ($options as $name => $value)
        {
            $q->$name($value);
        }

        return $this;
    }

    public function delete($root = '')
    {
        $query = $this->newQuery(new DeleteBuilder);
        $query->root($root ?: $this->_root);

        $query->setCriticalQuery();

        return $query;
    }

    /**
     * Insert one or several rows in DB.
     *
     * @param array $fields The query columns.
     * @param array $values The values to insert.
     *
     * @return mixed The last insert ID.
     */
    public function insert(array $fields, array $values)
    {
        $query = $this->newQuery(new InsertBuilder())
            ->root($this->_root)
            ->fields($fields)
            ->values($values)
            ->run()
            ;

        return $query->getConnection()->lastInsertId();
    }

    public function insertInto($table, $fields)
    {
        $query = $this->newQuery(new InsertBuilder())
            ->root($table)
            ->fields($fields);

        return $query;
    }

    public function update($root = '')
    {
        $query = $this->newQuery(new UpdateBuilder())
            ->root($root ?: $this->_root);

        return $query;
    }

    public function getTableColumns($tableName)
    {
        $conn = $this->getConnection();
        $columns = $conn->query('SHOW COLUMNS FROM `' . $tableName . '`')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get one model instance from Query.
     *
     * @return SimpleAR\Model
     */
    public function one()
    {
        $q = $this->getQueryOrNewSelect()->run();

        return $this->_fetchModelInstance();
    }

    /**
     * Alias for one().
     *
     * @see one()
     * @return Model
     */
    public function first()
    {
        return $this->one();
    }

    /**
     * Get the last fetched model.
     *
     * @return Model
     */
    public function last()
    {
        $q = $this->getQueryOrNewSelect()->run();

        return $this->_fetchModelInstance(false);
    }

    /**
     * Return all fetch Model instances.
     *
     * This function is just a foreach wrapper around `row()`.
     *
     * @see Select::row()
     *
     * @return array
     */
    public function all()
    {
        $q = $this->getQueryOrNewSelect()->run();

        $all = array();
        while ($one = $this->_fetchModelInstance())
        {
            $all[] = $one;
        }

        return $all;
    }

    /**
     * Return the number of rows matching the current query.
     *
     * @param string $attribute An optional attribute to count on.
     * @return int The count.
     */
    public function count($attribute = '*')
    {
        $q = $this->getQueryOrNewSelect();
        $q->count($attribute)->run();

        return $q->getConnection()->getColumn();
    }

    /**
     * Reset current query builder state.
     */
    public function reset()
    {
        $this->_pendingRow = false;
        $this->_eagerLoad = false;
        $this->_query = null;
        $this->_noRowToFetch = false;
    }

    public function applyScope($scope)
    {
        $root = $this->_root;
        $args = func_get_args();

        // We don't want $scope twice.
        unset($args[0]);

        return $root::applyScope($scope, $this, $args);
    }

    /**
     * Redirect method calls.
     *
     * All unknown method calls are redirected to be called on Query instance.
     *
     * Note: The SelectBuilder will be used.
     * -----
     *
     * @return Query The current query instance.
     */
    public function __call($name, $args)
    {
        $q = $this->getQueryOrNewSelect();

        // If 'with()' option is called, query builder has to parse eager loaded
        // models.
        if ($name === 'with' && $args)
        {
            $this->_eagerLoad = true;
        }

        // Redirect method calls on Query.
        switch (count($args))
        {
            case 0: $q->$name(); break;
            case 1: $q->$name($args[0]); break;
            case 2: $q->$name($args[0], $args[1]); break;
            case 3: $q->$name($args[0], $args[1], $args[2]); break;
            case 4: $q->$name($args[0], $args[1], $args[2], $args[3]); break;
            default: call_user_func_array(array($q, $name), $args);
        }

        // We always want to use the query builder, not the Query class.
        return $this;
    }

    /**
     * Return the current query.
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->_query;
    }

    /**
     * Return a new Query instance.
     *
     * @param Builder $b The builder to use.
     * @param Compiler $c The compiler to use.
     * @param Connection $conn The connection to use.
     * @return Query
     */
    public function newQuery(QueryBuilder $b = null, Compiler $c = null, Connection $conn = null)
    {
        $c = $c ?: $this->getCompiler();
        $conn = $conn ?: $this->getConnection();

        $q = new Query($b, $c, $conn);

        return $q;
    }

    /**
     * Return the current query instance or a new Select query if there is no
     * query yet.
     *
     * @see newQuery()
     */
    public function getQueryOrNewSelect()
    {
        $this->_query = $this->getQuery() ?: $this->newQuery(new SelectBuilder);
        $this->_root && $this->_query->root($this->_root);

        return $this->_query;
    }

    /**
     * Get the compiler instance.
     *
     * @return Compiler
     */
    public function getCompiler()
    {
        return $this->_compiler = ($this->_compiler ?: DB::getCompiler());
    }

    /**
     * Get the connection instance.
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->_connection = ($this->_connection ?: DB::getConnection());
    }

    protected function _applyScope($modelClass, $scope, array $args)
    {
        return $modelClass::applyScope($scope, $this, $args);
    }

    /**
     * Construct and return a model instance from result.
     *
     * Algorithm:
     * ----------
     *
     *  * Fetch data;
     *  * Instanciate model object and populate it;
     *  * Return it.
     *
     * If some model relation are to be eager loaded, the 'fetch data' step can
     * retrieve several rows from DB.
     */
    protected function _fetchModelInstance($next = true)
    {
        $data = $this->_fetchModelInstanceData($this->_eagerLoad, $next);

        if (! $data)
        {
            return null;
        }

        $model = $this->_root;
        $instance = new $model();
        $instance->populate($data);

        return $instance;
    }

    protected function _fetchModelInstanceData($eagerLoad = false, $next = true)
    {
        // If there is no eager load, we are sure that one row equals one model
        // instance.
        if (! $eagerLoad)
        {
            return $this->_getNextOrPendingRow($next);
        }


        // Otherwise, it is more complicated: several rows can be returned for
        // only one model instance.

        $model = $this->_root;
        $pk = array_flip($model::table()->getPrimaryKey());

        $res = array();
        $instanceId  = null;

        while (($row = $this->_getNextOrPendingRow($next)) !== false)
        {
            // Get the ID of the row.
            $rowId = array_intersect_key($row, $pk);

            // I remind us of that we want *one* model instance: we have to stop
            // when the next row does not concern the same model instance than
            // the previous.
            if ($instanceId && $instanceId !== $rowId)
            {
                $this->_pendingRow = $row;
                break;
            }

            $instanceId = $rowId;

            // Parse result (parse eager load).
            $row = $this->_parseRow($row);

            // Construct result.
            $res = $res ? \SimpleAR\array_merge_recursive_distinct($res, $row) : $row;
        }

        return $res;
    }

    /**
     * Get next DB row or the pending one is there is.
     *
     * @return array
     */
    protected function _getNextOrPendingRow($next = true)
    {
        $row = $this->_pendingRow ?: $this->_getNextRow($next);
        $this->_pendingRow = false;

        return $row;
    }

    /**
     * Get next DB row.
     *
     * $_query must be set.
     *
     * @return array
     */
    protected function _getNextRow($next = true)
    {
        $res = false;

        // It saves superfluous call to Connection instance.
        if (! $this->_noRowToFetch)
        {
            $res = $this->_query->getConnection()->getNextRow($next);

            if ($res === false)
            {
                $this->_noRowToFetch = true;
            }
        }

        return $res;
    }

    /**
     * Parse a row fetch from query result into a more object-oriented array.
     *
     * Resulting array:
     * array(
     *      'attr1' => <val>,
     *      'attr2' => <val>,
     *      'attr3' => <val>,
     *      '_WITH_' => array(
     *          'relation1' => array(
     *              <attributes>
     *          ),
     *          ...
     *      ),
     * )
     *
     * Note: does not parse relations recursively (only on one level).
     *
     * This function has to be called only if we have to parse eager loaded
     * related models.
     *
     * @return array
     */
    private function _parseRow(array $row)
    {
        static $i = 0;

        $res = array(
            // To store eager loaded stuff.
            '__with__' => array()
        );

        foreach ($row as $key => $value)
        {
            // Keys are prefixed with alias matching relation names.
            // They are constructed like the following:
            //
            //  `[<relationName>\.]*<attributeName>`
            //
            // If there is no relation name, it means that attribute belongs to
            // root model.
            $relations = explode('.', $key);
            $attribute = array_pop($relations);

            // There may be several relations; we need to go down the
            // arborescence.
            $end =& $res;
            foreach ($relations as $rel)
            {
                if (! isset($end['__with__'][$rel]))
                {
                    // We add a "__with__" entry at each level for easy
                    // recursivity.
                    $end['__with__'][$rel][$i] = array();
                }
                $end =& $end['__with__'][$rel][$i];
            }

            $end[$attribute] = $value;
        }

        $i++;
        return $res;
    }

}
