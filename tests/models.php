<?php

class Blog extends SimpleAR\Orm\Model
{
    public static $_relations = array(
        'articles' => array(
            'type'  => 'has_many',
            'model' => 'Article',
        ),
    );

    public static $table;
    public static function table()
    {
        if (self::$table === null)
        {
            self::$table = new \SimpleAR\Orm\Table('blogs', 'id', array(
                'name',
                'description',
                'created_at'
            ));
            self::$table->modelBaseName = 'Blog';
        }

        return self::$table;
    }
}

class Article extends SimpleAR\Orm\Model
{
    protected static $_tableName  = 'articles';
    protected static $_primaryKey = 'id';

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
    public static $table;
    public static function table()
    {
        if (self::$table === null)
        {
            self::$table = new \SimpleAR\Orm\Table('authors', 'id', array(
                'firstName' => 'first_name',
                'lastName'  => 'last_name',
                'age',
            ));
            self::$table->modelBaseName = 'Author';
        }

        return self::$table;
    }
}

Article::wakeup();
