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

    const DEFAULT_ROOT_ALIAS = '_';

    /**
     * The Builder's type.
     *
     * It corresponds to one of the above const values.
     *
     * @var string
     */
    public $type;

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
    protected $_rootAlias;

    /**
     * The list of values to pass the query executor.
     *
     * @var array
     */
    protected $_values = array();

    /**
     * The list of given options.
     * 
     * @var array
     */
    protected $_options = array();

    /**
     * The list of built components.
     *
     * When an option is built, the resulting value(s) is stored in 
     * $_components.
     *
     * @var array
     */
    protected $_components;

    public function __construct()
    {
        $this->_rootAlias = self::DEFAULT_ROOT_ALIAS;
    }

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

        return $this->getComponents();
    }

    /**
     * Set an option.
     *
     * Options are defined in sub-builders. Options are here to allow the array
     * way of constructing query. This array way tends to be replaced by 
     * method-chaining.
     *
     * Example:
     * --------
     *
     * With an array:
     * ```php
     * MyModel::all(['conditions' => ['myAttr' => 'myVal'], 'limit' => 10]);
     * ```
     *
     * With method-chaining:
     * ```php
     * MyModel::where('myAttr', 'myVal')->limit(10)->all();
     * ```
     *
     * If an option is set twice, values will be merged with array_merge().
     * @see http://php.net/manual/en/function.array-merge.php
     */
    public function __call($name, $args)
    {
        if (! isset($args[1]) && isset($args[0]))
        {
            $args = $args[0];
        }

        $this->_options[$name] = isset($this->_options[$name])
            ? array_merge($this->_options[$name], (array) $args)
            : $args;

        return $this;
    }

    /**
     * Return the list of options that are waiting to be built.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Return currently built components.
     *
     * @return array
     */
    public function getComponents()
    {
        return $this->_components;
    }

    /**
     * Get option values.
     *
     * @return array The values.
     */
    public function getValues()
    {
        return $this->_values;
    }

    /**
     * Get the root table object.
     *
     * @return Table The root Table.
     */
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
     * Set the query root.
     *
     * Component: "root"
     * ----------
     *
     * @param string $root A valid model class name, or a DB table name.
     * @param string $alias An alias for the root. It will override the default 
     * that you can find here: @see ::$_rootAlias.
     */
    public function root($root, $alias = null)
    {
        $alias && $this->setRootAlias($alias);

        if (is_valid_model_class($root))
        {
            $this->setRootModel($root);
        }
        else
        {
            $this->setRootTableName($root);
        }

        $this->_components['root'] = $root;
        return $this;
    }

    /**
     * Set the root model.
     *
     * @param string $model The model class name.
     */
    public function setRootModel($model)
    {
        $this->_model    = $model;
        $this->_table    = $model::table();
        $this->_useModel = true;
    }

    /**
     * Set the name of table on which to execute the query.
     *
     * @param  string $tableName The table name.
     * @return $this
     */
    public function setRootTableName($tableName)
    {
        $this->_model    = null;
        $this->_table    = $tableName;
        $this->_useModel = false;
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
        return (array) $table->columnRealName($attributes);
    }

    /**
     * Add query value to value list.
     *
     * If $component is not given, it expects $value to be an array of
     * components' values.
     *
     * If value is a subquery, its values will be added to the current query;
     * but it won't be compiled. This is the Compiler's role. @see
     * Compiler::parameterize().
     *
     * @param mixed $value The value to add to the value list.
     * @param string $component The component type ('set', 'where'...).
     *
     * @return void
     */
    public function addValueToQuery($value, $component = '')
    {
        if ($value instanceof Query)
        {
            $this->addValueToQuery($value->getComponentValues(), $component);
            return;
        }

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

    /**
     * Build all unbuilt options.
     *
     * There is unbuilt options when the option-array syntax is used to build 
     * queries.
     *
     * Example:
     * --------
     *
     * When constructing a query as follows:
     * ```php
     * MyModel::one(array('limit' => 10, 'offset' => 50));
     * ```
     * "limit" and "offset" options will still be not built.
     *
     *
     * Algorithm:
     * ----------
     *
     * For each option name, this function will try to delegate the option
     * handling:
     *
     * <option>: The option name.
     *
     *  1) If a function named "_build<Option>" exists, call it. (Note the
     *  capital "O");
     *  2) Else, if a function named "<optionName>" exists, call it;
     *  3) Else, ignore the option.
     *
     * @param array $options The options to build.
     */
    protected function _buildOptions(array $options)
    {
        foreach ($options as $name => $value)
        {
            $fn = '_build' . ucfirst($name);
            if (method_exists($this, $fn))
            {
                $this->$fn($value);
            }
            elseif (method_exists($this, $name))
            {
                $this->$name($value);
            }
        }
    }

    /**
     * Event handler called at the end of build and before start of compilation.
     *
     * It can be overwritten  by builders.
     *
     * @return void
     */
    protected function _onAfterBuild()
    {
    }
}
