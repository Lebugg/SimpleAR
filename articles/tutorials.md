---
title: "SimpleAR: Tutorials"
layout: article
---


## Getting started

### Installation

The project is hosted on GitHub. You can found it
[here](https://github.com/Lebugg/SimpleAR.git). To install it, just clone the
repo:

```
git clone https://github.com/Lebugg/SimpleAR.git
```

Then, in your code, include the main file and create a SimpleAR instance:

```php
include 'SimpleAR/SimpleAR.php';

$cfg = new SimpleAR\Config();
$app = new SimpleAR($cfg);
```

And you're done!

### Basic configuration.

You will need to set a database access, and you may also want to define your
model folder(s) in order for the autoloader to load them. All general
configuration like this is made through the `Config` instance passed to the
`SimpleAR` object.

```php
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
```

There are several configuration options available. You can refer to
configuration section to find a list of all options.

## Creating your models

Now that SimpleAR is installed and configured, we are able to create our models!

Let's create an Article model that represents... an article (on a blog, for
example).

```php
// Every model *must* extend SimpleAR\Model class.
class Article extends SimpleAR\Model
{
}
```

And it's done. You are able to manipulate your articles:

```php
$articles = Article::all();

$article = Article::find(12);
$article->title = 'Good Stuff';
$article->save();
$article->delete();
```

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

```php
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
```
