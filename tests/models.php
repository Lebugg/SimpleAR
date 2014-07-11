<?php

class Blog extends SimpleAR\Orm\Model
{
    protected static $_tableName = 'blogs';
    protected static $_primaryKey = array('id');

    protected static $_columns = array(
        'name',
        'description',
        'created_at',
    );

    protected static $_relations = array(
        'articles' => array(
            'type'  => 'has_many',
            'model' => 'Article',
        ),
    );
}

class Article extends SimpleAR\Orm\Model
{
    protected static $_tableName  = 'articles';
    protected static $_primaryKey = array('id');

    public static $_relations = array(
        'author' => array(
            'type'  => 'belongs_to',
            'model' => 'Author',
        ),
        'blog' => array(
            'type'  => 'belongs_to',
            'model' => 'Blog',
        ),
    );

    protected static $_columns = array(
        'blogId' => 'blog_id',
        'title',
        'authorId' => 'author_id',
        'created_at',
    );

    protected static $_orderBy = array(
        'title' => 'ASC',
    );
}

class Author extends SimpleAR\Orm\Model
{
    protected static $_tableName = 'authors';

    protected static $_columns = array(
        'firstName' => 'first_name',
        'lastName'  => 'last_name',
        'age',
    );
}

Blog::wakeup();
Article::wakeup();
Author::wakeup();
