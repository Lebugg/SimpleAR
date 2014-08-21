<?php namespace SimpleAR\Database;

require __DIR__ . '/Builder/InsertBuilder.php';
require __DIR__ . '/Builder/WhereBuilder.php';
require __DIR__ . '/Builder/SelectBuilder.php';
require __DIR__ . '/Builder/UpdateBuilder.php';
require __DIR__ . '/Builder/DeleteBuilder.php';

use \SimpleAR\Database\Expression;
use \SimpleAR\Database\Query;
use \SimpleAR\Orm\Table;

class Builder
{
    const INSERT = 'insert';
    const SELECT = 'select';
    const UPDATE = 'update';
    const DELETE = 'delete';

    /**
     * The query to build.
     *
     * @var Query
     */
    protected $_query;

    /**
     * The "root table" of the query. If set, it means that we are using a model 
     * to construct the query: i.e. User::all();
     *
     * @var Table
     */
    protected $_table;

    /**
     * The "root" model class name.
     *
     * @var string
     */
    protected $_model;

    /**
     * Are we using model?
     *
     * Indicate whether we use table aliases. If true, every table field use in
     * query will be prefix by the corresponding table alias.
     *
     * @var bool
     */
    protected $_useModel = false;

    /**
     * The alias to use for root table.
     *
     * Table aliases are based on model relation names. Since root model is the
     * root, there is no relation name for it.
     *
     * @var string
     */
    protected $_rootAlias = '_';

    /**
     * The list of values to pass the query executor.
     *
     * @var array
     */
    protected $_values = array();

    protected $_options = array();
    protected $_components;

    /**
     * Available options for this builder.
     *
     * @var array
     */
    public $availableOptions = array(
        // "root" option can only be a string.
        // It can be: either a valid model class name, or a any other string, 
        // interpreted as the root table name. In the second case, many features 
        // are not available.
        'root',
    );

    public $type;

    /**
     * Run the build process.
     *
     * @param array $options Options to build. If given, it will erase 
     * previously set options.
     *
     * @return array The built components. Components are to be passed to a 
     * Compiler.
     */
    public function build(array $options = array())
    {
        $this->_options = $options ?: $this->_options;
        $this->_buildOptions($this->_options);

        // Allow specific builders to set default values after build.
        $this->_onAfterBuild();

        return $this->_components;
    }

    public function __call($name, $args)
    {
        if (! isset($args[1]) && isset($args[0]))
        {
            $args = $args[0];
        }

        $this->_options[$name] = $args;
        return $this;
    }

    /**
     * Set the root model class.
     *
     * @param  string $class A valid model class name.
     */
    public function useRootModel($class)
    {
        $this->_useModel = true;
        $this->setRootModel($class);
    }

    /**
     * Set the name of table on which to execute the query.
     *
     * @param  string $tableName The table name.
     * @return $this
     */
    public function useRootTableName($tableName)
    {
        $this->_useModel = false;
        $this->_root = $tableName;
    }

    public function getValues()
    {
        return $this->_values;
    }

    public function getRootTable()
    {
        return $this->_table;
    }

    /**
     * Get the root alias.
     *
     * @return string The root alias
     */
    public function getRootAlias()
    {
        return $this->_rootAlias;
    }

    /**
     * Set the root alias.
     *
     * @param string $alias The alias to set as root.
     */
    public function setRootAlias($alias)
    {
        $this->_rootAlias = $alias;
    }

    /**
     * Get the root model class.
     *
     * @return string The root model class.
     */
    public function getRootModel()
    {
        return $this->_model;
    }

    /**
     * Set the root model class name.
     *
     * @param string $model The model class name.
     * @return void
     */
    public function setRootModel($model)
    {
        $this->_model    = $model;
        $this->_table    = $model::table();
        $this->_useModel = true;
    }

    /**
     * Convert attribute names to column names.
     *
     * @param string|array $attributes An attribute name or an array of it.
     * @param Table $table The attributes's table.
     * @return string|array A column name or an array of it.
     */
    public function convertAttributesToColumns($attributes, Table $table)
    {
        return $table->columnRealName($attributes);
    }

    /**
     * Add query value to value list.
     *
     * If $component is not given, it expects $value to be an array of 
     * components' values.
     *
     * @param mixed $value The value to add to the value list.
     * @param string $component The component type ('set', 'where'...).
     *
     * @return void
     */
    public function addValueToQuery($value, $component = '')
    {
        if ($component)
        {
            // Any check on value type (Model instance, Expression...) is made
            // in Query::prepareValuesForExecution().
            $this->_values[$component][] = $value;
        }
        else
        {
            foreach ($value as $component => $values)
            {
                $this->addValueToQuery($values, $component);
            }
        }
    }

    /**
     * Clear build() result.
     *
     * A builder can `build()` options any number of time. But at each run, 
     * previous result must be cleared.
     *
     * It clears components and values.
     */
    public function clearResult()
    {
        $this->_components = array();
        $this->_values = array();
    }

    /**
     * Remove options from builder.
     *
     * @param array $toRemove The options to remove.
     */
    public function removeOptions(array $toRemove)
    {
        foreach ($toRemove as $option)
        {
            unset($this->_options[$option]);
        }
    }

    protected function _buildOptions(array $options)
    {
        foreach ($this->availableOptions as $option)
        {
            if (isset($options[$option]))
            {
                $fn = '_build' . ucfirst($option);
                $this->$fn($options[$option]);
            }
        }
    }

    /**
     * Set the "root" of the query.
     *
     * It can be:
     *  
     *  * A valid model class name.
     *  * A table name.
     *
     * @param  string $root The query root.
     */
    protected function _buildRoot($root)
    {
        $this->root($root);
    }

    public function root($root)
    {
        if (\SimpleAR\is_valid_model_class($root))
        {
            $this->useRootModel($root);
        }
        else
        {
            $this->useRootTableName($root);
        }

        $this->_components['root'] = $root;
        return $this;
    }

    /**
     * Generate an alias out of a table name.
     *
     * We need a function that will give a unique alias for a given table name.
     *
     * @param string $tableName The table name.
     * @return string The alias.
     */
    protected function _getAliasForTableName($tableName)
    {
        return strtolower($tableName);
    }

    /**
     * Event handler called at the end of build and before start of compilation.
     *
     * It can be overwrittent by builders.
     *
     * @return void
     */
    protected function _onAfterBuild()
    {
    }
}
