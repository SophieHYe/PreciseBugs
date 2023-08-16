<?php

	/*
	 *
	 *	LiftKit MVC PHP Framework
	 *
	 *
	 */


	namespace LiftKit\Database\Query;

	use LiftKit\Database\Connection\Connection as Database;
	use LiftKit\Database\Query\Exception\Query as QueryBuilderException;
	use LiftKit\Database\Query\Condition\Condition as DatabaseQueryCondition;
	use LiftKit\Database\Result\Result as DatabaseResult;

	use LiftKit\Database\Query\Raw\Raw;
	use LiftKit\Database\Query\Join\Join;


	/**
	 * Class Query
	 *
	 * @package LiftKit\Database\Query
	 *
	 * @method $this whereEqual(mixed $compare, mixed $against)
	 * @method $this orWhereEqual(mixed $compare, mixed $against)
	 * @method $this whereNotEqual(mixed $compare, mixed $against)
	 * @method $this orWhereNotEqual(mixed $compare, mixed $against)
	 *
	 * @method $this whereLessThan(mixed $compare, mixed $against)
	 * @method $this orWhereLessThan(mixed $compare, mixed $against)
	 * @method $this whereLessThanOrEqual(mixed $compare, mixed $against)
	 *
	 * @method $this whereGreaterThan(mixed $compare, mixed $against)
	 * @method $this orWhereGreaterThan(mixed $compare, mixed $against)
	 * @method $this whereGreaterThanOrEqual(mixed $compare, mixed $against)
	 *
	 * @method $this whereIn(mixed $needle, mixed $haystack)
	 * @method $this orWhereIn(mixed $needle, mixed $haystack)
	 * @method $this whereNotIn(mixed $needle, mixed $haystack)
	 * @method $this orWhereNotIn(mixed $needle, mixed $haystack)
	 *
	 * @method $this whereIs(mixed $compare, mixed $against)
	 * @method $this orWhereIs(mixed $compare, mixed $against)
	 * @method $this whereNotIs(mixed $compare, mixed $against)
	 * @method $this orWhereNotIs(mixed $compare, mixed $against)
	 *
	 * @method $this whereLike(mixed $compare, string $expression)
	 * @method $this orWhereLike(mixed $compare, string $expression)
	 * @method $this whereNotLike(mixed $compare, string $expression)
	 * @method $this orWhereNotLike(mixed $compare, string $expression)
	 *
	 * @method $this whereRegexp(mixed $compare, string $regexp)
	 * @method $this orWhereRegexp(mixed $compare, string $regexp)
	 * @method $this whereNotRegexp(mixed $compare, string $regexp)
	 * @method $this orWhereNotRegexp(mixed $compare, string $regexp)
	 *
	 * @method $this whereCondition(DatabaseQueryCondition $condition)
	 * @method $this orWhereCondition(DatabaseQueryCondition $condition)
	 * @method $this whereNotCondition(DatabaseQueryCondition $condition)
	 * @method $this orWhereNotCondition(DatabaseQueryCondition $condition)
	 *
	 * @method $this whereRaw(string $condition)
	 * @method $this orWhereRaw(string $condition)
	 * @method $this whereNotRaw(string $condition)
	 * @method $this orWhereNotRaw(string $condition)
	 *
	 * @method $this whereSearch(array $fields, string $query)
	 *
	 * @method $this havingEqual(mixed $compare, mixed $against)
	 * @method $this orHavingEqual(mixed $compare, mixed $against)
	 * @method $this havingNotEqual(mixed $compare, mixed $against)
	 * @method $this orHavingNotEqual(mixed $compare, mixed $against)
	 *
	 * @method $this havingLessThan(mixed $compare, mixed $against)
	 * @method $this orHavingLessThan(mixed $compare, mixed $against)
	 * @method $this havingLessThanOrEqual(mixed $compare, mixed $against)
	 *
	 * @method $this havingGreaterThan(mixed $compare, mixed $against)
	 * @method $this orHavingGreaterThan(mixed $compare, mixed $against)
	 * @method $this havingGreaterThanOrEqual(mixed $compare, mixed $against)
	 *
	 * @method $this havingIn(mixed $needle, mixed $haystack)
	 * @method $this orHavingIn(mixed $needle, mixed $haystack)
	 * @method $this havingNotIn(mixed $needle, mixed $haystack)
	 * @method $this orHavingNotIn(mixed $needle, mixed $haystack)
	 *
	 * @method $this havingIs(mixed $compare, mixed $against)
	 * @method $this orHavingIs(mixed $compare, mixed $against)
	 * @method $this havingNotIs(mixed $compare, mixed $against)
	 * @method $this orHavingNotIs(mixed $compare, mixed $against)
	 *
	 * @method $this havingLike(mixed $compare, string $expression)
	 * @method $this orHavingLike(mixed $compare, string $expression)
	 * @method $this havingNotLike(mixed $compare, string $expression)
	 * @method $this orHavingNotLike(mixed $compare, string $expression)
	 *
	 * @method $this havingRegexp(mixed $compare, string $regexp)
	 * @method $this orHavingRegexp(mixed $compare, string $regexp)
	 * @method $this havingNotRegexp(mixed $compare, string $regexp)
	 * @method $this orHavingNotRegexp(mixed $compare, string $regexp)
	 *
	 * @method $this havingCondition(DatabaseQueryCondition $condition)
	 * @method $this orHavingCondition(DatabaseQueryCondition $condition)
	 * @method $this havingNotCondition(DatabaseQueryCondition $condition)
	 * @method $this orHavingNotCondition(DatabaseQueryCondition $condition)
	 *
	 * @method $this havingRaw(string $condition)
	 * @method $this orHavingRaw(string $condition)
	 * @method $this havingNotRaw(string $condition)
	 * @method $this orHavingNotRaw(string $condition)
	 *
	 * @method $this havingSearch(array $fields, string $query)
	 */
	abstract class Query
	{
		const QUERY_TYPE_SELECT        = 'SELECT';
		const QUERY_TYPE_INSERT        = 'INSERT';
		const QUERY_TYPE_INSERT_IGNORE = 'INSERT IGNORE';
		const QUERY_TYPE_INSERT_UPDATE = 'INSERT UPDATE';
		const QUERY_TYPE_UPDATE        = 'UPDATE';
		const QUERY_TYPE_DELETE        = 'DELETE';

		const QUERY_ORDER_ASC  = 'ASC';
		const QUERY_ORDER_DESC = 'DESC';

		protected $database;

		protected $type;
		protected $table;
		protected $alias;

		protected $fields = array();
		protected $data   = array();

		/**
		 * @var Join[]
		 */
		protected $joins  = array();
		protected $unions = array();

		protected $whereCondition;
		protected $havingCondition;

		protected $groupBys = array();
		protected $orderBys = array();

		protected $start = 0;
		protected $limit = null;

		protected $isCached = false;

		protected $entityHydrationRule = null;
		protected $prependFields       = false;

		protected $whereConditionMethodMap = array(
			'whereEqual'              => 'equal',
			'orWhereEqual'            => 'orEqual',
			'whereNotEqual'           => 'notEqual',
			'orWhereNotEqual'         => 'orNotEqual',

			'whereLessThan'           => 'lessThan',
			'orWhereLessThan'         => 'orLessThan',
			'whereLessThanOrEqual'    => 'lessThanOrEqual',

			'whereGreaterThan'        => 'greaterThan',
			'orWhereGreaterThan'      => 'orGreaterThan',
			'whereGreaterThanOrEqual' => 'greaterThanOrEqual',

			'whereIn'                 => 'in',
			'orWhereIn'               => 'orIn',
			'whereNotIn'              => 'notIn',
			'orWhereNotIn'            => 'orNotIn',

			'whereIs'                 => 'is',
			'orWhereIs'               => 'orIs',
			'whereNotIs'              => 'notIs',
			'orWhereNotIs'            => 'orNotIs',

			'whereLike'               => 'like',
			'orWhereLike'             => 'orLike',
			'whereNotLike'            => 'notLike',
			'orWhereNotLike'          => 'orNotLike',

			'whereRegexp'             => 'regexp',
			'orWhereRegexp'           => 'orRegexp',
			'whereNotRegexp'          => 'notRegexp',
			'orWhereNotRegexp'        => 'orNotRegexp',

			'whereCondition'          => 'condition',
			'orWhereCondition'        => 'orCondition',
			'whereNotCondition'       => 'notCondition',
			'orWhereNotCondition'     => 'orNotCondition',

			'whereRaw'                => 'raw',
			'orWhereRaw'              => 'orRaw',
			'notWhereRaw'             => 'notRaw',
			'orWhereNotRaw'           => 'orNotRaw',

			'whereSearch'             => 'search',
		);

		protected $havingConditionMethodMap = array(
			'havingEqual'              => 'equal',
			'orHavingEqual'            => 'orEqual',
			'havingNotEqual'           => 'notEqual',
			'orHavingNotEqual'         => 'orNotEqual',

			'havingLessThan'           => 'lessThan',
			'orHavingLessThan'         => 'orLessThan',
			'havingLessThanOrEqual'    => 'lessThanOrEqual',

			'havingGreaterThan'        => 'greaterThan',
			'orHavingGreaterThan'      => 'orGreaterThan',
			'havingGreaterThanOrEqual' => 'greaterThanOrEqual',

			'havingIn'                 => 'in',
			'orHavingIn'               => 'orIn',
			'havingNotIn'              => 'notIn',
			'orHavingNotIn'            => 'orNotIn',

			'havingIs'                 => 'is',
			'orHavingIs'               => 'orIs',
			'havingNotIs'              => 'notIs',
			'orHavingNotIs'            => 'orNotIs',

			'havingLike'               => 'like',
			'orHavingLike'             => 'orLike',
			'havingNotLike'            => 'notLike',
			'orHavingNotLike'          => 'orNotLike',

			'havingRegexp'             => 'regexp',
			'orHavingRegexp'           => 'orRegexp',
			'havingNotRegexp'          => 'notRegexp',
			'orHavingNotRegexp'        => 'orNotRegexp',

			'havingCondition'          => 'condition',
			'orHavingCondition'        => 'orCondition',
			'havingNotCondition'       => 'notCondition',
			'orHavingNotCondition'     => 'orNotCondition',

			'havingRaw'                => 'raw',
			'orHavingRaw'              => 'orRaw',
			'notHavingRaw'             => 'notRaw',
			'orHavingNotRaw'           => 'orNotRaw',
		);


		public function __construct (Database $database)
		{
			$this->database        = $database;
			$this->whereCondition  = $this->database->createCondition();
			$this->havingCondition = $this->database->createCondition();

		}


		public function __call ($method, $arguments)
		{
			if (isset($this->whereConditionMethodMap[$method])) {
				$condition = $this->database->createCondition();

				call_user_func_array(
					array(
						$condition,
						$this->whereConditionMethodMap[$method]
					),
					$arguments
				);

				return $this->where($condition);

			} else if (isset($this->havingConditionMethodMap[$method])) {
				$condition = $this->database->createCondition();

				call_user_func_array(
					array(
						$condition,
						$this->havingConditionMethodMap[$method]
					),
					$arguments
				);

				return $this->having($condition);

			} else {
				throw new QueryBuilderException('Method ' . $method . ' not found.');
			}
		}


		public function setCache ($bool)
		{
			$this->isCached = $bool;

			return $this;
		}


		public function isCached ()
		{
			return (bool) $this->isCached;
		}


		public function setPrependFields ($bool)
		{
			$this->prependFields = (boolean) $bool;

			return $this;
		}


		public function setEntity ($dependencyInjectionRule)
		{
			$this->entityHydrationRule = $dependencyInjectionRule;

			return $this;
		}


		public function composeWith ($query)
		{
			$query = $this->database->toQuery($query);

			$this->type = $query->type ? $query->type : $this->type;

			if ($query->prependFields) {
				$this->fields = array_merge($query->fields, $this->fields);
				$this->data   = array_merge($query->data, $this->data);
			} else {
				$this->fields = array_merge($this->fields, $query->fields);
				$this->data   = array_merge($this->data, $query->data);
			}

			$this->joins  = array_merge($this->joins, $query->joins);
			$this->unions = array_merge($this->unions, $query->unions);

			if (! $query->whereCondition->isEmpty()) {
				$this->where($query->whereCondition);
			}

			if (! $query->havingCondition->isEmpty()) {
				$this->having($query->havingCondition);
			}

			$this->groupBys = array_merge($this->groupBys, $query->groupBys);
			$this->orderBys = array_merge($this->orderBys, $query->orderBys);

			$this->start = $query->start ? $query->start : $this->start;
			$this->limit = $query->limit ? $query->limit : $this->limit;

			$this->entityHydrationRule = $query->entityHydrationRule ?: $this->entityHydrationRule;

			return $this;
		}


		public function __toString ()
		{
			return $this->getRaw();
		}


		public function getRaw ()
		{
			if ($this->type == self::QUERY_TYPE_SELECT) {
				return $this->generateSelectQuery();

			} else if ($this->type == self::QUERY_TYPE_INSERT) {
				return $this->generatInsertQuery();

			} else if ($this->type == self::QUERY_TYPE_INSERT_IGNORE) {
				return $this->generateInsertIgnoreQuery();

			} else if ($this->type == self::QUERY_TYPE_INSERT_UPDATE) {
				return $this->generateInsertUpdateQuery();

			} else if ($this->type == self::QUERY_TYPE_UPDATE) {
				return $this->generateUpdateQuery();

			} else if ($this->type == self::QUERY_TYPE_DELETE) {
				return $this->generateDeleteQuery();

			} else {
				throw new QueryBuilderException('Invalid query type '.var_export($this->type, true));
			}
		}


		protected function generateSelectQuery ()
		{
			$queryLines[] = "SELECT " . $this->processFields();

			if ($this->alias) {
				$queryLines[] = "FROM " . $this->filterIdentifier($this->table) . " AS " . $this->filterIdentifier($this->alias);
			} else {
				$queryLines[] = "FROM " . $this->filterIdentifier($this->table);
			}

			$queryLines[] = $this->processJoins();
			$queryLines[] = $this->processWhere();
			$queryLines[] = $this->processGroupBy();
			$queryLines[] = $this->processHaving();
			$queryLines[] = $this->processOrderBy();
			$queryLines[] = $this->processLimit();

			$queryLines = array_filter($queryLines);

			return implode($queryLines, PHP_EOL);
		}


		protected function generatInsertQuery ()
		{
			$queryLines[] = "INSERT INTO " . $this->filterIdentifier($this->table) . " " . $this->processInsertColumns();
			$queryLines[] = "VALUES " . $this->processInsertValues();

			$queryLines = array_filter($queryLines);

			return implode($queryLines, PHP_EOL);
		}


		protected function generateInsertIgnoreQuery ()
		{
			$queryLines[] = "INSERT IGNORE INTO ".$this->filterIdentifier($this->table);
			$queryLines[] = "SET " . $this->processUpdateData();

			$queryLines = array_filter($queryLines);

			return implode($queryLines, PHP_EOL);
		}


		protected function generateInsertUpdateQuery ()
		{
			$queryLines[] = "INSERT INTO " . $this->filterIdentifier($this->table);
			$queryLines[] = "SET " . $this->processUpdateData();
			$queryLines[] = "ON DUPLICATE KEY UPDATE " . $this->processUpdateData();

			$queryLines = array_filter($queryLines);

			return implode($queryLines, PHP_EOL);
		}


		protected function generateUpdateQuery ()
		{
			$queryLines[] = "UPDATE " . $this->filterIdentifier($this->table);
			$queryLines[] = "SET " . $this->processUpdateData();
			$queryLines[] = $this->processJoins();
			$queryLines[] = $this->processWhere();
			$queryLines[] = $this->processGroupBy();
			$queryLines[] = $this->processHaving();
			$queryLines[] = $this->processOrderBy();
			$queryLines[] = $this->processLimit();

			$queryLines = array_filter($queryLines);

			return implode($queryLines, PHP_EOL);
		}


		protected function generateDeleteQuery ()
		{
			$queryLines[] = "DELETE " . $this->processFields();
			$queryLines[] = "FROM " . $this->filterIdentifier($this->table);
			$queryLines[] = $this->processJoins();
			$queryLines[] = $this->processWhere();
			$queryLines[] = $this->processGroupBy();
			$queryLines[] = $this->processHaving();
			$queryLines[] = $this->processOrderBy();
			$queryLines[] = $this->processLimit();

			$queryLines = array_filter($queryLines);

			return implode($queryLines, PHP_EOL);
		}


		public function getJoins ()
		{
			return $this->joins;
		}


		public function getOrderBys ()
		{
			return $this->orderBys;
		}


		public function getTable ()
		{
			return $this->table;
		}


		public function getType ()
		{
			return $this->type;
		}


		public function execute ()
		{
			$cache = $this->database->getCache();
			$cache->refreshCache($this);

			if ($result = $cache->getCachedResult($this)) {
				return $result;
			} else {
				$result = $this->database->query($this, array(), $this->entityHydrationRule);

				if ($result instanceof DatabaseResult) {
					$cache->cacheQuery($this, $result);
				}

				return $result;
			}
		}


		public function set ($data)
		{
			$this->data = array_merge($this->data, (array)$data);

			return $this;
		}


		public function fields ($fields, $prepend = false)
		{
			if (!is_array($fields)) {
				throw new QueryBuilderException('The fields parameter must be an array.');
			}

			foreach ($fields as $field) {
				if (is_array($field)) {
					$alias = $field[1];
					$field = $field[0];
				}

				if ($field instanceof self) {
					$field = '(' . $field->getRaw() . ')';
				} else if (! $field instanceof Raw) {
					$field = $this->filterIdentifier($field);
				}

				if (isset($alias)) {
					$field .= ' AS ' . $this->filterIdentifier($alias);
				}

				if ($prepend) {
					array_unshift($this->fields, $field);
				} else {
					$this->fields[] = $field;
				}
			}

			return $this;
		}


		public function prependFields ($fields)
		{
			return $this->fields($fields, true);
		}


		public function addField ($field, $alias = null, $prepend = false)
		{
			if (!is_null($alias)) {
				$field = array($field, $alias);
			}

			return $this->fields(array($field), $prepend);
		}


		public function prependField ($field, $alias = null)
		{
			return $this->addField($field, $alias, true);
		}


		public function select ()
		{
			$this->type = self::QUERY_TYPE_SELECT;
			$this->fields(func_get_args());

			return $this;
		}


		public function insert ()
		{
			$this->type = self::QUERY_TYPE_INSERT;

			return $this;
		}


		public function insertIgnore ()
		{
			$this->type = self::QUERY_TYPE_INSERT_IGNORE;

			return $this;
		}


		public function insertUpdate ()
		{
			$this->type = self::QUERY_TYPE_INSERT_UPDATE;

			return $this;
		}


		public function update ($table = null)
		{
			$this->type = self::QUERY_TYPE_UPDATE;

			if ($table) {
				$this->table($table);
			}

			return $this;
		}


		public function delete ()
		{
			$this->type = self::QUERY_TYPE_DELETE;
			$this->fields(func_get_args());

			return $this;
		}


		public function table ($table, $alias = null)
		{
			$this->table = $table;
			$this->alias = $alias;

			return $this;
		}


		public function from ($table, $alias = null)
		{
			$this->table($table, $alias);

			return $this;
		}


		public function into ($table)
		{
			$this->table($table);

			return $this;
		}


		public function leftJoin ($table, $condition, $alias = null)
		{
			$this->joins[] = new Join(
				$this->database,
				'LEFT JOIN',
				$table,
				'ON',
				$condition,
				$alias
			);

			return $this;
		}


		public function leftJoinUsing ($table, $field, $alias = null)
		{
			$this->joins[] = new Join(
				$this->database,
				'LEFT JOIN',
				$table,
				'USING',
				$this->filterIdentifier($field),
				$alias
			);

			return $this;
		}


		public function leftJoinEqual ($table, $left, $right, $alias = null)
		{
			$this->joins[] = new Join(
				$this->database,
				'LEFT JOIN',
				$table,
				'ON',
				$this->filterIdentifier($left) . ' = ' . $this->filterIdentifier($right),
				$alias
			);

			return $this;
		}


		public function rightJoin ($table, $condition, $alias = null)
		{
			$this->joins[] = new Join(
				$this->database,
				'RIGHT JOIN',
				$table,
				'ON',
				$condition,
				$alias
			);

			return $this;
		}


		public function rightJoinUsing ($table, $field, $alias = null)
		{
			$this->joins[] = new Join(
				$this->database,
				'RIGHT JOIN',
				$table,
				'USING',
				$this->filterIdentifier($field),
				$alias
			);

			return $this;
		}


		public function rightJoinEqual ($table, $left, $right, $alias = null)
		{
			$this->joins[] = new Join(
				$this->database,
				'RIGHT JOIN',
				$table,
				'ON',
				$this->filterIdentifier($left) . ' = ' . $this->filterIdentifier($right),
				$alias
			);

			return $this;
		}


		public function innerJoin ($table, $condition, $alias = null)
		{
			$this->joins[] = new Join(
				$this->database,
				'INNER JOIN',
				$table,
				'ON',
				$condition,
				$alias
			);

			return $this;
		}


		public function innerJoinUsing ($table, $field, $alias = null)
		{
			$this->joins[] = new Join(
				$this->database,
				'INNER JOIN',
				$table,
				'USING',
				$this->filterIdentifier($field),
				$alias
			);

			return $this;
		}


		public function innerJoinEqual ($table, $left, $right, $alias = null)
		{
			$this->joins[] = new Join(
				$this->database,
				'INNER JOIN',
				$table,
				'ON',
				$this->filterIdentifier($left) . ' = ' . $this->filterIdentifier($right),
				$alias
			);

			return $this;
		}


		public function where ($condition)
		{
			if ($condition instanceof DatabaseQueryCondition) {
				$this->whereCondition->condition($condition);
			} else if ($condition instanceof Raw || is_string($condition)) {
				$this->whereCondition->raw($condition);
			} else {
				throw new QueryBuilderException(gettype($condition).' is not a valid condition type.');
			}

			return $this;
		}


		public function orWhere ($condition)
		{
			if ($condition instanceof DatabaseQueryCondition) {
				$this->whereCondition->orCondition($condition);
			} else if ($condition instanceof Raw || is_string($condition)) {
				$this->whereCondition->orRaw($condition);
			} else {
				throw new QueryBuilderException(gettype($condition).' is not a valid condition type.');
			}

			return $this;
		}


		public function notWhere ($condition)
		{
			if ($condition instanceof DatabaseQueryCondition) {
				$this->whereCondition->notCondition($condition);
			} else if ($condition instanceof Raw || is_string($condition)) {
				$this->whereCondition->notRaw($condition);
			} else {
				throw new QueryBuilderException(gettype($condition).' is not a valid condition type.');
			}

			return $this;
		}


		public function orNotWhere ($condition)
		{
			if ($condition instanceof DatabaseQueryCondition) {
				$this->whereCondition->orNotCondition($condition);
			} else if ($condition instanceof Raw || is_string($condition)) {
				$this->whereCondition->orNotRaw($condition);
			} else {
				throw new QueryBuilderException(gettype($condition).' is not a valid condition type.');
			}

			return $this;
		}


		public function having ($condition)
		{
			if ($condition instanceof DatabaseQueryCondition) {
				$this->havingCondition->condition($condition);
			} else if ($condition instanceof Raw || is_string($condition)) {
				$this->havingCondition->raw($condition);
			} else {
				throw new QueryBuilderException(gettype($condition).' is not a valid condition type.');
			}

			return $this;
		}


		public function orHaving ($condition)
		{
			if ($condition instanceof DatabaseQueryCondition) {
				$this->havingCondition->orCondition($condition);
			} else if ($condition instanceof Raw || is_string($condition)) {
				$this->havingCondition->orRaw($condition);
			} else {
				throw new QueryBuilderException(gettype($condition).' is not a valid condition type.');
			}

			return $this;
		}


		public function notHaving ($condition)
		{
			if ($condition instanceof DatabaseQueryCondition) {
				$this->havingCondition->notCondition($condition);
			} else if ($condition instanceof Raw || is_string($condition)) {
				$this->havingCondition->notRaw($condition);
			} else {
				throw new QueryBuilderException(gettype($condition).' is not a valid condition type.');
			}

			return $this;
		}


		public function orNotHaving ($condition)
		{
			if ($condition instanceof DatabaseQueryCondition) {
				$this->havingCondition->orNotCondition($condition);
			} else if ($condition instanceof Raw || is_string($condition)) {
				$this->havingCondition->orNotRaw($condition);
			} else {
				throw new QueryBuilderException(gettype($condition).' is not a valid condition type.');
			}

			return $this;
		}


		public function groupBy ($field)
		{
			$this->groupBys[] = $this->filterIdentifier($field);

			return $this;
		}


		public function orderBy ($field, $direction = 'ASC')
		{
			$this->orderBys[] = array(
				'field'     => $this->filterIdentifier($field),
				'direction' => $direction,
			);

			return $this;
		}


		public function start ($start)
		{
			$this->start = intval($start);

			return $this;
		}


		public function limit ($limit)
		{
			$this->limit = intval($limit);

			return $this;
		}


		// PROTECTED METHODS


		protected function processFields ()
		{
			if (empty($this->fields) && $this->getType() == self::QUERY_TYPE_SELECT) {
				return $this->database->quoteIdentifier('*');
			} else {
				return implode(', ', $this->fields);
			}
		}


		protected function processUpdateData ()
		{
			if (empty($this->data)) {
				return '';
			} else {
				$fields = array();

				foreach ($this->data as $key => $value) {
					$fields[] = $this->database->quoteIdentifier($key) . " = " . $this->database->quote($value);
				}

				return implode(', ', $fields);
			}
		}


		protected function processInsertColumns ()
		{
			if (empty($this->data)) {
				return '';
			} else {
				$columns = array_keys($this->data);

				$columns = array_map(function ($columnName) {
					return $this->database->quoteIdentifier($columnName);
				}, $columns);

				return '(' . implode(', ', $columns) . ')';
			}
		}


		protected function processInsertValues ()
		{
			if (empty($this->data)) {
				return '';
			} else {
				$values = $this->data;

				$values = array_map(function ($columnName) {
					return $this->database->quote($columnName);
				}, $values);

				return '(' . implode(', ', $values) . ')';
			}
		}


		protected function processJoins ()
		{
			if (empty($this->joins)) {
				return '';
			} else {
				$joins = array();

				foreach ($this->joins as $join) {
					$joins[] = $join->toString();
				}

				return implode("\n", $joins);
			}
		}


		protected function processWhere ()
		{
			if (!$this->whereCondition->isEmpty()) {
				return "WHERE " . $this->whereCondition->getRaw();
			} else {
				return '';
			}
		}


		protected function processHaving ()
		{
			if (!$this->havingCondition->isEmpty()) {
				return "HAVING " . $this->havingCondition->getRaw();
			} else {
				return '';
			}
		}


		protected function processGroupBy ()
		{
			if (empty($this->groupBys)) {
				return '';
			} else {
				return "GROUP BY " . implode(', ', $this->groupBys);
			}
		}


		protected function processOrderBy ()
		{
			if (empty($this->orderBys)) {
				return '';
			} else {
				$sql       = "ORDER BY ";
				$orderBys = array();

				foreach ($this->orderBys as $orderBy) {
					$orderBys[] = $orderBy['field'] . ' ' . $orderBy['direction'];
				}

				return $sql.implode(', ', $orderBys);
			}
		}


		protected function processLimit ()
		{
			if (strval(intval($this->limit)) === strval($this->limit)) {
				return "LIMIT " . intval($this->start) . ", " . intval($this->limit);
			} else {
				return '';
			}
		}


		protected function filterIdentifier ($value)
		{
			return $this->database->quoteIdentifier($value);
		}
	}


