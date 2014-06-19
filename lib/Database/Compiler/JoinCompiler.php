<?php namespace SimpleAR\Database\Compiler;

use \SimpleAR\Database\Compiler;

class JoinCompiler extends Compiler
{
    public $components = array(
        'joins',
    );

    protected function _compileJoins(array $joins)
    {
        return '';
    }
}
