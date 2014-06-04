[![Build Status](https://travis-ci.org/icaine/flunorette.svg?branch=master)](https://travis-ci.org/icaine/flunorette)

What is Flunorette?
===================
**Flunorette**

- is a **database layer** simplifying work with tables, relations and CRUD operations.

- is currently in **beta** but still runs without any known problems on few bigger projects (e.g. daty.cz, uzmipopust.rs, wrate.it).

- is based on **[Nette Database](http://doc.nette.org/en/2.0/database) ([Table](http://doc.nette.org/en/2.0/database-table))** - NDB(T) and uses modified **[FluentPDO](http://fluentpdo.com)** as SQL builder.

- is derived[1] directly from NDB(T)2.0 thus it's like on 95% compatible with it[2].

- **uses the syntax from NDBT2.0** but takes faster code from NDBT2.1.

*The main reason i decided to create Flunorette was that NDBT is superior for simple websites like blogs but with complex web apps you sooner or later hit the wall. E.g. as soon as you need create queries with conditions inside JOIN clauses or any other advance queries there is no way how to achieve it with NDBT*.



[1]: I literally gutted NDB(T) and took most of the code.
[2]: Flunorette has a replacer that can help you with replacing NDB(T).

---

What is the difference between Flunorette and NDB(T)?
-----------------------------------------------------

###General

- Currently there is only `MySql` driver.

- You can make your own `Selection` and `ActiveRow` (there is separate `SelectionFactory`).

- You can use PDO's named params (`:id`) but there are still some limitations (same as in PDO - you can use anonymous `?` xor named params).

- You can use custom fetch modes (similar to hydrators in Doctrine) when you don't want to waste resources on creating instances of `Row`/`ActiveRows`. `Selection` and `Statement` have method `hydrate()`.

- There is a setting how to delimite in sql queries (adding delimiters user->\`user\`, replacing delimiters "user"->\`user\` or both).

- Delimiting is a bit smarter - no more delimited words inside sql strings.

- Added `TransactionCounter` for possibility to nest transactions without errors.

- To `DebugPanel` has been added table with filterable query overview.

- You can simply replace NDB2.0 with `Flunorette\NetteDatabaseReplacer::replace();` and your current code will still work.

###ActiveRow

- You can create empty `ActiveRows`, then fill them with data and save them.

- Flunorette does not deprecate `ActiveRow's` methods `insert()`, `update()` as NDBT since v2.1 does.

- `ActiveRow` now has method `save()` that automatically decides whether to insert or update.

###Selection

- Flunorette does not use still buggy (smart) column prediction in select queries. The feature caused me a lot of troubles and i always had to bypass it with DevNullStorage for Cache.

- Flunorette uses modified [FluentPDO](http://fluentpdo.com) as a SQL builder and thus you can quite easily change inner sql of the `Selection` or use the SQL builder totally independently.

- `Selection` now has methods `whereAnd()` and `whereOr()` that does not wrap the condition inside parentheses like `where()` does.

- `Selection` now has methods `join()` and `leftJoin()` that can be used to change how to join related tables.

- There is a php notice when `Selection` selects columns with same names and that will be overwritten (causes troubles especially with `ID` columns)

###DiscoveredReflection

- `DiscoveredReflection` has been improved so in `ref()`/`belongsTo()` table name can be used, not only (part of) key. E.g. `$article->categories` does not work in NDBT when fk is `category_id`, you had to use `$article->category`.

- `DiscoveredReflection` now tells you when you use wrong direction (e.g. `$user->ref('article')` does not exist, but `$article->ref('user')` does).

- BC break: If using a cache, it must be invalidated when schema is changed.

###SqlBuilder

- `SqlBuilder` can be now used independently `Flunorette\[Select|Update|...]Query`.

- Auto/smart filling of join clauses. E.g. `->join('user:')`, `->join('user ON user.id = article.user_id')` or `$users->select('articles.name')`, etc.

- A possibility to tell which column to use when selecting column from related table. E.g. `->where('user#created_by.name = ?', $name)`.

- A possibility to overwrite or reset any clause.

- Full sql query expansion including parameter values (good for debugging)

- Ability to convert one Query to another. E.g. `SelectQuery` to `DeleteQuery`.

###Connection's settings

- Whether to use the `TransactionCounter`.

- Delimiting mode.

- BC compatibility with NDBT
    - Whether to wrap conditions in `->where()` inside parentheses

    - Whether to use `*` or `table.*` when no `->select()` is explicitly called.

###Example

What never can be achieved with NDBT:

    if ($search && $search->getUserIds()) {
        $ratingJoin = array('ratings ON branches_categories.id = ratings.branch_category_id AND ratings.created_by IN (?)', $search->getUserIds());
    } else {
        $ratingJoin = array('ratings ON branches_categories.id = ratings.branch_category_id');
    }

    $selection = $this->getBaseSelection()
        ->select('branches_categories.category_id AS category_id')
        ->select('criterion_ratings.criterion_id')
        ->select('criterion_ratings.value AS rating_value')
        ->select('ratings.created_on AS rated_on')
        ->select('ratings.created_by AS rated_by')
        ->leftJoin('branches_categories:')
        ->leftJoin($ratingJoin)
        ->leftJoin('criterion_ratings ON ratings.id = criterion_ratings.rating_id')
        ->group('branches.id')
    ;

More usage examples can be seen in [tests](https://github.com/icaine/Flunorette/tree/master/tests).

---

#ToDo
There are few things that i want to do before releasing Flunorette as `v1.0`.

**Namely:**

- Clean the code (decide whether to be dependent on Nette or not, move some classes to appropriate namespaces).
- Improve/clean tests.
- Create a documentation.

If you want to lend me a hand, just contact me on webdev.daso@gmail.com


Change log
----------

**1.6.2014** - Some classes moved to new namespaces (towards PSR-4). To migrate from 0.9 to 1.0 use:
```php
	//BC part

	//https://github.com/icaine/RenamedClassLoader
	$rcLoader = new iCaine\RenamedClassLoader([
		'Flunorette\\NetteDatabaseReplacer' => 'Flunorette\\Bridges\\Nette\\NetteDatabaseReplacer',
		'Flunorette\\Hydrator' => 'Flunorette\\Hydrators\\Hydrator',
		'Flunorette\\HydratorSelectionDefault' => 'Flunorette\\Hydrators\\HydratorSelection',
		'Flunorette\\HydratorArrayHash' => 'Flunorette\\Hydrators\\HydratorArrayHash',
		'Flunorette\\HydratorResult' => 'Flunorette\\Hydrators\\HydratorField',

		'Flunorette\\ActiveRow' => 'Flunorette\\Selections\\ActiveRow',
		'Flunorette\\Selection' => 'Flunorette\\Selections\\Selection',
		'Flunorette\\GroupedSelection' => 'Flunorette\\Selections\\GroupedSelection',
		'Flunorette\\ISelectionFactory' => 'Flunorette\\Selections\\ISelectionFactory',
		'Flunorette\\SelectionFactory' => 'Flunorette\\Selections\\SelectionFactory',

		'Flunorette\\DeleteQuery' => 'Flunorette\\Queries\\DeleteQuery',
		'Flunorette\\InsertQuery' => 'Flunorette\\Queries\\InsertQuery',
		'Flunorette\\JoinableQuery' => 'Flunorette\\Queries\\JoinableQuery',
		'Flunorette\\Query' => 'Flunorette\\Queries\\Query',
		'Flunorette\\QueryBuilder' => 'Flunorette\\Queries\\QueryBuilder',
		'Flunorette\\QueryContext' => 'Flunorette\\Queries\\QueryContext',
		'Flunorette\\SelectQuery' => 'Flunorette\\Queries\\SelectQuery',
		'Flunorette\\UpdateQuery' => 'Flunorette\\Queries\\UpdateQuery',

		'Flunorette\\IReflection' => 'Flunorette\\Reflections\\IReflection',
		'Flunorette\\ConventionalReflection' => 'Flunorette\\Reflections\\ConventionalReflection',
		'Flunorette\\DiscoveredReflection' => 'Flunorette\\Reflections\\DiscoveredReflection',
		'Flunorette\\ReflectionException' => 'Flunorette\\Reflections\\ReflectionException',

	]);
    $rcLoader->onClassLoaded[] = function ($old, $new) {
        trigger_error($old, E_USER_DEPRECATED);
    };
    $rcLoader->register();

	//If you are using the replacer
	//Flunorette\NetteDatabaseReplacer::replace();
	//end BC part
```
