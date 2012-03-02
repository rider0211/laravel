<?php namespace Laravel\Database\Schema\Grammars;

use Laravel\Fluent;
use Laravel\Database\Schema\Table;

class SQLServer extends Grammar {

	/**
	 * The keyword identifier for the database system.
	 *
	 * @var string
	 */
	public $wrapper = '[%s]';

	/**
	 * Generate the SQL statements for a table creation command.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return array
	 */
	public function create(Table $table, Fluent $command)
	{
		$columns = implode(', ', $this->columns($table));

		// First we will generate the base table creation statement. Other than auto
		// incrementing keys, no indexes will be created during the first creation
		// of the table as they're added in separate commands.
		$sql = 'CREATE TABLE '.$this->wrap($table).' ('.$columns.')';

		return $sql;
	}

	/**
	 * Geenrate the SQL statements for a table modification command.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return array
	 */
	public function add(Table $table, Fluent $command)
	{
		$columns = $this->columns($table);

		// Once we the array of column definitions, we need to add "add" to the
		// front of each definition, then we'll concatenate the definitions
		// using commas like normal and generate the SQL.
		$columns = implode(', ', array_map(function($column)
		{
			return 'ADD '.$column;

		}, $columns));

		return 'ALTER TABLE '.$this->wrap($table).' '.$columns;
	}

	/**
	 * Create the individual column definitions for the table.
	 *
	 * @param  Table  $table
	 * @return array
	 */
	protected function columns(Table $table)
	{
		$columns = array();

		foreach ($table->columns as $column)
		{
			// Each of the data type's have their own definition creation method,
			// which is responsible for creating the SQL for the type. This lets
			// us to keep the syntax easy and fluent, while translating the
			// types to the types used by the database.
			$sql = $this->wrap($column).' '.$this->type($column);

			$elements = array('unsigned', 'incrementer', 'nullable', 'defaults');

			foreach ($elements as $element)
			{
				$sql .= $this->$element($table, $column);
			}

			$columns[] = $sql;
		}

		return $columns;
	}

	/**
	 * Get the SQL syntax for indicating if a column is nullable.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function nullable(Table $table, Fluent $column)
	{
		return ($column->nullable) ? ' NULL' : ' NOT NULL';
	}

	/**
	 * Get the SQL syntax for specifying a default value on a column.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function defaults(Table $table, Fluent $column)
	{
		if ( ! is_null($column->default))
		{
			return " DEFAULT '".$column->default."'";
		}
	}

	/**
	 * Get the SQL syntax for defining an auto-incrementing column.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function incrementer(Table $table, Fluent $column)
	{
		if ($column->type == 'integer' and $column->increment)
		{
			return ' IDENTITY PRIMARY KEY';
		}
	}

	/**
	 * Generate the SQL statement for creating a primary key.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return string
	 */
	public function primary(Table $table, Fluent $command)
	{
		$name = $command->name;

		$columns = $this->columnize($columns);

		return 'ALTER TABLE '.$this->wrap($table)." ADD CONSTRAINT {$name} PRIMARY KEY ({$columns})";
	}

	/**
	 * Generate the SQL statement for creating a unique index.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return string
	 */
	public function unique(Table $table, Fluent $command)
	{
		return $this->key($table, $command, true);
	}

	/**
	 * Generate the SQL statement for creating a full-text index.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return string
	 */
	public function fulltext(Table $table, Fluent $command)
	{
		$columns = $this->columnize($command->columns);

		$table = $this->wrap($table);

		// SQL Server requires the creation of a full-text "catalog" before creating
		// a full-text index, so we'll first create the catalog then add another
		// separate statement for the index.
		$sql[] = "CREATE FULLTEXT CATALOG {$command->catalog}";

		$create =  "CREATE FULLTEXT INDEX ON ".$table." ({$columns}) ";

		// Full-text indexes must specify a unique, non-null column as the index
		// "key" and this should have been created manually by the developer in
		// a separate column addition command.
		$sql[] = $create .= "KEY INDEX {$command->key} ON {$command->catalog}";

		return $sql;
	}

	/**
	 * Generate the SQL statement for creating a regular index.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return string
	 */
	public function index(Table $table, Fluent $command)
	{
		return $this->key($table, $command);
	}

	/**
	 * Generate the SQL statement for creating a new index.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @param  bool    $unique
	 * @return string
	 */
	protected function key(Table $table, Fluent $command, $unique = false)
	{
		$columns = $this->columnize($command->columns);

		$create = ($unique) ? 'CREATE UNIQUE' : 'CREATE';

		return $create." INDEX {$command->name} ON ".$this->wrap($table)." ({$columns})";
	}

	/**
	 * Generate the SQL statement for creating a foreign key.
	 *
	 * @param  Table    $table
	 * @param  Command  $command
	 * @return string
	 */
	public function foreign(Table $table, Fluent $command)
	{
		$name = $command->name;

		// We need to wrap both of the table names in quoted identifiers to protect
		// against any possible keyword collisions, both the table on which the
		// command is being executed and the referenced table are wrapped.
		$table = $this->wrap($table);

		$on = $this->wrap($command->on);

		// Next we need to columnize both the command table's columns as well as
		// the columns referenced by the foreign key. We'll cast the referenced
		// columns to an array since they aren't by the fluent command.
		$foreign = $this->columnize($command->columns);

		$referenced = $this->columnize((array) $command->references);

		// Finally we can built the SQL. This should be the same for all database
		// platforms we support, but we'll just keep it in the grammars since
		// adding foreign keys using ALTER isn't supported by SQLite.
		$sql = "ALTER TABLE $table ADD CONSTRAINT $name ";

		die($sql .= "FOREIGN KEY ($foreign) REFERENCES $on ($referenced)");
	}

	/**
	 * Generate the SQL statement for a drop table command.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return string
	 */
	public function drop(Table $table, Fluent $command)
	{
		return 'DROP TABLE '.$this->wrap($table);
	}

	/**
	 * Generate the SQL statement for a drop column command.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return string
	 */
	public function drop_column(Table $table, Fluent $command)
	{
		$columns = array_map(array($this, 'wrap'), $command->columns);

		// Once we the array of column names, we need to add "drop" to the front
		// of each column, then we'll concatenate the columns using commas and
		// generate the alter statement SQL.
		$columns = implode(', ', array_map(function($column)
		{
			return 'DROP '.$column;

		}, $columns));

		return 'ALTER TABLE '.$this->wrap($table).' '.$columns;
	}

	/**
	 * Generate the SQL statement for a drop primary key command.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return string
	 */
	public function drop_primary(Table $table, Fluent $command)
	{
		return 'ALTER TABLE '.$this->wrap($table).' DROP CONSTRAINT '.$command->name;
	}

	/**
	 * Generate the SQL statement for a drop unqiue key command.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return string
	 */
	public function drop_unique(Table $table, Fluent $command)
	{
		return $this->drop_key($table, $command);
	}

	/**
	 * Generate the SQL statement for a drop full-text key command.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return string
	 */
	public function drop_fulltext(Table $table, Fluent $command)
	{
		$sql[] = "DROP FULLTEXT INDEX ".$command->name;

		$sql[] = "DROP FULLTEXT CATALOG ".$command->catalog;

		return $sql;
	}

	/**
	 * Generate the SQL statement for a drop index command.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return string
	 */
	public function drop_index(Table $table, Fluent $command)
	{
		return $this->drop_key($table, $command);
	}

	/**
	 * Generate the SQL statement for a drop key command.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return string
	 */
	protected function drop_key(Table $table, Fluent $command)
	{
		return "DROP INDEX {$command->name} ON ".$this->wrap($table);
	}

	/**
	 * Generate the data-type definition for a string.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_string(Fluent $column)
	{
		return 'NVARCHAR('.$column->length.')';
	}

	/**
	 * Generate the data-type definition for an integer.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_integer(Fluent $column)
	{
		return 'INT';
	}

	/**
	 * Generate the data-type definition for an integer.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_float(Fluent $column)
	{
		return 'FLOAT';
	}

	/**
	 * Generate the data-type definition for a boolean.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_boolean(Fluent $column)
	{
		return 'TINYINT';
	}

	/**
	 * Generate the data-type definition for a date.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_date(Fluent $column)
	{
		return 'DATETIME';
	}

	/**
	 * Generate the data-type definition for a timestamp.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_timestamp(Fluent $column)
	{
		return 'TIMESTAMP';
	}

	/**
	 * Generate the data-type definition for a text column.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_text(Fluent $column)
	{
		return 'NVARCHAR(MAX)';
	}

	/**
	 * Generate the data-type definition for a blob.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_blob(Fluent $column)
	{
		return 'VARBINARY(MAX)';
	}

}