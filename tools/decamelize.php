<?php

/**
 * Taken from: http://stackoverflow.com/a/19533226/2119117
 *
 * Transform a camelCase to camel_case.
 *
 * @param string $input The string to decamelize.
 * @return string
 */
function decamelize($input)
{
    return ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $input)), '_');
}
