<?php namespace SimpleAR\Database\Compiler;

use \SimpleAR\Database\Compiler;

class WhereCompiler extends Compiler
{
    public $components = array(
        'where',
    );

    protected function _compileWhere($where)
    {
        return '';
    }
}
