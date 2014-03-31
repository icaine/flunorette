<?php

namespace Flunorette;

/**
 * Information about tables and columns structure.
 */
interface IReflection {

	const
		FIELD_TEXT = 'string',
		FIELD_BINARY = 'bin',
		FIELD_BOOL = 'bool',
		FIELD_INTEGER = 'int',
		FIELD_FLOAT = 'float',
		FIELD_DATE = 'date',
		FIELD_TIME = 'time',
		FIELD_DATETIME = 'datetime',
		FIELD_UNIX_TIMESTAMP = 'timestamp',
		FIELD_TIME_INTERVAL = 'timeint';

	/**
	 * Gets primary key of $table.
	 * @param  string
	 * @return string|array
	 */
	function getPrimary($table);

	/**
	 * Gets referenced table & referenced column.
	 * Example:
	 *     author, book returns array(book, author_id)
	 *
	 * @param  string  source table
	 * @param  string  referencing key
	 * @return array   array(referenced table, referenced column)
	 */
	function getHasManyReference($table, $key);

	/**
	 * Gets referenced table & referencing column.
	 * Example
	 *     book, author      returns array(author, author_id)
	 *     book, translator  returns array(author, translator_id)
	 *
	 * @param  string  source table
	 * @param  string  referencing key
	 * @return array   array(referenced table, referencing column)
	 */
	function getBelongsToReference($table, $key);

	/**
	 * @return bool
	 */
	public function hasTable($name);

}
