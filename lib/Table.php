<?php
namespace SimpleAR;

class Table
{
    public $name;
    public $primaryKey;
    public $primaryKeyColumns;
    public $columns;
    public $orderBy;
    public $modelBaseName;

	// Constructed.
	public $alias;
    public $isSimplePrimaryKey;

    public function __construct($sName, $mPrimaryKey, $aColumns)
    {
        $this->name       = $sName;
        $this->primaryKey = $mPrimaryKey;
        $this->columns    = $aColumns;

        /**
         * Allows $_aColumns declaration like:
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
        foreach ($aColumns as $mKey => $sValue)
        {
            if (is_int($mKey))
            {
                $this->columns[$sValue] = $sValue;
                unset($this->columns[$mKey]);
            }
        }

		$this->alias		      = '_' . strtolower($sName);
        $this->isSimplePrimaryKey = is_string($mPrimaryKey);

        $this->primaryKeyColumns  = $this->isSimplePrimaryKey ? $this->primaryKey : $this->columnRealName($this->primaryKey);
    }

    /**
     * Gives a column name according to its key that is a class member name.
     *
     * @param string|array $mKey The key of $_aColumns. If $mKey is equal to "id", it
     * will return the model primary key.
     *
     * @return string|array The DB field name.
     */
    public function columnRealName($mKey)
    {
        $aRes = array();
        foreach ((array) $mKey as $sKey)
        {
            $aRes[] = $sKey === 'id'
                ? $this->primaryKeyColumns
                : $this->columns[$sKey]
                ;
        }

        // Return a string if only one element.
        return isset($aRes[1]) ? $aRes : $aRes[0];
    }

    public function hasColumn($s)
    {
        return $s == 'id' || (isset($this->columns[$s]) || array_key_exists($this->columns[$s]));
    }
}
