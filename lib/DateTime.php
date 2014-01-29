<?php
/**
 * This file contains a extension of PHP DateTime class.
 */
namespace SimpleAR;

/**
 * This extension implements __toString() function to automatically format DateTime when
 * using instance as a string.
 *
 * In combination, we use a member variable that holds the date format to use. This property at
 * initialization of SimpleAR so that it can be changed if application internationalized.
 *
 * Thanks to gmblar+php at gmail dot com
 * at http://www.php.net/manual/fr/class.datetime.php#95830
 * for this DateTime extension.
 */
class DateTime extends \DateTime
{
    /**
     * Date format to use when trying to use DateTime instance as string.
     *
     * @var string Default: 'Y-m-d';
     */
    private static $_format = 'Y-m-d';

    /**
     * PHP magical __toString method.
     *
     * When the DateTime will be used as a string, this function will be magically fired and it will
     * automatically format the DateTime to the format specified by `$_format` property.
     *
     * @return string
     *
     * @see DateTime::$_format.
     */
    public function __toString()
    {
        return $this->format(self::$_format);
    }

    /**
     * Date format setter.
     *
     * @param string $s The date format to use.
     */
    public static function setFormat($s)
    {
        self::$_format = $s;
    }
}
