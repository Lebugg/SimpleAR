<?php namespace SimpleAR\Database\Option;

use \SimpleAR\Database\Option;
use \SimpleAR\Database\Expression;

class Group extends Option
{
    protected static $_name = 'group';

    public $groups = array();

    public function build($useModel, $model = null)
    {
        if (! is_array($this->_value))
        {
            $this->_value = array($this->_value);
        }

        foreach ($this->_value as $group)
        {
            // Raw SQL expression.
            if ($group instanceof Expression)
            {
                $attribute = $group->val();
                $toColumn  = false;
                $relations = array();
            }

            // Classic group option.
            //
            // Example:
            // --------
            //
            //  'my/relation/attribute'
            //  'myAttribute'
            else
            {
                $relations = explode('/', $group);

                $attribute = array_pop($relations);
                $toColumn  = $useModel;
            }

            $this->groups[] = array(
                'attribute' => $attribute,
                'toColumn'  => $toColumn,
                'relations' => $relations,
            );
        }
    }
}
