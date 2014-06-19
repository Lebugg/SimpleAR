<?php

class Article extends SimpleAR\Orm\Model
{
    public static function to_conditions_relevant($attribute)
    {
        return array(array('content', 'LIKE', '%pokemon%'));
    }
}
