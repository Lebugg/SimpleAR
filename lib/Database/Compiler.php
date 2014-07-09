<?php namespace SimpleAR\Database;

require __DIR__ . '/Compiler/InsertCompiler.php';

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
     * @var bool
     */
    public $useTableAlias = false;

    public $useResultAlias = false;

    public $rootAlias = '_';

    public $components = array(
        'insert' => array(),
        'select' => array(),
        'update' => array(),
        'delete' => array(),
    );

    public function compile(Query $query, $type)
    {
        $fn  = 'compile' . ucfirst($type);
        $sql = $this->$fn($query);

        return $sql;
    }

    protected function _compileComponents(Query $query, array $components)
    {
        $sql = array();

        foreach ($components as $component)
        {
            if (isset($query->components[$component]))
            {
                $fn = '_compile' . ucfirst($component);
                $sql[$component] = $this->$fn($query->components[$component]);
            }
        }

        return $sql;
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

    public function column($column, $tablePrefix = '')
    {
        $tablePrefix = $tablePrefix ? $this->wrap($tablePrefix) . '.' : '';
        return $tablePrefix . $this->wrap($column);
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
