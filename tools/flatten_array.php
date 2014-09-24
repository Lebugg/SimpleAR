<?php

/**
 * Flatten an array.
 *
 * Taken from:
 * * http://stackoverflow.com/a/1320259/2119117
 * * http://codepad.org/w8aqaTBF
 */
function flatten_array(array $array)
{
    return iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($array)), false);
}
