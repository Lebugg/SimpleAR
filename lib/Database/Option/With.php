<?php namespace SimpleAR\Database\Option;

use \SimpleAR\Database\Query;
use \SimpleAR\Database\Option;
use \SimpleAR\Database\Arborescence;

use \SimpleAR\Exception\MalformedOption;

class With extends Option
{
    protected static $_name = 'with';

    public $aggregates = array();
    public $groups = array();
    public $with   = array();

    public function build($useModel, $model = null)
    {
        if (! $useModel)
        {
            throw new MalformedOption('Cannot use "with" option when not using models.');
        }

        foreach((array) $this->_value as $with)
        {
            $firsts = explode('/', $with);
            $last   = array_pop($firsts);

            switch ($last[0])
            {
                case self::SYMBOL_COUNT:
                    $last = substr($last, 1);

                    $this->aggregates[] = array(
                        'relations' => array_merge($firsts, array($last)),
                        'attribute' => 'id',
                        'toColumn'  => true,
                        'fn'        => 'COUNT',

                        'asRelations' => $firsts,
                        'asAttribute' => self::SYMBOL_COUNT . $last,
                    );

                    $this->groups[] = array(
                        'relations' => $firsts,
                        'attribute' => 'id',
                        'toColumn'  => true,
                    );

                    $this->withs[] = array(
                        'relations' => $firsts,
                    );
                    
                    // For "continue", switch is a loop.
                    // http://www.php.net/manual/en/control-structures.continue.php
                    continue 2;
            }

            $this->withs[] = array(
                'relations' => array_merge($firsts, array($last)),
            );
        }
    }
}
