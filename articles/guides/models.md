---
title: "SimpleAR - Guides: Taking control of your models"
layout: article
---

# Taking control of your models

## Attribute manipulation

### Column aliasing

Here is how to define your model's attributes:

{% highlight php startinline %}
class Author extends SimpleAR\Model
{
    protected static $_columns = array(
        'first_name',
        'last_name',
        'years_since_birth',
    );
}
{% endhighlight %}

But we can see that "years_since_birth" is not really appropriate. "age" would
be a better wording:

{% highlight php startinline %}
    protected static $_columns = array(
        'first_name',
        'last_name',
        // <attribute name> => <column name>
        'age' => 'years_since_birth',
    );
{% endhighlight %}

<p class="alert alert-warning">
    <strong>Note:</strong> A better solution might be to change the field name
    in database. But sometimes, you are not able to change it.
</p>

### Getters and setters

SimpleAR\Model class provides some methods to get and set attributes.

### Get all attributes

You can retrieve every object's attribute with their values with the
`attributes()` method:

{% highlight php startinline %}
$author = Author::create(['first_name' => 'John', 'age' => 43]);
print_r($author->attributes());

// Result:
// array(
//     'id'         => 1,
//     'first_name' => 'John',
//     'last_name'  => null,
//     'age'        => 43,
// );
{% endhighlight %}

### Get and set several attributes at once

You can use `get()` and `set()` methods to respectively get and set several
attributes at once.

Very handy!

### Get model's columns

Use `columns()` static method to retrieve an associative array between attribute
names and column names.

Example with `Author` model:
{% highlight php startinline %}
print_r(Author::columns());

// Result:
// array(
//     'id'         => 'id',
//     'first_name' => 'first_name',
//     'last_name'  => 'last_name',
//     'age'        => 'years_since_birth',
// );
{% endhighlight %}

### Custom getters and setters

You can define custom getters and setters method for specific attribute that
will be called when trying to access to the attribute.

{% highlight php startinline %}
public function set_password($raw)
{
    $this->_attr('password', md5($raw));
}

// ...

// Then, you set `password` attribute this way:
$author->password = 'yeah!';
{% endhighlight %}

<p class="alert alert-warning">
    <strong>Note:</strong> Use `_attr()` method inside getters and setters to
    prevent infinite loop.
</p>

You can write a getter or setter for attribute that does not “exist”:

{% highlight php startinline %}
public function get_name()
{
    return $this->first_name . ' ' . $this->last_name;
}
{% endhighlight %}

And then use it in a view as in following example:
{% highlight html startinline %}
<p>
    Hello, <?php $user->name; ?>!
</p>
{% endhighlight %}

## Model configuration

### Global ordering

You can define a model-wide sort ordering when retrieving several instances from
database. Take a `Country` model that modelizes a country. In your application,
you always want to retrieve countries sorted in alphabetical order.

{% highlight php startinline %}
class Country extends SimpleAR\Model
{
    protected static $_orderBy = array(
        'name', // Equivalent to: 'name' => 'ASC'
    );
}

// Result will automatically be sorted.
Country::all();
{% endhighlight %}

<p class="alert alert-info">
Use  `$_orderBy` class attribute to define a global ordering.
</p>

### Global conditions

You can define model-wide conditions. It means that these conditions will be
applied to every query made on this model.

{% highlight php startinline %}
// Let's take Article model for an example. On your blog, you never want offline
// articles.
Article::setGlobalConditions(['status' => 'online']);
{% endhighlight %}

This way, the condition "status is online" will be applied to each query made
over Article model.

<p class="alert alert-info">
You can use `MyModel::getGlobalConditions()` to get current global conditions of
`MyModel`.
</p>

### Callbacks

SimpleAR provides a collection of callback methods that you can overwrite to
take control of your model instance.

* `_onBeforeLoad()`;
* `_onAfterLoad()`;
* `_onBeforeInsert()`;
* `_onAfterInsert()`;
* `_onBeforeUpdate()`;
* `_onAfterUpdate()`;
* `_onBeforeDelete()`;
* `_onAfterDelete()`.
