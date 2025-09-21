<?php

namespace Psa\Qb;

use mysqli_result;
use mysqli;

class QueryBuilderConnect
{
    /**
     * @var mysqli Database connection instance
     */
    private $database;

    /**
     * Constructor
     *
     * @param mysqli $database Database connection instance
     */
    public function __construct($database)
    {
        $this->database = $database;
    }

    /**
     * Execute a query and return all rows as an associative array
     *
     * @param string $sql SQL query string
     * @return array<int, array<string, mixed>> List of rows
     */
    public function getRows($sql)
    {
        $result = $this->database->query($sql);
        $rows = [];

        if ($result && $result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
        }

        return $rows;
    }

    /**
     * Execute a query and return the first column of all rows
     *
     * @param string $sql SQL query string
     * @return array<int, mixed> List of column values
     */
    public function getColumn($sql)
    {
        $result = $this->database->query($sql);
        $column = [];

        if ($result && $result instanceof mysqli_result) {
            while ($row = $result->fetch_row()) {
                $column[] = $row[0];
            }
            $result->free();
        }

        return $column;
    }

    /**
     * Execute a query and return the first row as an associative array
     *
     * @param string $sql SQL query string
     * @return array<string, mixed>|null First row or null if none
     */
    public function getRow($sql)
    {
        $result = $this->database->query($sql);
        if ($result && $result instanceof mysqli_result) {
            $row = $result->fetch_assoc();
            $result->free();
            return $row;
        }
        return null;
    }

    /**
     * Execute a query and return the result
     *
     * @param string $sql SQL query string
     * @return mysqli_result|bool Query result or false on failure
     */
    public function query($sql)
    {
        return $this->database->query($sql);
    }

    /**
     * Escape and quote a value for safe SQL usage
     *
     * @param mixed $value Value to quote
     * @return mixed Quoted and escaped value
     */
    public function quote($value)
    {
        if (is_string($value)) {
            return "'" . $this->database->real_escape_string($value) . "'";
        } else {
            return $value;
        }
    }

    /**
     * Get the last inserted ID
     *
     * @return int Last insert ID
     */
    public function getLastID()
    {
        return $this->database->insert_id;
    }
}
