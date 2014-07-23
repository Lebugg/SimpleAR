SimpleAR
========

I know, you may say: “why another PHP ORM? There are so many of them!”. And I
would agree, but I've been looking for an ORM that would fit my needs and I
haven't found it. So I've written one.

Features
--------

### Classic features of any ORM

* CRUD manipulation:
    * **Create**:
   
        ```php
        $object = new Class(<attributes>);
        $object->save();
        ```
        
        *or*
        
        ```php
        $object = Class::create(<attributes>);
        ```
        
    * **Read**:
              
        ```php
        Class::find(<options>);
        ```
            
        (and its derivated: `Class::findByPK()`, `Class::all()`,
        `Class::first()`, `Class::last()`, `Class::one()`)

    * **Update**:

        ```php
        $object->myAttr = $myValue;
        $obeect->save();
        ```
            
        *or*
                
        ```php
        $object->set(<attributes>)
        $object->save();
        ```
            
        for several attributes in a row.

    * **Delete**:
    
        ```php
        $object->delete();
        ```
            
        *or*
        
        ```php
        Class::remove(<conditions>);
        ```

* Model relationships:
    * belongs\_to, has\_one, has\_many, many\_many;
    * conditions on relations;
    * order by in \*\_many relations.

### Great features

Write *no* SQL anymore. There is no need, *not once*, to write SQL, even for
complex searches.

* Conditions can be made through a model arborescence:

    ```php
    'conditions' => array(
        'company/contacts/last_name' => 'Doe',
        ...
    ),
    ```

* Do not use methods to construct your query conditions: one array suffises:

    ```php
    'conditions' => array(
        array(
            'company/contacts/first_name' => 'John',
            'company/contacts/last_name'  => 'Doe',
        ),
        'OR',
        array(
            'company/contacts/first_name' => 'Paul',
            'company/contacts/last_name'  => 'Smith',
        ),
    ),
    ```

    But you can do better. Check this out:

    ```php
    'conditions' => array(
        'company/contacts/first_name,last_name' => array(array('John', 'Doe'), array('Paul', 'Smith')),
    ),
    ```

    Great, isn't it?

* Order your result by a related model's attribute:
    
    ```php
    'order_by' => 'company/director/last_name',
    ```

* Order your result by a `COUNT`:
    
    ```php
    'order_by' => array('#contacts' => 'DESC'),
    ```

One of the strenghts of this ORM is that it can be used on top of any database.
I mean that if, for example, you have to use a really ugly database schema, this
ORM is perfect. With combination of a few features of it, you can abstract in a
very clean way your schema.

* Attribute to column translation: In the model column definition array you can
change columns' names for a more fitting ones.

    ```php
    protected static $_columns = array(
        'first_name', // Column is named "first_name" and it is a good one.
        'last_name',
        'age' => 'years_since_birth', // Column "years_since_birth" of
                                      // database table will be named "age" in our model.
        ...
    );
    ```

* Callbacks at every important step of interaction with database (Names are
meaningful):
    * `_onBeforeLoad()`;
    * `_onAfterLoad()`;
    * `_onBeforeInsert()`;
    * `_onAfterInsert()`;
    * `_onBeforeUpdate()`;
    * `_onAfterUpdate()`;
    * `_onBeforeDelete()`;
    * `_onAfterDelete()`.

* Handling functions for conditions on virtual attributes.

### Other cool features

* Optional getters and setters;
* Optional count getters;
* Model attributes filters: an easy way to manage attributes you want to
retrieve from DB;
* Scopes;

Requirements
------------
SimpleAR requires the following:

* PHP 5.3 or higher.

Installation
------------

1. Just copy *lib/* folder content anywhere in your code (a *libraries/SimpleAR/*
folder sounds good, for example).

2. Include SimpleAR.php with some basic configuration:

    ```php
    include 'libraries/SimpleAR/SimpleAR.php';
    
    $cfg = new SimpleAR\Config();
    $cfg->dsn = array(
        'driver'   => DB_DRIVER,
        'host'     => DB_HOST,
        'name'     => DB_NAME,
        'user'     => DB_USER,
        'password' => DB_PASS,
    );
    
    // Note trailing slash.
    $cfg->modelDirectory  = 'path/to/any/directory_you_want/'; // You can pass
                                                               // an array too.
    
    $app = new SimpleAR($config);
    ```

3. No, there is no third step; It's done!

How to use SimpleAR?
--------------------

To use SimpleAR functionalities, simply make your model classes extends
SimpleAR's Model class:

 ```php
 <?php

 class MyModel extends SimpleAR\Model
 {
 }
 ```

You are done.

Configuration
-------------

There are several available configuration options you can modify the same way as
shown in Installation - step two.

All of them are describe in documentation.

Documentation
-------------

### Create a Model class ###

#### Database settings ####

Most simple case:

```php
class User extends SimpleAR\Model
{
}
```

##### Set table and primary key #####

Conventions:

* User database table will be named according to `Config::classToTable` item. By default, it applies
the PHP `strtolower()` function. So, here our table is supposed to be named “user”.

* Table primary key will be named according to `Config::primaryKey` item. By default, it is “id”.


You can still explicitly set your values:
```php
class User extends SimpleAR\Model
{
    // Database table name.
    protected static $_tableName = 'users';

    // Table primary key.
    protected static $_primaryKey = array('id');
}
```

##### Set columns #####

If you don't specify them, columns will be retrieved thanks to a `SHOW COLUMNS`
query. I recommend to specify them, it will save one database query for each
loaded model:

```php
class User extends SimpleAR\Model
{
    ...

    protected $_columns = array(
        'last_name',
        'first_name',
        'age',
    );
}
```

You can set a different name for your attribute if column name does not suit you:

```php
class User extends SimpleAR\Model
{
    ...

    protected $_columns = array(
        'last_name',
        'first_name',
        // “age” will be used for “years_since_birth” column.
        'age' => 'years_since_birth',
    );
}
```
