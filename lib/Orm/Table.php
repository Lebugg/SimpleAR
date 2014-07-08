<?php namespace SimpleAR\Orm;

use \SimpleAR\Exception;

class Table
{
    public $name;
    public $primaryKey;
    public $primaryKeyColumns;
    public $columns;
    public $orderBy;
    public $conditions = array();
    public $modelBaseName;

	// Constructed.
	public $alias;
    public $isSimplePrimaryKey;

    public function __construct($name, $primaryKey, $columns)
    {
        $this->name       = $name;
        $this->primaryKey = $primaryKey;
        $this->columns    = $columns;

        /**
         * Allows $_columns declaration like:
         * array(
         *      'attrName'  => 'colName',
         *      'attr2Name' => 'col2Name',
         *      'col3Name',
         *      ...
         * ),
         *
         * This way, attribute to column translation is super easy and
         * practical.
         */
        foreach ($columns as $key => $value)
        {
            if (is_int($key))
            {
                $this->columns[$value] = $value;
                unset($this->columns[$key]);
            }
        }

		$this->alias		      = '_' . strtolower($name);
        $this->isSimplePrimaryKey = is_string($primaryKey);

        $this->primaryKeyColumns  = $this->isSimplePrimaryKey ? $this->primaryKey : $this->columnRealName($this->primaryKey);
    }

    /**
     * Gives a column name according to its key that is a class member name.
     *
     * @param string|array $key The key of $_columns. If $key is equal to "id", it
     * will return the model primary key.
     *
     * @return string|array The DB field name.
     */
    public function columnRealName($key)
    {
        if (! $key) { return $key; }

        $res = array();
        foreach ((array) $key as $key)
        {
            if (! isset($this->columns[$key]) && $key !== 'id')
            {
                throw new Exception('Attribute "' . $key . '" does not exist for model "' .  $this->modelBaseName . '".');
            }

            $res[] = $key === 'id'
                ? $this->primaryKeyColumns
                : $this->columns[$key]
                ;
        }

        // Return a string if only one element.
        return isset($res[1]) ? $res : $res[0];
    }

    public function hasAttribute($s)
    {
        return $s == 'id' || (isset($this->columns[$s]) || array_key_exists($this->columns[$s]));
    }

    /**
     * Return the primary key column(s).
     *
     * It always return an array, even if primary key is not compound.
     *
     * @return array
     */
    public function getPrimaryKey()
    {
        return (array) $this->primaryKey;
    }

    /**
     * Get *all* table columns, including primary keys.
     *
     * It returns an associative between attribute names and column names.
     *
     * @return array
     */
    public function getColumns()
    {
        $columns = $this->columns;

        $pk = (array) $this->primaryKey;
        $pkCols = (array) $this->primaryKeyColumns;
        foreach ($pk as $i => $attrName)
        {
            $columns[$attrName] = $pkCols[$i];
        }

        return $columns;
    }
}
