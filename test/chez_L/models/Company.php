<?php

class Company extends SimpleAR\Model
{
    protected static $_aRelations = array(
        'offers' => array(
            'type'  => 'has_many',
            'model' => 'Offer',
        ),
    );
}