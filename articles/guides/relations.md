---
title: "SimpleAR - Guides: Relations"
layout: article
---

# Relations

SimpleAR allows you define relations between your models.
Relations are defined statically (for now) in the `$_relations` class attribute in
your model class.

## Relation declaration format

`$_relations` array follows this syntax:

{% highlight php startinline %}
array(
    '<relation name>' => array(
        'type'  => '<relation type>',
        'model' => '<linked model class>',
        ...
    ),
    ...
);
{% endhighlight %}

`$_relations` keys are the name of the relations you declare and `$_relations`
values are information about these relations.
`type` and `model` in are always mandatory.

Let's review the different kinds of relations between models!

## BelongsTo

A blog article belongs to a blog.

{% highlight php startinline %}
class Blog extends SimpleAR\Model {}

// ...

class Article extends SimpleAR\Model
{
    protected static $_relations = array(
        'blog' => array(
            'type'  => 'belongs_to',
            'model' => 'Blog',
        ),
    );
}
{% endhighlight %}

Since Article belongs to Blog, it means that Article model has an attribute that
references Blog model. SimpleAR will assume that this attribute is named `blog_id`.

Of course, you can override the default key by setting the `key_from` entry in
relation information array:
{% highlight php startinline %}
    protected static $_relations = array(
        'blog' => array(
            // ...
            'key_from' => 'blog_fk' // Whatever your attribute is.
        ),
    );
{% endhighlight %}

<p class="alert alert-warning">
    You can change SimpleAR assumptions for foreign keys by overwriting these
    configuration options: <code>buildForeignKey</code> or
    <code>foreignKeySuffix</code>.
</p>

Once you've declared your relation, you can retrieve linked model as if it was
an attribute:

{% highlight php startinline %}
$blog = $article->blog;
{% endhighlight %}

## HasOne

A user has one CV (resume):

{% highlight php startinline %}
class CV extends SimpleAR\Model {}

// ...

class User extends SimpleAR\Model
{
    protected static $_relations = array(
        'cv' => array(
            'type'  => 'has_one',
            'model' => 'CV',
        ),
    );
}

// ...

$cv = $user->cv;
{% endhighlight %}

SimpleAR will assume that CV model contains a `user_id` attribute. You can
override this default setting `key_to` entry:
{% highlight php startinline %}
    protected static $_relations = array(
        'cv' => array(
            // ...
            'key_to' => 'owner_id' // Whatever your attribute is.
        ),
    );
{% endhighlight %}

## HasMany

A brewery brews many beers!
{% highlight php startinline %}
class Beer extends SimpleAR\Model {}

// ...

class Brewery extends SimpleAR\Model
{
    protected static $_relations = array(
        'beers' => array(
            'type'  => 'has_many',
            'model' => 'Beer',
            // You can set "key_to" here too.
        ),
    );
}

// ...

$beers = $brewery->beers; // $beers is an array of Beer instances.
{% endhighlight %}

## ManyMany

A blog article has many readers; a reader reads many articles.

{% highlight php startinline %}
class Article extends SimpleAR\Model
{
    protected static $_relations = array(
        'readers' => array(
            'type'  => 'many_many',
            'model' => 'User',
        ),
    );
}

// ...

$readers = $article->readers; // $readers is an array of User instances.
{% endhighlight %}

For ManyMany relations, SimpleAR will make several assumptions. In above
example, it would be as follows:

* Middle table name: "articles_users". Set `join_table` to override it;
* Middle table left foreign key: "article_id". Set `join_from` to override it;
* Middle table right foreign key: "user_id". Set `join_to` to override it.

You can also, set a join model instead of a middle table name with `join_model`
entry.

For example:

{% highlight php startinline %}
class Reading extends SimpleAR\Model
{
    protected static $_columns = array(
        'article_id',
        'user_id',
        'created_at',
    );
}

// ...

class Article extends SimpleAR\Model
{
    protected static $_relations = array(
        'readers' => array(
            'type'  => 'many_many',
            'model' => 'User',
            'join_model' => 'Reading',
        ),
    );
}
{% endhighlight %}
