<?php

class Offer extends SimpleAR\Model
{
    protected static $_aRelations = array(
        'company' => array(
            'type'  => 'belongs_to',
            'model' => 'Company',
        ),
    );
}