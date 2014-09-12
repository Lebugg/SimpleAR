---
title: "SimpleAR - Guides: Configuration"
layout: article
---

# Configuration

Configuration of SimpleAR is made through the Config instance you pass to the
SimpleAR constructor.

{% highlight php startinline %}
$cfg = new SimpleAR\Config();
$cfg->myOption = 'myValue';

$app = new SimpleAR($cfg);
{% endhighlight %}

If you want to modify configuration after SimpleAR object has been instanciated,
you can use `SimpleAR::configure()` method:

{% highlight php startinline %}
$app = new SimpleAR($cfg);

// ...

$cfg->myOption = 'myOtherValue';
$app->configure($cfg);
{% endhighlight %}

## Available options

### Database

* **charset**: The charset to use to connect to database.
{% highlight php startinline %}
$cfg->charset = 'utf8';
{% endhighlight %}
* **buildForeignKey**: A function used to construct foreign keys. It takes the
model name as parameter and returns the foreign key.
{% highlight php startinline %}
$cfg->buildForeignKey = function($modelName) {
    return strtolower($modelName) . '_id';
};
{% endhighlight %}
* **classToTable**: A function used to guess table name for a model. It takes
the model class name as parameter and returns the table name.
{% highlight php startinline %}
$cfg->classToTable = 'strotolower';
{% endhighlight %}
* **databaseDateTimeFormat**: The format to use when writing date in database.
{% highlight php startinline %}
$cfg->databaseDateTimeFormat = 'Y-m-d H:i';
{% endhighlight %}
* **doForeignKeyWork**: A boolean to tell SimpleAR whether to perform ON DELETE
CASCADE queries for database using storage engine that does not handle foreign
keys (MyISAM for example).
* **dsn**: The database dsn.
{% highlight php startinline %}
$cfg->dsn = array(
    'driver'   => 'mysql',
    'host'     => 'localhost',
    'name'     => 'dbname'
    'user'     => 'dbuser',
    'password' => 'dbpassword',
);
{% endhighlight %}
* **foreignKeySuffix**: The suffix to use when constructing foreign keys. It can
be used by `buildForeignKey` function.
{% highlight php startinline %}
// Want to use camelcase attribute instead of underscores?
$cfg->foreignKeySuffix = 'Id';
{% endhighlight %}

### Localization

* **dateFormat**: The format to use when using SimpleAR\DateTime as strings.
{% highlight php startinline %}
$cfg->dateFormat = 'd/m/Y'; // French format.
{% endhighlight %}

### Model

* **convertDateToObject**: A boolean to tell SimpleAR whether to automatically
converting date fields into SimpleAR\DateTime instances.
* **modelClassSuffix**: If you must use a suffix for your model classes (For
example, in CodeIgniter, model classes must be suffixed by "\_model"), specify
it here. Otherwise, it would mess with foreign keys construction.
* **modelDirectory**: A path or an array of pathes to the directory in which
models are located.
{% highlight php startinline %}
$cfg->modelDirectory = array(
    'this/directory/',   // Will be checked by autoloader first.
    'and/this/one/too/', // Then, this path will.
);
{% endhighlight %}
* **primaryKey**: The default primary key name.

### General

* **debug**: Are we in debug mode? If true, executed queries will be stored in
Connection object.
* **queryOptionRelationSeparator**: The character to use to separate relation
names when building queries.
{% highlight php startinline %}
// You prefer a dot over a slash?
$cfg->queryOptionRelationSeparator = '.';
{% endhighlight %}
* **aliases**: An array of classes to alias.
http://php.net/manual/function.class-alias.php
{% highlight php startinline %}
// Default values for this option:
$cfg->aliases = array(
    'SimpleAR\Orm\Model'   => 'SimpleAR\Model',
    'SimpleAR\Facades\DB'  => 'DB',
    'SimpleAR\Facades\Cfg' => 'Cfg',
);
{% endhighlight %}
