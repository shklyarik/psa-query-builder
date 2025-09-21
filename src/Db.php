<?php

namespace Psa\Qb;

use mysqli;
use Exception;

final class Db
{
    /**
     * @var mysqli|null Database connection instance
     */
    private $connect;

    /**
     * Constructor
     *
     * @param string      $host     Database host
     * @param string      $user     Database username
     * @param string      $password Database password
     * @param string      $database Database name
     * @param int|null    $port     Database port (optional)
     */
    public function __construct(
        private $host,
        private $user,
        private $password,
        private $database,
        private $port = null,
    ) {
    }

    /**
     * Create a query builder starting from a specific table
     *
     * @param string $table Table name
     * @return QueryBuilder Query builder instance
     */
    public function from($table)
    {
        return (new QueryBuilder($this->connect()))->from($table);
    }

    /**
     * Establish a database connection and return a QueryBuilderConnect instance
     *
     * @return QueryBuilderConnect Database query builder connection
     * @throws Exception If the connection fails
     */
    public function connect()
    {
        if ($this->connect === null) {
            $this->connect = new mysqli($this->host, $this->user, $this->password, $this->database, $this->port);
            $this->connect->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
        }

        if ($this->connect->connect_error) {
            throw new Exception("Connection failed: " . $this->connect->connect_error);
        }

        return new QueryBuilderConnect($this->connect);
    }
}
