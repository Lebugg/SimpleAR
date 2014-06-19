<?php namespace SimpleAR\Database;

require __DIR__ . '/Compiler/InsertCompiler.php';

use \SimpleAR\Database\Query;

abstract class Compiler
{
    /**
     * Do we need to use table alias in query?
     *
     * Indicate whether we are using models to build query. If false, we only use the
     * raw table name given in constructor. In this case, we would be enable to
     * use many features like process query on linked models.
     *
     * @var bool
     */
    public $useTableAlias = false;
    public $rootAlias = '_';

    public $components = array(
        'insert' => array(),
        'select' => array(),
        'update' => array(),
        'delete' => array(),
    );

    public function compile(Query $query)
    {
        $fn  = 'compile' . ucfirst($query->type);
        $sql = $this->$fn($query);

        return $sql;
    }

    protected function _compileComponents(Query $query, array $components)
    {
        $sql = array();

        foreach ($components as $component)
        {
            if ($query->$component)
            {
                $fn = '_compile' . ucfirst($component);
                $sql[$component] = $this->$fn($query->$component);
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
    protected function _compileAs($name, $alias)
    {
        if (! $name || ! $alias) { return ''; }

        return $this->wrap($name) . ' AS ' . $this->wrap($alias);
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

    public function wrap($string)
    {
        return '`' . $string . '`';
    }

    public function wrapArrayToString(array $a)
    {
        return '`' . implode('`,`', $a) . '`';
    }

    protected function _getAliasForTableName($tableName)
    {
        return strtolower($tableName);
    }
}
