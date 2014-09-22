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
    protected $_rootAlias;

    /**
     * Do we have eager loading processing?
     *
     * @var bool
     */
    protected $_eagerLoad = false;

    protected $_relationsToPreload = array();

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

    /**
     * Constructor.
     *
     */
    public function __construct($root = null, $rootAlias = null, Relation $rel = null)
    {
        $this->setRoot($root, $rootAlias);
        $rel && $this->getQueryOrNewSelect()->whereRelation($rel);
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
    public function setRoot($root, $rootAlias = null)
    {
        // If a query is already instanciated, set root of it.
        //$this->getQueryOrNewSelect()->root($root, $rootAlias);
        $this->_root = $root;
        $this->_rootAlias = $rootAlias;

        return $this;
    }

    /**
     * Get the root alias.
     *
     * @return string The current root alias.
     */
    public function getRootAlias()
    {
        return $this->_rootAlias;
    }

    /**
     * Add a condition over a related model.
     *
     * Usage:
     * ------
     *
     * There is different calling form for this function.
     *
     *  * Check existence of related models. This is a merely a WHERE EXISTS
     *  clause in SQL:
     *
     *      `$query->root('Blog')->has('articles');`
     *
     *  * Perform a condition over the *number* of related models:
     *
     *      `$query->root('Blog')->where('articles', '>', 100);`
     *
     *      This would retrieve blogs instance which contains at least one hundred
     *      articles.
     *
     *  * Use a Closure to add conditions to the subquery:
     *
     *      ```php
     *      $query->root('Blog')->has('articles', function ($q) {
     *          $q->where('online', true);
     *      });
     *
     * @param string $relation The name of the relation to check on.
     * @param string $op       Optional. The operator for the second use form.
     * @param mixed $value     Optional. The value for the second use form.
     *
     * @return $this
     */
    public function whereHas($relation, $op = null, $value = null, $not = false)
    {
        $mainQuery = $this->getQueryOrNewSelect();
        $mainQueryRootAlias = $mainQuery->getBuilder()->getRootAlias();

        // We'll use $rels for recursivity.
        $rels = explode('/', $relation);
        $relation = array_shift($rels);

        $root = $this->getRoot();;
        $rel  = $root::relation($relation);

        // We construct the sub-query. It can be the sub-query of an Exists
        // clause or a Count (if $op and $value are given).
        $subRootAlias = $this->getRootAlias();
        $subRootAlias = $subRootAlias ? $subRootAlias . '.' . $rel->name : $rel->name;
        $subQuery = new self($rel->lm->class, $subRootAlias);
        $subQuery->setConnection($this->getConnection());
        $subQuery->newSelect();
        $subQuery->whereRelation($rel, $mainQueryRootAlias);

        $subQuery->getQuery()->getCompiler()->useTableAlias = true;
        $mainQuery->getQuery()->getCompiler()->useTableAlias = true;

        if ($rels)
        {
            $relation = implode('/', $rels);
            $subQuery->whereHas($relation);
        }

        // We want a Count sub-query.
        if ($op !== null && $value !== null)
        {
            $subQuery->getQuery()->addAggregate('COUNT');

            $mainQuery->selectSub($subQuery->getQuery(), '#'.$relation);
            $mainQuery->where(DB::expr('#'.$relation), $op, $value, null, $not);
        }

        // We don't want anything special. This will be a simple Select
        // sub-query.
        else
        {
            if ($op instanceof \Closure)
            {
                $op($subQuery);
            }

            $subQuery->get(array('*'), false);
            $mainQuery->whereExists($subQuery->getQuery(), null, $not);
        }

        return $this;
    }

    /**
     * Alias for whereHas()
     *
     * @deprecated
     */
    public function has($relation, $op = null, $value = null, $not = false)
    {
        return $this->whereHas($relation, $op, $value, $not);
    }

    /**
     * Add a condition over a related model.
     *
     * Inverse `has()` method.
     *
     * @see ::has()
     *
     * @param string $relation The name of the relation to check on.
     * @param string $op       Optional. The operator for the second use form.
     * @param mixed $value     Optional. The value for the second use form.
     *
     * @return $this
     */
    public function whereHasNot($relation, $op = null, $value = null)
    {
        return $this->whereHas($relation, $op, $value, true);
    }

    /**
     * Alias for whereHasNot()
     *
     * @deprecated
     */
    public function hasNot($relation, $op = null, $value = null)
    {
        return $this->whereHasNot($relation, $op, $value);
    }
    /**
     * Set several options.
     *
     * @param array $options The options to set.
     * @param Query $q A query to set options on. If not given, builder will use
     * getQueryOrNewSelect() to get one.
     * @return $this
     */
    public function setOptions(array $options)
    {
        foreach ($options as $name => $value)
        {
            $this->$name($value);
        }

        return $this;
    }

    /**
     * Create a new insert query.
     *
     * @param  string $root The query's root.
     * @return Query
     */
    public function insert($root = null, $rootAlias = null, $storeIt = true)
    {
        return $this->initQuery(new InsertBuilder, $root, $rootAlias, false, $storeIt);
    }

    /**
     * Create a new select query.
     *
     * @param  string $root The query's root.
     * @return Query
     */
    public function newSelect($root = null, $rootAlias = null, $storeIt = true)
    {
        return $this->initQuery(new SelectBuilder, $root, $rootAlias, false, $storeIt);
    }

    /**
     * Create a new update query.
     *
     * @param  string $root The query's root.
     * @return Query
     */
    public function update($root = null, $rootAlias = null, $storeIt = true)
    {
        return $this->initQuery(new UpdateBuilder, $root, $rootAlias, true, $storeIt);
    }

    /**
     * Create a new delete query.
     *
     * @param  string $root The query's root.
     * @return Query
     */
    public function delete($root = null, $rootAlias = null, $storeIt = true)
    {
        return $this->initQuery(new DeleteBuilder, $root, $rootAlias, true, $storeIt);
    }

    /**
     * Return a new Query instance.
     *
     * @param Builder $b The builder to use.
     * @return Query
     */
    public function initQuery(QueryBuilder $b = null,
        $root = null, $rootAlias = null,
        $critical = false, $storeIt = false)
    {
        $q = $this->newQuery($b);

        $q->root($root ?: $this->_root, $rootAlias ?: $this->_rootAlias);
        $critical && $q->setCriticalQuery($critical);

        $storeIt && $this->setQuery($q);

        return $q;
    }

    /**
     * Instanciate a new Query object with the given builder.
     *
     * @param  QueryBuilder $b The builder to use.
     * @return Query
     */
    public function newQuery(QueryBuilder $b)
    {
        return new Query($b, $this->getConnection());
    }

    /**
     * Return columns of the given table.
     *
     * This is used by SimpleAR\Orm\Model::wakeup() in order to known model's
     * columns if they are not defined by user.
     *
     * @param string $tableName The name of the table of which we want the
     * columns.
     */
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
    public function one(array $columns = array('*'))
    {
        $q = $this->getQueryOrNewSelect()->get($columns)->run();

        $object = $this->_fetchModelInstance();
        $this->_relationsToPreload && $this->preloadRelations(array($object));

        return $object;
    }

    /**
     * Alias for one().
     *
     * @param  array $columns The columns to select.
     * @return SimpleAR\Orm\Model
     *
     * @see one()
     */
    public function first(array $columns = array('*'))
    {
        return $this->one($columns);
    }

    /**
     * Get the last fetched model.
     *
     * @param  array $columns The columns to select.
     * @return SimpleAR\Orm\Model
     */
    public function last(array $columns = array('*'))
    {
        $q = $this->getQueryOrNewSelect()->get($columns)->run();

        $object = $this->_fetchModelInstance(false);
        $this->_relationsToPreload && $this->preloadRelations(array($object));

        return $object;
    }

    /**
     * Return all fetch Model instances.
     *
     * @param array $columns The columns to select.
     * @param Query $q A query to set options on. If not given, builder will use
     * getQueryOrNewSelect() to get one.
     */
    public function all(array $columns = array('*'), Query $q = null)
    {
        $q = $q ?: $this->getQueryOrNewSelect();
        $q->get($columns)->run();

        $all = array();
        while ($one = $this->_fetchModelInstance())
        {
            $all[] = $one;
            $ids[] = $one->id();
        }

        $this->_relationsToPreload && $this->preloadRelations($all);
        return $all;
    }

    /**
     * Find one model isntance.
     *
     * @param  array $options The options to pass to the query.
     * @return SimpleAR\Orm\Model
     */
    public function findOne(array $options)
    {
        $this->setOptions($options);
        return $this->one();
    }

    /**
     * Find several model isntances.
     *
     * @param  array $options The options to pass to the query.
     * @return array
     */
    public function findMany(array $options)
    {
        $this->setOptions($options);
        return $this->all();
    }

    /**
     * Preload required relations.
     *
     * @param array $cmInstances Instances of current model for which to preload 
     * relations.
     */
    public function preloadRelations(array $cmInstances)
    {
        $root = $this->getRoot();
        foreach ($this->_relationsToPreload as $relationName)
        {
            $qb = new self($root);
            $qb->preloadRelation($cmInstances, $relationName);
        }
    }

    /**
     * Preload a relation for an array of model instances.
     *
     * @param array  $cmInstances
     * @param string $relationName
     */
    public function preloadRelation(array $cmInstances, $relationName)
    {
        $root = $this->getRoot();
        $relation = $root::relation($relationName);

        $lmInstances = $this->loadRelation($relation, $cmInstances);
        $this->_associateLinkedModels($cmInstances, $lmInstances, $relation);
    }

    public function loadRelation(Relation $relation, array $objects, array $localOptions = array())
    {
		$lmClass = $relation->lm->class;
        $this->setRoot($lmClass);

        $lmAttributePrefix = '';
        if ($relation instanceof Relation\ManyMany)
        {
			$reversed = $relation->reverse();
			$lmClass::relation($reversed->name, $reversed);

            $lmAttributePrefix = $reversed->name . '/';
            $relation = $reversed;
        }

        // Get CM join attributes values for each given objects.
        $cmValues = array();
        foreach ($objects as $o)
        {
            $cmValues[] = $o->get($relation->getCmAttributes());
        }

        $lmAttributes = $relation->getLmAttributes();
        if ($lmAttributePrefix) {
            foreach ($lmAttributes as &$attr)
            {
                $attr = $lmAttributePrefix . $attr;
            }
        }

        $options['conditions'] = array_merge(
            $relation->conditions,
            $lmClass::getGlobalConditions()
        );
        if ($orderBy = $relation->getOrderBy()) {
            $options['orderBy'] = $orderBy;
        }

        $options = array_merge($options, $localOptions);

        $q = $this->newSelect();
        $this->setOptions($options);
        $q->whereTuple($lmAttributes, $cmValues);

        if ($scope = $relation->getScope())
        {
            $this->applyScopes($scope);
        }

        $lmInstances = $this->all($relation->filter ?: array('*'), $q);

        return $lmInstances;
    }

    /**
     * Count the number of linked models for givent instances.
     *
     */
    public function countRelation(Relation $relation, array $objects, array $localOptions = array())
    {
		$lmClass = $relation->lm->class;
        $this->setRoot($lmClass);

        $lmAttributePrefix = '';
        if ($relation instanceof Relation\ManyMany)
        {
			$reversed = $relation->reverse();
			$lmClass::relation($reversed->name, $reversed);

            $lmAttributePrefix = $reversed->name . '/';
            $relation = $reversed;
        }

        // Get CM join attributes values for each given objects.
        $cmValues = array();
        foreach ($objects as $o)
        {
            $cmValues[] = $o->get($relation->getCmAttributes());
        }

        $lmAttributes = $relation->getLmAttributes();
        if ($lmAttributePrefix) {
            foreach ($lmAttributes as &$attr)
            {
                $attr = $lmAttributePrefix . $attr;
            }
        }

        $options['conditions'] = array_merge(
            $relation->conditions,
            $lmClass::getGlobalConditions()
        );

        $options = array_merge($options, $localOptions);

        $q = $this->newSelect();
        $this->setOptions($options);
        $q->whereTuple($lmAttributes, $cmValues);

        if ($scope = $relation->getScope())
        {
            $this->applyScopes($scope);
        }

        $pk = $lmClass::table()->getPrimaryKey();
        return $this->count(DB::distinct($pk));
    }

	/**
     * Search for object in database. It combines count() and all() functions.
     *
	 * This function makes pagination easier.
	 *
	 * @param int   $page    Page number. Min: 1.
	 * @param int   $nbItems Number of items. Min: 1.
	 */
    public function search($page, $nbItems)
    {
		$page    = $page    >= 1 ? $page    : 1;
		$nbItems = $nbItems >= 1 ? $nbItems : 1;

        $q  = $this->getQueryOrNewSelect();
        $q->limit($nbItems, ($page - 1) * $nbItems);

        $res['rows'] = $this->all();
        $q->remove(array('limit', 'offset'));
        $res['count'] = $q->count();

        return $res;
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
     * Apply scopes to the query.
     *
     * @param array $scopes The scopes to apply.
     */
    public function applyScopes(array $scopes)
    {
        $root = $this->_root;
        foreach ($scopes as $name => $args)
        {
            // Check if model has a scope of this name.
            if ($root::hasScope($name))
            {
                return $root::applyScope($name, $this, $args);
            }
            else
            {
                throw new Exception('Unknown scope "' . $name . '" with args: '
                    . var_export($args, true));
            }
        }
    }

    /**
     * Preload a relation.
     *
     * Another query will be performed to fetch linked models for the given
     * relation.
     *
     * @param string $relation The relation to preload.
     */
    public function preload($relation)
    {
        $this->_relationsToPreload[] = $relation;

        return $this;
    }

    /**
     * Eager-load a relation.
     *
     * Joins will be added to the query to fetch linked models for the given
     * relation.
     *
     * @param string $relation The relation to preload.
     */
    public function eagerLoad($relation)
    {
        $this->with($relation);
    }

    /**
     * Redirect method calls.
     *
     * If method name matches a root model scope, scope is applied, otherwise,
     * all unknown method calls are redirected to be called on Query instance.
     *
     * Note: The SelectBuilder will be used.
     * -----
     *
     * @return $this
     */
    public function __call($name, $args)
    {
        $q    = $this->getQueryOrNewSelect();
        $root = $this->_root;

        // Check if model has a scope of this name.
        if ($root && $root::hasScope($name))
        {
            return $root::applyScope($name, $this, $args);
        }

        // If 'with()' option is called, query builder will have to parse eager 
        // loaded models.
        if ($name === 'with' && $args)
        {
            $this->_eagerLoad = true;
        }

        // Redirect method calls on Query.
        $res = call_user_func_array(array($q, $name), $args);

        // If Query returns itself, we will prefer to return current
        // QueryBuilder instead. However, if it returns something else (A COUNT
        // has been performed and Query returns the result, for example), we
        // return the result.
        return $res === $q ? $this : $res;
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
     * Set the query.
     *
     * @param Query $q The query.
     */
    public function setQuery(Query $q)
    {
        $this->_query = $q;
    }

    /**
     * Return the current query instance or a new Select query if there is no
     * query yet.
     *
     * @see getQuery()
     * @see select()
     */
    public function getQueryOrNewSelect()
    {
        return $this->getQuery() ?: $this->newSelect();
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

    /**
     * Set the Connection.
     *
     * @param Connection $conn The connection.
     */
    public function setConnection(Connection $conn)
    {
        $this->_connection = $conn;
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
            $res = $res ? array_merge_recursive_distinct($res, $row) : $row;
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
        // It saves superfluous call to Connection instance.
        if ($this->_noRowToFetch) { return false; }

        $res = $this->getQuery()->getConnection()->getNextRow($next);

        // Do not try to call Connection next time, it's useless.
        if ($res === false) { $this->_noRowToFetch = true; }

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

    /**
     * Associate preloaded linked models to correct current models.
     *
     */
    protected function _associateLinkedModels(array $cmInstances, array $lmInstances, Relation $relation)
    {
        $cmAttr = $relation->getCmAttributes();
        $lmAttr = $relation->getLmAttributes();
        $rName  = $relation->name;

        foreach ($cmInstances as $cm)
        {
            foreach ($lmInstances as $lm)
            {
                if ($cm->get($cmAttr) === $lm->get($lmAttr))
                {
                    if ($relation->isToMany())
                    {
                        $tmp = $cm->$rName;
                        $tmp[] = $lm;
                        $cm->$rName = $tmp;
                    }
                    else
                    {
                        $cm->$rName = $lm;
                        break;
                    }
                }
            }
        }
    }

}
