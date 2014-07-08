<?php namespace SimpleAR;
/**
 * This file contains the Database class.
 *
 * @author Lebugg
 */

require __DIR__ . '/Database/Connection.php';
require __DIR__ . '/Database/Expression.php';

use \SimpleAR\Database\Connection;
use \SimpleAR\Database\Compiler;
use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Database\Expression;

class Database
{
    private $_connection;

    public function __construct(Connection $conn)
    {
        $this->setConnection($conn);
        $this->chooseCompiler();
    }

    /**
     * Set the compiler according to database driver.
     *
     * If no adequate compiler is found for the driver, default compiler is 
     * used.
     *
     * @see setCompiler()
     * @see setDefaultCompiler()
     */
    public function chooseCompiler()
    {
        $specificCompiler = ucfirst($this->_connection->getDriver()) . 'Compiler';
        $specificCompiler = 'SimpleAR\Database\Compiler\\' . $specificCompiler;

        if (class_exists($specificCompiler)) {
            $this->setCompiler(new $specificCompiler);
        } else {
            $this->setDefaultCompiler();
        }
    }

    /**
     * Set the connecton.
     *
     * @param Connection $conn
     */
    public function setConnection(Connection $conn)
    {
        $this->_connection = $conn;
    }

    /**
     * Set the compiler.
     *
     * @param Compiler $compiler
     */
    public function setCompiler(Compiler $compiler)
    {
        $this->_compiler = $compiler;
    }

    /**
     * Set default compiler.
     *
     * @see setCompiler()
     * @see Database\Compiler\BaseCompiler
     */
    public function setDefaultCompiler()
    {
        $this->setCompiler(new BaseCompiler);
    }

    /**
     * Construct an Expression based on given string.
     *
     * @param string $expression
     * @return Expression
     */
    public function expr($expression)
    {
        return new Expression($expression);
    }

    /**
     * Return the Connection instance.
     *
     * @var Connection
     */
    public function connection()
    {
        return $this->_connection;
    }

    /**
     * Return the Compiler instance.
     *
     * @var Compiler
     */
    public function compiler()
    {
        return $this->_compiler;
    }

    /**
     * Forward function call to the Connection instance.
     *
     * @param  string $method The name of the called method.
     * @param  array  $args   Array of arguments to pass to the method.
     * @return mixed
     *
     * @throws SimpleAR\Exception when the method does not exist in the
     * Connection instance.
     */
    public function __call($method, $args)
    {
        if (method_exists($this->_connection, $method))
        {
            // It is better to call the method via straight statement.
            // @see http://www.php.net/manual/fr/function.call-user-func-array.php#97473
            switch (count($args))
            {
                case 0: return $this->_connection->$method();
                case 1: return $this->_connection->$method($args[0]);
                case 2: return $this->_connection->$method($args[0], $args[1]);
                case 3: return $this->_connection->$method($args[0], $args[1], $args[2]);
                default: return call_user_func_array(array($this->_connection, $method), $args);
            }
        }

        throw new Exception('Method "' . $method . '" does not exist.');
    }
}
