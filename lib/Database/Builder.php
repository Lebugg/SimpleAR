<?php namespace SimpleAR\Database;

require __DIR__ . '/Builder/InsertBuilder.php';

use \SimpleAR\Database\Query;
use \SimpleAR\Orm\Table;

class Builder
{
    protected $_query;
    protected $_table;

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
     * Available options for this builder.
     *
     * @var array
     */
    public $availableOptions = array();

    public function build(Query $query, array $options)
    {
        $this->_query = $query;
        $this->_buildOptions($options);
    }

    public function setTable(Table $table)
    {
        $this->_table    = $table;
        $this->_useModel = true;
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

}
