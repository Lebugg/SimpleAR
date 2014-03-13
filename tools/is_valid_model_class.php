<?php namespace SimpleAR;
/**
 * This file contains the is_valid_model_class function.
 *
 */

/**
 * Check whether the given class is a valid model class.
 *
 * It checks two things:
 *
 *  * The class is a subclass is a subclass of SimpleAR\Model;
 *  * The class is not abstract.
 *
 * The function uses the ReflectionClass.
 *
 * @param  string $class The class to check.
 * @return bool True if the class is a valid model class, false otherwise.
 *
 * @see http://www.php.net/manual/en/class.reflectionclass.php
 */
function is_valid_model_class($class)
{
    try
    {
        $reflection = new \ReflectionClass($class);
    }
    // The class does not even exist.
    catch (ReflectionException $ex)
    {
        return false;
    }

    return $reflection->isSubclassOf('\SimpleAR\Model')
        && ! $reflection->isAbstract();
}
