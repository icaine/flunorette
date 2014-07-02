[![Build Status](https://travis-ci.org/icaine/flunorette.svg?tag=1.0.2)](https://travis-ci.org/icaine/flunorette) &nbsp; ![Downloads Total](http://img.shields.io/packagist/dt/icaine/flunorette.svg) &nbsp; ![Latest Version](http://img.shields.io/packagist/v/icaine/flunorette.svg)

What is Flunorette?
===================
**Flunorette**

- is a **database layer** simplifying work with tables, relations and CRUD operations.

- is heavily based on **[Nette Database](http://doc.nette.org/en/2.0/database) ([Table](http://doc.nette.org/en/2.0/database-table))** - NDB(T) and uses modified **[FluentPDO](http://fluentpdo.com)** as SQL builder.

- is derived directly from NDB(T)2.0 thus it's like on 95% compatible with it[1]
- **uses the same syntax/API as NDBT2.0** but takes faster code from NDBT2.1.

*The main reason i decided to create Flunorette was that NDBT is superior for simple websites like blogs but with complex web apps you sooner or later hit the wall. E.g. as soon as you need create queries with conditions inside JOIN clauses or any other advance queries there is no way how to achieve it with NDBT*.




[1]: Flunorette has a replacer that can help you with replacing NDB(T)2.0.

---

Documentation
-----------------------------------------------------

Can be found on [wiki pages](https://github.com/icaine/flunorette/wiki).

###Examples

Some examples are on [wiki pages](https://github.com/icaine/flunorette/wiki) and even more examples can be seen in [tests](https://github.com/icaine/Flunorette/tree/master/tests).

---

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
