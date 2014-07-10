<?php namespace SimpleAR\Orm;

use \SimpleAR\Exception;

class Table
{
    public $name;
    public $primaryKey;
    public $columns;
    public $orderBy;
    public $conditions = array();
    public $modelBaseName;

    public function __construct($name, $primaryKey, array $columns = array())
    {
        $this->name = $name;

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
                $columns[$value] = $value;
                unset($columns[$key]);
            }
        }

        // @deprecated
        if (is_string($primaryKey))
        {
            $columns['id'] = $primaryKey;
            $primaryKey = array('id');
        }
        // Right way.
        else
        {
            foreach ($primaryKey as $attr)
            {
                if (! isset($columns[$attr]))
                {
                    $columns[$attr] = $attr;
                }
            }
        }

        $this->columns = $columns;
        $this->primaryKey = $primaryKey;
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

        $keys = (array) $key;
        $res  = array();

        foreach ($keys as $key)
        {
            if (! isset($this->columns[$key]))
            {
                throw new Exception('Attribute "' . $key . '" does not exist for model "' .  $this->modelBaseName . '".');
            }

            $res[] = $this->columns[$key];
        }

        // Return a string if only one element.
        return isset($res[1]) ? $res : $res[0];
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
        return $this->primaryKey;
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
        return $this->columns;
    }

    /**
     * Check whether primary key is a compound key.
     *
     * @return bool True if primary key is compound, false otherwise.
     */
    public function hasCompoundPK()
    {
        return count($this->primaryKey) > 1;
    }
}
