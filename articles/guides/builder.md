---
title: "SimpleAR - Guides: Query builder"
layout: article
---

# Query builder

In this guide, we will go through functionalities provided by SimpleAR's query
builder.

The major part of this guide will explain how to use query builder to construct
`SELECT` queries, since it is the most used query type.

We'll see how to construct other statements at the end.

## Accessing builder instance

You can get a new query builder instance for a model using the `query()` method.

{% highlight php startinline %}
$qb = Article::query();

$qb->one(); // Or first(), last(), all().
{% endhighlight %}

## Conditions

### Simple conditions

Query builder provides a good collection of method to add conditions on data to
retrieve:

{% highlight php startinline %}
Article::query()->where('author_id', 12);
{% endhighlight %}

Fortunately, you don't have to use query() method every time you want to build a
query:
{% highlight php startinline %}
Article::where('author_id', 12);
Article::where('author_id', '=', 12); // Same as above
{% endhighlight %}

#### Attribute-value conditions

##### Basic where methods

{% highlight php startinline %}
MyModel::whereNot('attr', 'val'); // NOT `attr` = 'val'
MyModel::andWhere('attr', 'val'); // AND `attr` = 'val'
MyModel::orWhere('attr', 'val');  // OR  `attr` = 'val'
MyModel::andWhereNot('attr', 'val'); // AND NOT `attr` = 'val'
MyModel::orWhereNot('attr', 'val');  // OR  NOT `attr` = 'val'
{% endhighlight %}

##### Where null
{% highlight php startinline %}
$query->whereNull('author_id');
// <=>
$query->where('author_id', null);
{% endhighlight %}

{% highlight sql%}
WHERE `author_id` IS NULL
{% endhighlight %}

- - -

{% highlight php startinline %}
$query->whereNotNull('author_id');
// <=>
$query->whereNot('author_id', null)
// <=>
$query->where('author_id', '!=', null);
{% endhighlight %}

{% highlight sql%}
WHERE `author_id` IS NOT NULL
{% endhighlight %}

##### Conditions over attribute tuple

{% highlight php startinline %}
// (You can use where() method too.)
$query->whereTuple(['author_id', 'blog_id'], [[1,2], [1,3], [2,3]]);
{% endhighlight %}

{% highlight sql%}
WHERE (`author_id`, `blog_id`) IN ((1,2), (1,3), (2,3))
{% endhighlight %}

**Alternative syntax:**

{% highlight php startinline %}
$query->where('author_id,blog_id', [[1,2], [1,3], [2,3]])
{% endhighlight %}

Will produce the same as shown above.


#### Attribute-attribute conditions

{% highlight php startinline %}
Article::whereAttr('comment_count', '>', 'view_count');
{% endhighlight %}

{% highlight sql%}
WHERE `comment_count` > `view_count`
{% endhighlight %}

#### Nested conditions

Use the `whereNested` method:

{% highlight php startinline %}
Article::whereNested(function ($q) {
    $q->where('author_id', [12, 13, 14])
      ->andWhere('blog_id', 1);
})
->orWhere(function ($q) {
    $q->where('title', 'Alice in Wonderland')
      ->where('blog_id', 3);
});
{% endhighlight %}

{% highlight sql%}
WHERE (
    `author_id` IN [12, 13, 14] AND `blog_id` = 1
) OR (
    `title` = 'Alice in Wonderland' AND `blog_id` = 3
)
{% endhighlight %}

<p class="alert alert-info">
<code>whereNested()</code> is the method that handles nested conditions; but
<code>where()</code> will get it as well (by delegating to
<code>whereNested()</code>).

<br>
<br>

Use whatever method you prefer!
</p>

### Conditions over relations

This is really easy and intuitive to perform conditions over linked models in
SimpleAR. This section demonstrates it.


Here is the schema I will use in my examples:

![Blog schema]({{ site.baseurl }}/assets/images/guides_builder.png "The blog schema")

Here is the corresponding classes and their relations:

{% highlight php startinline %}
class Blog extends SimpleAR\Model
{
    protected static $_relations = array(
        'articles' => array(
            'type'  => 'has_many',
            'model' => 'Article',
        )
    );
}

...

class Article extends SimpleAR\Model
{
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
}

...

class Author extends SimpleAR\Model {}

...

class User extends SimpleAR\Model {}
{% endhighlight %}

Now we have everything we need, let's explore what query builder can offer!

#### Condition on linked model's attribute

> *Every attribute condition method (seen above) handles attribute of linked
> models.*

This means that the following is valid:
{% highlight php startinline %}
// Blogs where John Doe has written articles.
Blog::where('articles/author/first_name,last_name', ['John', 'Doe']);

// Blogs where no article has been viewed more than 100 times.
Blog::whereNot('articles/view_count', '>', 100);
{% endhighlight %}

<p class="alert alert-success">
This syntax is valid with every method seen in <a href="#simple-conditions">Simple conditions</a> section.
</p>

<p class="alert alert-info">
The character "/" is the default relation separator character. You can change
it with <code>queryOptionRelationSeparator</code> configuration option.
</p>

#### Condition on linked models

{% highlight php startinline %}
Blog::whereHas('articles');
{% endhighlight %}

{% highlight php startinline %}
Article::whereHas('readers', '>', 1000);
{% endhighlight %}

{% highlight php startinline %}
Blog::whereHas('articles', function ($q) {
    $q->where('author_id', 12);
});
{% endhighlight %}

{% highlight php startinline %}
Blog::whereHas('articles/readers');

// is equivalent to:

Blog::whereHas('articles', function($q) {
    $q->whereHas('readers');
});
{% endhighlight %}

### Aggregates

{% highlight php startinline %}
// Blogs of which articles readers' average age is greater than 30.
Blog::where(DB::avg('articles/readers/age'), '>', 30);
{% endhighlight %}
Smooth!

## Select statements

{% highlight php startinline %}
Article::where('author_id', 12)
    ->orderBy('title')
    ->limit(10)
    ->offset(20)
    ->all();
{% endhighlight %}

## Insert statements

{% highlight php startinline %}
User::insert()->fields(['first_name', 'last_name'])->values(
    ['John', 'Doe'],
    ['Karl', 'Marx']
)->run();
{% endhighlight %}

## Update statements

{% highlight php startinline %}
User::update()->set('first_name', 'Foo')->where('last_name', 'Bar')->run();
{% endhighlight %}

## Delete statements

{% highlight php startinline %}
Author::delete()->whereHasNot('articles')->run();
{% endhighlight %}

