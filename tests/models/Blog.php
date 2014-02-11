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
}
