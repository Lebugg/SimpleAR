---
title: "SimpleAR: Reference - Models and relations"
layout: article
---

# Models and relations

## The Model

SimpleAR uses, for now, a [Domain
Model](http://martinfowler.com/eaaCatalog/domainModel.html) to manage objects.
The `Model` class is the heart of this approach.

### Table

User uses `Model` to give information about its table (columns, table name...)
but these are parsed at class loading and then stored in a `Table` object.

Here is the process:

1. Autoloader loads class file and triggers `Model::wakeup()` class method;
2. Model parses data provided by user (`$_columns`, `$_tableName`,
`$_primaryKey`...) or, if not given, tries to guess stuff out of its class name.
If columns are not given, SimpleAR will perform a query to the DB.
3. Create a `Table` object with these informations and store it.

`Table` objects are stored in a `Model::$_tables`, a static array that associate
model class name to `Table` objects.

Then, every needed information is accessed through the `Table`.
<p class="alert alert-info">
<code>Table</code> object can be accessed with <code>Model::table()</code>
method.
</p>

{% highlight php startinline %}
Article::table()->getPrimaryKey();
// ['id']
{% endhighlight %}

## Relations

Relations 

## Orm query builder
