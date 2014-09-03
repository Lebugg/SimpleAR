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
use \SimpleAR\Database\Expression\Func as FuncExpr;

class Database
{
    private $_connection;

    public function __construct(Connection $conn = null)
    {
        $conn && $this->setConnection($conn);
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
     * Alias for `expr()`.
     */
    public function raw($expression)
    {
        return $this->expr($expression);
    }

    /**
     * Return an Func Expression.
     *
     * @param  string $attribute An extended attribute string.
     * @param  string $fn The aggregate function.
     * @return Database\Expression\Func
     */
    public function fn($attribute, $fn)
    {
        return new FuncExpr($attribute, $fn);
    }

    /**
     * Return an Func Expression for 'AVG' function.
     *
     * @param  string $attribute An extended attribute string.
     * @return Database\Expression\Func
     */
    public function avg($attribute)
    {
        return $this->fn($attribute, 'AVG');
    }

    /**
     * Return an Func Expression for 'COUNT' function.
     *
     * @param  string $attribute An extended attribute string.
     * @return Database\Expression\Func
     */
    public function count($attribute)
    {
        return $this->agg($attribute, 'COUNT');
    }

    /**
     * Return an Func Expression for 'SUM' function.
     *
     * @param  string $attribute An extended attribute string.
     * @return Database\Expression\Func
     */
    public function sum($attribute)
    {
        return $this->fn($attribute, 'SUM');
    }

    /**
     * Return the Connection instance.
     *
     * @var Connection
     */
    public function getConnection()
    {
        return $this->_connection;
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
     * Return the Compiler instance.
     *
     * @var Compiler
     */
    public function getCompiler()
    {
        return $this->getConnection()->getCompiler();
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
            // A day'll come: 5.6 style!
            // $this->_connection->$method(...$args);

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
