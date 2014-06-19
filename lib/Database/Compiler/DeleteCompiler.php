<?php namespace SimpleAR\Database\Compiler;

use \SimpleAR\Database\Compiler;

class DeleteCompiler extends Compiler
{

    public $components = array(
        'from',
        'where'
    );

}
