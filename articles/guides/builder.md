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

## Simple conditions

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

### Attribute-value conditions

{% highlight php startinline %}
// Collection or available where-like methods:
MyModel::whereNot('attr', 'val');
MyModel::andWhere('attr', 'val');
MyModel::orWhere('attr', 'val');
MyModel::andWhereNot('attr', 'val');
MyModel::orWhereNot('attr', 'val');
{% endhighlight %}

### Attribute-attribute conditions

{% highlight php startinline %}
Article::whereAttr('commentNumber', '>', 'viewNumber');
{% endhighlight %}

### Nested conditions

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

<p class="alert alert-info">
<code>whereNested()</code> is the method that handles nested conditions; but
<code>where()</code> will get it as well (by delegating to
<code>whereNested()</code>).

<br>
<br>

Use whatever name you prefer!
</p>

