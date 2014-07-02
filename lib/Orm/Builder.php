<?php namespace SimpleAR\Orm;

use SimpleAR\Database\Query;
use SimpleAR\Database\Builder as QueryBuilder;
use SimpleAR\Database\Builder\InsertBuilder;
use SimpleAR\Database\Builder\SelectBuilder;
use SimpleAR\Database\Builder\UpdateBuilder;
use SimpleAR\Database\Builder\DeleteBuilder;
use SimpleAR\Database\Compiler;
use SimpleAR\Database\Connection;
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

    public function __construct($root = null)
    {
        $root && $this->root($root);
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

    /**
     * Get the compiler instance.
     *
     * @return Compiler
     */
    public function getCompiler()
    {
        return $this->_compiler = ($this->_compiler ?: DB::compiler());
    }

    /**
     * Get the connection instance.
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->_connection = ($this->_connection ?: DB::connection());
    }

    public function delete($root = '')
    {
        $query = $this->newQuery(new DeleteBuilder);
        $root && $this->root($root);

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
        $this->_root && $q->root($this->_root);

        return $this->_query = $q;
    }

    /**
     * Return the current query instance or a new Select query if there is no 
     * query yet.
     *
     * @see newQuery()
     */
    public function getQueryOrNewSelect()
    {
        if (! $this->_query)
        {
            $this->_query = $this->newQuery(new SelectBuilder);
        }

        return $this->_query;
    }

    public function setOptions(array $options)
    {
        $q = $this->getQueryOrNewSelect();

        foreach ($options as $name => $value)
        {
            $q->$name($value);
        }

        return $this;
    }

    public function getTableColumns($table)
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

        return $this->_getModelFromRow($q->getConnection()->getNextRow());
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

        return $this->_getModelFromRow($q->getConnection()->getLastRow());
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
        while ($one = $this->_getModelFromRow($q->getConnection()->getNextRow()))
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
     * Fetch next row from DB and create a model instance out of it.
     *
     * @return Model
     */
    protected function _getModelFromRow($row)
    {
        if ($row)
        {
            $class = $this->_root;
            return $class::createFromRow($row);
        }
    }

    /**
     * Return first fetch Model instance.
     *
     * @return Model
     */
    protected function row()
    {
        $res = array();

        $reversedPK = $this->_table->isSimplePrimaryKey
            ? array('id' => 0)
            : array_flip((array) $this->_table->primaryKey);

        $resId      = null;

        // We want one resulting object. But we may have to process several lines in case that eager
        // load of related models have been made with has_many or many_many relations.
        while (
               ($row = $this->_pendingRow)                    !== false ||
               ($row = $this->_sth->fetch(\PDO::FETCH_ASSOC)) !== false
        ) {

            if ($this->_pendingRow)
            {
                // Prevent infinite loop.
                $this->_pendingRow = false;
                // Pending row is already parsed.
                $parsedRow = $row;
            }
            else
            {
                $parsedRow = $this->_parseRow($row);
            }

            // New main object, we are finished.
            if ($res && $resId !== array_intersect_key($parsedRow, $reversedPK)) // Compare IDs
            {
                $this->_pendingRow = $parsedRow;
                break;
            }

            // Same row but there is no linked model to fetch. Weird. Query must be not well
            // constructed. (Lack of GROUP BY).
            if ($res && !isset($parsedRow['_WITH_']))
            {
                continue;
            }

            // Now, we have to combined new parsed row with our constructing result.

            if ($res)
            {
                // Merge related models.
                $res = \SimpleAR\array_merge_recursive_distinct($res, $parsedRow);
            }
            else
            {
                $res   = $parsedRow;

                // Store result object ID for later use.
                $resId = array_intersect_key($res, $reversedPK);
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
     * @return array
     */
    private function _parseRow($row)
    {
        $res = array();

        foreach ($row as $key => $value)
        {
            // We do not want null values. It would result with linked model instances with null 
            // attributes and null IDs. Moreover, it reduces process time (does not process useless 
            // null-valued attributes).
            //
            // EDIT
            // ----
            // Note: Now, the check is made in Model::_load(). We don't keep linked models with null 
            // ID at that moment.
            // Why: by discarding attributes with null value here, object's attribute array was not 
            // filled with them and we were forced to check attribute presence in columns definition 
            // in Model::__get(). Not nice.
            //
            // if ($value === null) { continue; }

            // Keys are prefixed with an alias corresponding:
            // - either to the root model;
            // - either to a linked model (thanks to the relation name).
            if ($this->_useResultAlias)
            {
                $a = explode('.', $key);

                if ($a[0] === $this->_rootAlias)
                {
                    // $a[1]: table column name.

                    $res[$a[1]] = $value;
                }
                else
                {
                    // $a[0]: relation name.
                    // $a[1]: linked table column name.

                    $res['_WITH_'][$a[0]][$a[1]] = $value;
                }
            }

            // Much more simple in that case.
            else
            {
                $res[$key] = $value;
            }
        }

        return $res;
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
        $q = $this->newQuery(new SelectBuilder);

        switch (count($args))
        {
            case 0: $q->$name(); break;
            case 1: $q->$name($args[0]); break;
            case 2: $q->$name($args[0], $args[1]); break;
            case 3: $q->$name($args[0], $args[1], $args[2]); break;
            case 4: $q->$name($args[0], $args[1], $args[2], $args[3]); break;
            default: call_user_func_array(array($q, $name), $args);
        }

        return $this;
    }
}
