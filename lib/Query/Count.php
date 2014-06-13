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
    protected static $_options = array('filter', 'conditions', 'has', 'group_by');

    protected $_filter = array('COUNT(*)');

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
