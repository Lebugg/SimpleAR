<?php

use \SimpleAR\Orm\Builder as QueryBuilder;

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
        'readers' => array(
            'type' => 'many_many',
            'model' => 'User'
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

    public static function scope_status(QueryBuilder $qb, $status)
    {
        if ($status === 2)
        {
            $qb->where('isOnline', true);
            $qb->where('isValidated', true);
        }

        return $qb;
    }
}

class Author extends SimpleAR\Orm\Model
{
    protected static $_tableName = 'authors';

    protected static $_columns = array(
        'firstName' => 'first_name',
        'lastName'  => 'last_name',
        'age',
    );

    public static function scope_women(QueryBuilder $qb)
    {
        return $qb->where('sex', 1);
    }
}

class User extends SimpleAR\Orm\Model
{
    protected static $_tableName = 'USERS';

    protected static $_columns = array(
        'firstName',
        'lastName',
    );
}

Blog::wakeup();
Article::wakeup();
Author::wakeup();
User::wakeup();
