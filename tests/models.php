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
            self::$table = new \SimpleAR\Orm\Table('blogs', 'id', array('name', 'description', 'created_at'));
            self::$table->modelBaseName = 'Blog';
        }

        return self::$table;
    }
}

class Article extends SimpleAR\Orm\Model
{
    public static $table;
    public static function table()
    {
        if (self::$table === null)
        {
            self::$table = new \SimpleAR\Orm\Table('articles', 'id', array(
                'blogId' => 'blog_id',
                'title',
                'author',
            ));
            self::$table->modelBaseName = 'Article';
            self::$table->order = array('title' => 'ASC');
        }

        return self::$table;
    }
}
