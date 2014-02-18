<?php namespace SimpleAR;
/**
 * This file contains the Database class.
 *
 * @author Lebugg
 */

require __DIR__ . '/Database/Connection.php';
require __DIR__ . '/Database/Expression.php';

/**
 * This class abstracts a database connection.
 *
 * It follows a Singleton pattern. It is not vulnerable to SQL injection, every
 * request is processed by binding parameters to it. So use parameters binding
 * every time you can in your SQL strings.
 *
 * How to execute a query:
 *  ```php
 *  $db->query($query, $params);
 *  ```
 */
class Database
{
    private $_connection;

    public function __construct(Config $config)
    {
        $this->_connection = new Database\Connection($config);
    }

    public function expr($expression)
    {
        return new Database\Expression($expression);
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
                case 0:
                    return $this->_connection->$method();
                case 1:
                    return $this->_connection->$method($args[0]);
                case 2:
                    return $this->_connection->$method($args[0], $args[1]);
                case 3:
                    return $this->_connection->$method($args[0], $args[1], $args[2]);
                default:
                    return call_user_func_array(array($this->_connection, $method), $args);
            }
        }

        throw new Exception('Method "' . $method . '" does not exist.');
    }
}
