<?php

class Article extends SimpleAR\Model
{
    public function to_conditions_relevant($attribute)
    {
        return array(array('content', 'LIKE', '%pokemon%'));
    }
}
