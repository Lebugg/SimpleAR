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
use \SimpleAR\Query;

abstract class Option
{
    protected $_value;
    protected $_context;
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

    const SYMBOL_COUNT = '#';
    const SYMBOL_NOT   = '!';

    public function __construct($value, $context, $arborescence = null)
    {
        $this->_value        = $value;
        $this->_context      = $context;
        $this->_arborescence = $arborescence ?: (isset($context->arborescence) ?  $context->arborescence : null);
    }

    public abstract function build();

    public static function forge($optionName, $value, $context, $arborescence = null)
    {
        if (!isset(self::$_optionToClass[$optionName]))
        {
            throw new MalformedOptionException('Use of unknown option "' .  $optionName . '".');
        }

        $class = '\SimpleAR\Query\Option\\' .  self::$_optionToClass[$optionName];

        return new $class($value, $context, $arborescence);
    }
}
