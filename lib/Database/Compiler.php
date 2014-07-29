<?php namespace SimpleAR\Database;

use \SimpleAR\Database\Query;
use \SimpleAR\Database\Expression;

abstract class Compiler
{
    /**
     * Do we need to use table alias in query?
     *
     * Indicate whether we are using models to build query. If false, we only use the
     * raw table name given in constructor. In this case, we would not be able to
     * use many features like process query on linked models.
     *
     * Property's value is set when starting the compilation step. Each public
     * compile can decide whether to use table alias or not depending on number
     * of used tables or things like that.
     *
     * If $useTableAlias is null when compilation starts, the compiler will 
     * decide himself whether to use table aliases or not.
     *
     * @var bool
     */
    public $useTableAlias = null;

    public $useResultAlias = false;

    public $rootAlias = '_';

    public $components = array(
        'insert' => array(),
        'select' => array(),
        'update' => array(),
        'delete' => array(),
    );

    /**
     * Compile an Query.
     *
     * @param Query $query The query to compile.
     * @return [string $sql, array $values]
     */
    public function compile(Query $query)
    {
        $type = $query->getType();

        $sql = $this->compileComponents($query->getComponents(), $type);
        $val = $this->compileValues($query->getComponentValues(), $type);

        return array($sql, $val);
    }

    /**
     * Compile components.
     *
     * This is the entry point of the compiler.
     *
     * @param array $components The query components to compile.
     * @param array $values The values associated to components.
     * @param string $type The query type.
     */
    public function compileComponents(array $components, $type)
    {
        $fn  = 'compile' . ucfirst($type);
        return $this->$fn($components);
    }

    protected function _compileComponents(array $availableComponents, array $actualComponents)
    {
        $sql = array();
        $val = array();

        foreach ($availableComponents as $component)
        {
            if (isset($actualComponents[$component]))
            {
                $fn = '_compile' . ucfirst($component);
                $sql[$component] = $this->$fn($actualComponents[$component]);

            }
        }

        return $sql;
    }

    /**
     * Compile query values in well-sorted array.
     *
     * It transforms values grouped by component type into a plain value array 
     * ordered according to query component order (@see $components).
     *
     * Example:
     * --------
     *
     * $values: ['where' => [1, 2, 3], 'set' => ['Value to set']].
     * Return: ['Value to set', 1, 2, 3].
     *
     * @param array $values Component values to sort.
     * @return array Compiled values.
     *
     * @see \SimpleAR\Database\Query::getComponentValues()
     */
    public function compileValues(array $values, $type)
    {
        $components = $this->components[$type];
        $val = array();
        foreach($this->components[$type] as $component)
        {
            if (isset($values[$component]))
            {
                $val = $val ? array_merge($val, $values[$component]) : $values[$component];
            }
        }

        return $val;
    }

    protected function _concatenate(array $components)
    {
        return implode(' ', $components);
    }

    /**
     * Compile an AS clause.
     *
     * @param string $name The identifier to alias.
     * @param string $alias The identifier alias.
     * @parm bool $useAlias Should we really alias the identifier? This boolean 
     * may seem stupid, but it prevents us to check that we are using table 
     * alias or not.
     */
    protected function _compileAs($alias)
    {
        return $alias ? ' AS ' . $this->wrap($alias) : '';
    }

    /**
     * Compile a table alias.
     *
     * @param string $identifier The identifier to alias.
     * @param string $alias The identifier alias.
     */
    protected function _compileAlias($identifier, $alias = '')
    {
        $sql = $this->wrap($identifier);

        if ($alias)
        {
            $sql .= ' ' . $this->wrap($alias);
        }

        return $sql;
    }

    /**
     * Get appropriate placeholders for values.
     *
     * It handles:
     *  
     *  * array of values;
     *  * array of array of values;
     *  * Expression;
     *  * array of Expressions;
     *  ...
     *
     * @param mixed $value
     */
    public function parameterize($value)
    {
        // This allows users to directly pass object or object array as
        // a condition value.
        //
        // The same check is done in Compiler to correctly construct
        // query.
        // @see SimpleAR\Database\Compiler::parameterize()
        if ($value instanceof \SimpleAR\Orm\Model)
        {
            return $this->parameterize($value->id());
        }

        if (is_array($value))
        {
            // This line is taken from:
            // @see https://github.com/illuminate/database/blob/master/Grammar.php
            $list = implode(',', array_map(array($this, 'parameterize'), $value));
            return '(' . $list . ')';
        }

        return $value instanceof Expression
            ?  $value->val()
            : '?';
    }

    public function column($column, $tablePrefix = '', $alias = '')
    {
        $tablePrefix = $tablePrefix ? $this->wrap($tablePrefix) . '.' : '';
        $alias = $alias ? ' AS ' . $this->wrap($alias) : '';

        return $tablePrefix . $this->wrap($column) . $alias;
    }

    /**
     * Apply aliases to columns.
     *
     * It may be necessary to add alias to columns:
     * - we want to prefix columns with table aliases;
     * - we want to rename columns in query result (only for Select queries).
     *
     * @param array $columns The column array. It can take three forms:
     * - an indexed array where values are column names;
     * - an associative array where keys are column names and values are
     * attribute names (for column renaming in result. Select queries only);
     * - a mix of both. Values of numeric entries will be taken as column names.
     *
     * @param array  $columns
     * @param string $tablePrefix  The table alias to prefix the column with.
     * @param string $resultPrefix The result alias to rename the column into.
     *
     * @return string SQL
     */
    public function columnize(array $columns, $tablePrefix = '', $resultPrefix = '')
    {
        $tablePrefix = $tablePrefix ? $this->wrap($tablePrefix) . '.' : '';
        $resultPrefix = $resultPrefix ? $resultPrefix . '.' : '';

        $sql = array();
        foreach($columns as $column => $attribute)
        {
            $left = $right = '';

            if (is_string($column))
            {
                $left  = $tablePrefix . $this->wrap($column);
                $right = $this->wrap($resultPrefix . $attribute);
            }
            else
            {
                $left = $tablePrefix . $this->wrap($attribute);
                if ($resultPrefix)
                {
                    $right = $this->wrap($resultPrefix . $attribute);
                }
            }

            $sql[] = $right ? $left . ' AS ' . $right : $left;
        }

        return implode(',', $sql);
    }

    /**
     * Wrap an identifier: column, table name, alias...
     *
     * @param string $string The identifier to wrap.
     * @return Safe wrapped identifier.
     */
    public function wrap($string)
    {
        if ($string === '*') { return $string; }
        if ($string instanceof Expression) { return $string->val(); }

        return '`' . $string . '`';
    }

    /**
     * Wrap an array of identifiers and return it as a string.
     *
     * @param array $a
     * @return string SQL
     *
     * @see wrap()
     */
    public function wrapArrayToString(array $a)
    {
        return implode(',', array_map(array($this, 'wrap'), $a));
    }
}
