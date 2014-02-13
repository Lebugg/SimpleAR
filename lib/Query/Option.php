<?php
namespace SimpleAR\Query;

require __DIR__ . '/Option/Conditions.php';
require __DIR__ . '/Option/Fields.php';
require __DIR__ . '/Option/Filter.php';
require __DIR__ . '/Option/GroupBy.php';
require __DIR__ . '/Option/Has.php';
require __DIR__ . '/Option/Limit.php';
require __DIR__ . '/Option/Offset.php';
require __DIR__ . '/Option/OrderBy.php';
require __DIR__ . '/Option/Values.php';
require __DIR__ . '/Option/With.php';

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
