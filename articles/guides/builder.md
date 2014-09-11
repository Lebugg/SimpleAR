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
retrieve.
