<?php

namespace Psa\Qb;

/**
 * QueryBuilder is the query builder for MySQL databases.
 * @version 0.5.9
 */
 class QueryBuilder
 {
    /**
     * @var mixed The database connection object.
     */
    private $connect;

    /**
     * @var string The SELECT part of the query.
     */
    private $select = '*';

    /**
     * @var int|null The LIMIT part of the query.
     */
    private $limit = null;

    /**
     * @var int|null The OFFSET part of the query.
     */
    private $offset = null;

    /**
     * @var array The WHERE part of the query.
     */
    private $where = ['and'];

    /**
     * @var array The HAVING part of the query.
     */
    private $having = ['and'];

    /**
     * @var string|array|null The ORDER BY part of the query.
     */
    private $orderBy = null;

    /**
     * @var string|array|null The GROUP BY part of the query.
     */
    private $groupBy = null;

    /**
     * @var array|null The JOIN part of the query.
     */
    private $join = null;

    /**
     * @var bool Whether to use DISTINCT.
     */
    private $is_distinct = false;

    /**
     * @var string|null The table name.
     */
    private $table = null;

    /**
     * @var array The fields to be treated as JSON.
     */
    private array $jsonFields = [];

    /**
     * Constructor.
     * @param mixed $connect The database connection object.
     */
    public function __construct($connect)
    {
        $this->connect = $connect;
    }

    /**
     * Sets the FROM part of the query.
     * @param string $table The table name.
     * @return self
     */
    public function from($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Enable the DISTINCT statement is used to return only distinct (different) values.
     * @return self
     */
    public function distinct()
    {
        $this->is_distinct = true;
        return $this;
    }

    /**
     * Executes the query and returns a single row of result.
     * @return array|null
     */
    public function one()
    {
        $this->limit(1);

        $res = $this->connect->getRow($this->sql());

        if (!empty($this->jsonFields)) {
            return $res === false ? null : $this->decodeJsonFields($res);
        } else {
            return $res === false ? null : $res;
        }
    }

    /**
     * Executes the query and returns all results as an array.
     * @return array
     */
    public function all()
    {
        if (!empty($this->jsonFields)) {
            return array_map(
                [$this, 'decodeJsonFields'],
                $this->connect->getRows($this->sql())
            );
        } else {
            return $this->connect->getRows($this->sql());
        }
    }

    /**
     * Returns the actual SQL.
     * @return string
     */
    public function sql()
    {
        $sql = 'SELECT' . $this->distinctSql() . $this->selectSql();
        $sql .= ' FROM ' . $this->table . $this->joinSql() . $this->whereSql() . $this->groupBySql() . $this->havingSql() .$this->orderBySql() . $this->limitSql();

        return $sql;
    }

    /**
     * Sets the LIMIT part of the query.
     * @param int $limit The limit.
     * @return self
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Sets the OFFSET part of the query.
     * @param int $offset The offset.
     * @return self
     */
    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Sets the WHERE part of the query.
     * @param array|string $condition The condition.
     * @return self
     */
    public function where($condition)
    {
        $this->where[] = ['and', $condition];
        return $this;
    }

    /**
     * Sets the WHERE part of the query.
     * @param array|string $condition The condition.
     * @return self
     */
    public function having($condition)
    {
        $this->having[] = ['and', $condition];
        return $this;
    }

    /**
     * Sets the AND WHERE part of the query.
     * @param array|string $condition The condition.
     * @return self
     */
    public function andWhere($condition)
    {
        $this->where[] = ['and', $condition];
        return $this;
    }

    /**
     * Sets the OR WHERE part of the query.
     * @param array|string $condition The condition.
     * @return self
     */
    public function orWhere($condition)
    {
        $this->where[] = ['or', $condition];
        return $this;
    }

    /**
     * Prepare array for condition.
     * @param array $where The where condition.
     * @return array
     */
    private function prepareWhere($where)
    {
        $output = [];

        foreach ($where as $k1 => $v1) {
            if (is_string($k1)) {
                $output[] = ['=', $k1, $v1];
            } else if (is_array($v1) && array_key_exists(0, $v1) && $v1[0] === 'and') {
                $and = ['and'];
                foreach ($this->prepareWhere(array_slice($v1, 1)) as $var) {
                    $and[] = $var;
                }
                $output[] = $and;
            } else if (is_array($v1) && array_key_exists(0, $v1) && $v1[0] === 'or') {
                $and = ['or'];
                foreach ($this->prepareWhere(array_slice($v1, 1)) as $var) {
                    $and[] = $var;
                }
                $output[] = $and;
            } else if (is_array($v1) && !array_key_exists(0, $v1)) {
                $and = ['and'];
                foreach ($this->prepareWhere($v1) as $var) {
                    $and[] = $var;
                }
                $output[] = $and;
            } else {
                $output[$k1] = $v1;
            }
        }

        return $output;
    }

    /**
     * Recursive parse where conditions.
     * @param array $condition The condition.
     * @param bool $canBrackets Whether to use brackets.
     * @return string
     */
    private function parseCondition($condition, $canBrackets = false)
    {
        $sql = '';

        if (isset($condition[0])) {
            if ($condition[0] === 'and' || $condition[0] === 'or') {
                $cnt = 0;
                $canBrackets = count($condition) > 2 || $canBrackets === true;
                foreach ($condition as $value) {
                    if ($cnt > 0) {
                        if ($cnt > 1) {
                            $sql .= ' ' . ($condition[0] === 'or' ? 'OR' : 'AND') . ' ';
                        }

                        if ($value[0] === 'or' || $value[0] === 'and') {
                            $mustBrackets = count($value) > 2 && $canBrackets;
                            $sql .= ($mustBrackets === true ? '(' : '') . $this->parseCondition($value, $canBrackets) . ($mustBrackets === true ? ')' : '');
                        } else {
                            if ((is_array($value[2]) || $value[2] instanceof QueryBuilder) && ($value[0] === '=' || $value[0] === 'in' || $value[0] === 'not in')) {
                                if ($value[2] instanceof QueryBuilder || count($value[2]) > 0) {
                                    $sql .= $value[1] . ($value[0] === 'not in' ? ' NOT' : '') . " IN (";
                                        if ($value[2] instanceof QueryBuilder) {
                                            $sql .= $value[2]->sql();
                                        } else {
                                            $iteration = count($value[2]);
                                            foreach ($value[2] as $item_value) {
                                                $sql .= $this->connect->quote($item_value);
                                                $iteration--;
                                                if ($iteration !== 0) {
                                                    $sql .= ', ';
                                                }
                                            }
                                        }

                                    $sql .= ')';
                                } else {
                                    $sql .= '0=1';
                                }
                            } else if ($value[0] === 'not in' && $value[2] === null) {
                                $sql .= $value[1] . " NOT IN (NULL)";
                            } else if ($value[0] === '=') {
                                if ($value[2] === null) {
                                    $sql .= $value[1] . ' IS NULL';
                                } else {
                                    $sql .= $value[1] . ' = ' . $this->connect->quote($value[2]);
                                }
                            } else if ($value[0] === '!=') {
                                if ($value[2] === null) {
                                    $sql .= $value[1] . ' IS NOT NULL';
                                } else {
                                    $sql .= $value[1] . ' != ' . $this->connect->quote($value[2]);
                                }
                            } else if ($value[0] === 'like') {
                                $sql .= $value[1] . ' LIKE ' . $this->connect->quote('%' . $value[2] . '%');
                            } else if ($value[0] === 'not like') {
                                $sql .= $value[1] . ' NOT LIKE ' . $this->connect->quote('%' . $value[2] . '%');
                            } else if (in_array($value[0], ['>', '<', '<>', '>=', '<=']) === true) {
                                $sql .= $value[1] . ' ' . $value[0] . ' ' . $this->connect->quote($value[2]);
                            } else if ($value[0] === 'between') {
                                $sql .= $value[1] . ' BETWEEN ' . $this->connect->quote($value[2]) . ' AND ' . $this->connect->quote($value[3]);
                            } else if (is_string($value)) {
                                $sql .= $value;
                            }
                        }
                    }

                    $cnt++;
                }
            }
        }

        return $sql;
    }

    /**
     * Returns string with part of where query.
     * @return string
     */
    private function whereSql()
    {
        $where = $this->parseCondition($this->prepareWhere($this->where));
        return $where === '' ? '' : ' WHERE ' . $where;
    }

    /**
     * Returns string with part of having query.
     * @return string
     */
    private function havingSql()
    {
        $having = $this->parseCondition($this->prepareWhere($this->having));
        return $having === '' ? '' : ' HAVING ' . $having;
    }

    /**
     * Sets the SELECT part of the query.
     * @param string|array $select The select part of the query.
     * @return self
     */
    public function select($select)
    {
        $this->select = $select;
        return $this;
    }

    /**
     * Appends a LEFT OUTER JOIN part to the query.
     * @param string $table The table name.
     * @param string $condition The join condition.
     * @return self
     */
    public function leftJoin($table, $condition)
    {
        if ($this->join === null) {
            $this->join = [];
        }

        $this->join[] = ['LEFT JOIN', $table, $condition];

        return $this;
    }

    /**
     * Appends an INNER JOIN part to the query.
     * @param string $table The table name.
     * @param string $condition The join condition.
     * @return self
     */
    public function innerJoin($table, $condition)
    {
        if ($this->join === null) {
            $this->join = [];
        }

        $this->join[] = ['INNER JOIN', $table, $condition];

        return $this;
    }

    /**
     * Appends a RIGHT OUTER JOIN part to the query.
     * @param string $table The table name.
     * @param string $condition The join condition.
     * @return self
     */
    public function rightJoin($table, $condition)
    {
        if ($this->join === null) {
            $this->join = [];
        }

        $this->join[] = ['RIGHT JOIN', $table, $condition];

        return $this;
    }

    /**
     * Sets the ORDER BY part of the query.
     * @param string|array $orderBy The order by part of the query.
     * @return self
     */
    public function orderBy($orderBy)
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * Sets the GROUP BY part of the query.
     * @param string|array $groupBy The group by part of the query.
     * @return self
     */
    public function groupBy($groupBy)
    {
        $this->groupBy = $groupBy;
        return $this;
    }

    /**
     * Returns string with part of SELECT query.
     * @return string
     */
    private function selectSql()
    {
        if (is_array($this->select)) {
            $sql_part = '';
            $cnt = 0;
            foreach ($this->select as $key => $value) {
                if ($cnt > 0) {
                    $sql_part .= ', ';
                }
                if (is_numeric($key)) {
                    $sql_part .= $value;
                } else {
                    $sql_part .= $value . ' AS ' . $key;
                }
                $cnt += 1;
            }
            return ' ' . $sql_part;
        }

        return ' ' . $this->select;
    }

    /**
     * Returns string with part of JOIN query.
     * @return string
     */
    private function joinSql()
    {
        if ($this->join === null) {
            return '';
        }

        $sql = '';

        foreach ($this->join as $join) {
            $sql .= $join[0] . ' ' . $join[1]   . ' ON (' . $join[2] . ') ';
        }

        return ' ' . trim($sql);
    }

    /**
     * Returns string with part of ORDER BY query.
     * @return string
     */
    private function orderBySql()
    {
        $sql_part = '';

        if ($this->orderBy !== null) {
            if (is_array($this->orderBy)) {
                $cnt = 0;
                foreach ($this->orderBy as $column => $sort) {
                    if ($cnt > 0) {
                        $sql_part .= ', ';
                    }
                    $sql_part .= $column . ' ' . ($sort === SORT_DESC ? 'DESC' : 'ASC');
                    $cnt++;
                }
            } else if (is_string($this->orderBy) && trim($this->orderBy) !== '') {
                $sql_part = $this->orderBy;
            }
        }

        return trim($sql_part) !== '' ? ' ORDER BY ' . $sql_part : '';
    }

    /**
     * Returns string with part of GROUP BY query.
     * @return string
     */
    private function groupBySql()
    {
        $sql_part = '';

        if ($this->groupBy !== null) {
            if (is_array($this->groupBy)) {
                $sql_part = implode(', ', $this->groupBy);
            } else {
                $sql_part = $this->groupBy;
            }
        }

        return trim($sql_part) !== '' ? ' GROUP BY ' . $sql_part : '';
    }

    /**
     * Returns string with part of LIMIT query.
     * @return string
     */
    private function limitSql()
    {
        $sql_part = '';

        if ($this->offset !== null) {
            $sql_part = ' LIMIT ' . $this->limit . ' OFFSET ' . $this->offset;
        } else if ($this->limit !== null) {
            $sql_part = ' LIMIT ' . $this->limit;
        }

        return $sql_part;
    }

    /**
     * Returns string with part of LIMIT query.
     * @return string
     */
    private function distinctSql()
    {
        if ($this->is_distinct === true) {
            return ' DISTINCT';
        } else {
            return '';
        }
    }

    /**
     * Executes the query and returns the first column of the result.
     * @return array|null
     */
    public function column()
    {
        $rows = $this->all();
        if (count($rows) > 0) {
            $out = [];
            foreach ($rows as $row) {
                $out[] = reset($row);
            }
            return $out;
        }
        return null;
    }

    /**
     * Returns the query result as a scalar value.
     * @return mixed|null
     */
    public function scalar()
    {
        $res = $this->one();
        if ($res !== null && count($res) > 0) {
            foreach ($res as $value) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Return count value.
     * @return int|null
     */
    public function count()
    {
        $this->select('COUNT(*)');
        $res = $this->connect->getRow($this->sql());

        if ($res !== null) {
            if (count($res) > 0) {
                foreach ($res as $value) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Generates the SQL for an UPDATE statement.
     * @param array $data The data to be updated.
     * @return string
     */
    public function updateSql($data)
    {
        $pairs = '';

        $cnt = 0;
        foreach ($data as $key => $value) {
            $pairs .= ($cnt !== 0 ? ', ' : '');

            if (in_array($key, $this->jsonFields, true)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if (is_integer($value) || is_float($value)) {
                $pairs .= $key . ' = ' . $value;
            } else if ($value === null) {
                $pairs .= $key . ' = NULL';
            } else if ($value === true || $value === false) {
                $pairs .= $key . ' = ' . intval($value);
            } else {
                $pairs .= $key . ' = ' . $this->connect->quote($value);
            }
            $cnt++;
        }


        return 'UPDATE ' . $this->table . ' SET ' . $pairs . $this->whereSql();
    }

    /**
     * Update records in database.
     * @param array $data The data to be updated.
     * @return mixed
     */
    public function update($data)
    {
        return $this->connect->query($this->updateSql($data));
    }

    /**
     * Generates the SQL for an INSERT statement.
     * @param array $data The data to be inserted.
     * @return string
     */
    public function insertSql($data)
    {
        $columns = '';
        $values = '';

        $cnt = 0;
        foreach ($data as $key => $value) {
            $values .= ($cnt !== 0 ? ', ' : '');
            $columns .= ($cnt !== 0 ? ', ' : '');
            $columns .= $key;

            if (in_array($key, $this->jsonFields, true)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if (is_integer($value) || is_float($value)) {
                $values .= $value;
            } else if ($value === null) {
                $values .= 'NULL';
            } else if ($value === true || $value === false) {
                $values .= intval($value);
            } else {
                $values .= $this->connect->quote($value);
            }
            $cnt++;
        }

        return 'INSERT INTO ' . $this->table . ' (' . $columns . ') VALUES (' . $values . ')';
    }

    /**
     * Inserts a new record into the database.
     * @param array $data The data to be inserted.
     * @return mixed
     */
    public function insert($data)
    {
        $this->connect->query($this->insertSql($data));
        return $this->connect->getLastID();
    }

    /**
     * Generates the SQL for a DELETE statement.
     * @return string
     */
    public function deleteSql()
    {
        return 'DELETE FROM ' . $this->table . $this->whereSql();
    }

    /**
     * Deletes records from the database.
     * @return mixed
     */
    public function delete()
    {
        return $this->connect->query($this->deleteSql());
    }

    /**
     * Executes a raw SQL query.
     * @param string $sql The SQL query to execute.
     * @return mixed
     */
    public function execute($sql)
    {
        return $this->connect->execute($sql);
    }

    /**
     * Quotes a string for use in a query.
     * @param string $value The value to quote.
     * @return string
     */
    public function quote($value)
    {
        return $this->connect->quote($value);
    }

    /**
     * Specifies which fields should be treated as JSON.
     * @param array $fields The fields to be treated as JSON.
     * @return self
     */
    public function asJson(array $fields)
    {
        $this->jsonFields = $fields;
        return $this;
    }

    /**
     * Decodes JSON fields in a row.
     * @param array|null $row The row to decode.
     * @return array|null
     */
    private function decodeJsonFields(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        foreach ($this->jsonFields as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $decoded = json_decode($row[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$field] = $decoded;
                }
            }
        }
        return $row;
    }
 }