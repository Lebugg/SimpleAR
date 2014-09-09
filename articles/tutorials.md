---
title: "SimpleAR: Tutorials"
layout: article
---

## Getting started

### Installation

The project is hosted on GitHub. You can found it
[here](https://github.com/Lebugg/SimpleAR.git). To install it, just clone the
repo:

{% highlight bash %}
git clone https://github.com/Lebugg/SimpleAR.git
{% endhighlight %}

Then, in your code, include the main file and create a SimpleAR instance:

{% highlight php startinline %}
include 'SimpleAR/SimpleAR.php';

$cfg = new SimpleAR\Config();
$app = new SimpleAR($cfg);
{% endhighlight %}

And you're done!

### Basic configuration.

You will need to set a database access, and you may also want to define your
model folder(s) in order for the autoloader to load them. All general
configuration like this is made through the `Config` instance passed to the
`SimpleAR` object.

{% highlight php startinline %}
// All these values are required.
// You can optionaly set a "charset" entry too.
$cfg->dsn = array(
    'driver'   => 'mysql',
    'host'     => 'localhost',
    'name'     => 'my_db',
    'user'     => 'username',
    'password' => 'I <3 SimpleAR'
);

// You can also set an array of directories to be checked by autoloader.
$cfg->modelDirectory = 'path/to/models/';

$app = new SimpleAR($cfg);
{% endhighlight %}

There are several configuration options available. You can refer to
configuration section to find a list of all options.

## Basic usage

### Creating your models

Now that SimpleAR is installed and configured, we are able to create our models!

Let's create an Article model that represents... an article (on a blog, for
example).

{% highlight php startinline %}
// Every model *must* extend SimpleAR\Model class.
class Article extends SimpleAR\Model
{
}
{% endhighlight %}

And it's done. You are able to manipulate your articles:

{% highlight php startinline %}
$articles = Article::all();

$article = Article::find(12);
$article->title = 'Good Stuff';
$article->save();
$article->delete();
{% endhighlight %}

Since we did not give any information about our model (except the class name!),
SimpleAR will make assumptions about the database structure:

 * Table name: If not set, table name is guessed from the model class
name. The function to construct the table name is defined by `classToTable`
configuration option. By default, it uses
[`strtolower`](http://php.net/manual/function.strtolower.php).
 * Model attributes: If not set, attributes will be fetched from database.
 * Primary key: If not set, primary key will be `id`. In fact, it will be
`array('id')` since internally, SimpleAR uses arrays for primary keys.

* * *

You can of course explicitly give information about your models:

{% highlight php startinline %}
class Article extends SimpleAR\Model
{
    protected static $_tableName = 'articles';
    protected static $_primaryKey = 'id';
    protected static $_columns = array(
        'title',
        'created_at',
        'author_id',
    );
}
{% endhighlight %}

### Manipulating model instances

#### Insertion

There are several ways to insert new records in database:

{% highlight php startinline %}
// You can instanciate an object, fill attributes, and `save()` it:
$article = new Article();
$article->title = 'My Title';
$article->authorId = 12;
$article->save(); // Creates the new row.

// Or do it all at once:
$article = Article::create(['title' => 'My Title', 'authorId' => 12]);
{% endhighlight %}

<p class="alert alert-warning">
    Note: You can know if a model instance matches a row in DB by using
    `isConcrete()` method.
</p>

#### Read

There are many possible way to retrieve data from DB. All of them will be
discussed in another part; but we will see most used ways here:

You can use find methods:

{% highlight php startinline %}
$a = Article::findByPK(12); // Find Article with "id" == 12.
$a = Article::find(12); // Shortcut for above.
$articles = Article::findByPK([1, 2, 3]); // Fetch articles which
                                          // IDs are 1, 2 and 3.
{% endhighlight %}

Or you can use the query builder which allows you a very flexible syntax.
{% highlight php startinline %}
$a = Article::where('id', 12)->one();
$a = Article::where('title', 'like', 'My Title')->last();
$a = Article::where('author_id', [12, 15])->all();
{% endhighlight %}

I won't describe all builder methods here, there are dozens of them. But if you
look at the method chaining, you will notice that the last method called
actually fetch result from database.

Here are the currently existing methods of this kind:

* `one()`: Fetch one record matching the query;
* `first()`: Fetch the first found record;
* `last()`: Fetch the last found record;
* `all()`: Fetch all found records;
* `search($page, $number)`: Fetch $number records with an offset of "($page - 1) *
$offset".

#### Update

Update a model instance:

{% highlight php startinline %}
$article = Article::find(12);
$article->title = 'Other Title';
$article->save();
{% endhighlight %}

You can update several attributes at once with `set()` method:
{% highlight php startinline %}
$article->set(['title' => 'A Title', 'author_id' => 14]);
$article->save();
{% endhighlight %}

SimpleAR intends to use method chaining. Thus, you are able to do the following:
{% highlight php startinline %}
Article::find(12)
    ->set(['title' => 'A Title'])
    ->save();
{% endhighlight %}

#### Delete
