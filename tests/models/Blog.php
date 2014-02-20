<?php

class Blog extends SimpleAR\Model
{
    protected static $_columns = array(
        'description',
        'name',
        'url',
    );

    protected static $_filters = array(
        'restricted' => array(
            'name',
            'url',
        ),
    );

    protected static $_relations = array(
        'articles' => array(
            'type'  => 'has_many',
            'model' => 'Article',
        ),
    );

    public function get_x()
    {
        return 'x';
    }
}
