<?php
namespace SimpleAR\Query;

require 'options/Conditions.php';
require 'options/Fields.php';
require 'options/Filter.php';
require 'options/GroupBy.php';
require 'options/Has.php';
require 'options/Limit.php';
require 'options/Offset.php';
require 'options/OrderBy.php';
require 'options/Values.php';
require 'options/With.php';

use \SimpleAR\MalformedOptionException;

abstract class Option
{
    protected $_value;
    protected $_query;
    protected $_arborescence;

    protected static $_optionToClass = array(
        'conditions' => 'Conditions',
        'fields'     => 'Fields',
        'filter'     => 'Filter',
        'group_by'   => 'GroupBy',
        'has'        => 'Has',
        'limit'      => 'Limit',
        'offset'     => 'Offset',
        'order_by'   => 'OrderBy',
        'values'     => 'Values',
        'with'       => 'With',
    );

    public function __construct($value, $context, $callback)
    {
        $this->_value        = $value;
        $this->_context      = $context;
        $this->_arborescence = isset($context->arborescence) ? $context->arborescence : null;
        $this->_callback     = $callback;
    }

    public abstract function build();

    public static function forge($optionName, $value, $query, $context)
    {
        if (!isset(self::$_optionToClass[$optionName]))
        {
            throw new MalformedOptionException('Use of unknown option "' .  $optionName . '".');
        }

        $sClass = '\SimpleAR\Query\Option\\' .  self::$_optionToClass[$optionName];

        return new $sClass($value, $context, array($query, $optionName));
    }
}
