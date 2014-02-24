<?php
/**
 * This file contains the Update class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

/**
 * This class handles SELECT COUNT(*) queries.
 */
class Count extends Select
{
    protected static $_options = array('conditions', 'has');

    protected $_columns = 'COUNT(*)';

    /**
     * The components of the query.
     *
     * They will be compiled in order of apparition in this array.
     * The order is important!
     *
     * @var array
     */
    protected static $_components = array(
        'columns',
        'from',
        'where',
        'groups',
        'havings',
    );

    protected function _compileColumns()
    {
        $d = $this->_distinct ? 'DISTINCT ' : '';
        $this->_sql .= 'SELECT ' . $d . $this->_columns;
    }

    /**
     * Count result getter.
     *
     * @see http://www.php.net/manual/en/pdostatement.fetch.php
     *
     * @return int The count result.
     */
    public function res()
    {
        return $this->_sth->fetch(\PDO::FETCH_COLUMN);
    }
}
