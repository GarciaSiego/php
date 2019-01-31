<?php

namespace slim3;

/**
 * Class SQL
 *
 */
class SQL
{
    /**
     * @var string Table prefix
     */
    public $prefix = "";
    /**
     * @var \PDO Database Handler
     */
    private $DBH;
    /**
     * @var int Count the last effected rows
     */
    private $rowCount;

    /**
     * SQL constructor.
     *
     * @param bool $postcode
     * @throws \Exception
     */
    public function __construct()
    {
        try {
            $this->DBH = new \PDO("mysql:host=" . host . ";dbname=" . core_dbb . ";charset=utf8", username, password);
            $this->DBH->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $ex) {
            throw new \Exception($ex);
        }
    }

    /**
     * @param $password string password
     * @return string encrypted password
     */
    function encrypt($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }


    /**
     * $sql -> update("garcia_test_db", ["f1" => 1,"f2" => 2,"f3" => 3 ], ["recno" => 1])
     *
     * @param       $table
     * @param array $data
     * @param array $where
     * @return \PDOStatement
     * @throws \Exception
     */
    public function update($table, array $data, array $where)
    {
        try {
            $sqlStr = sprintf('UPDATE %s SET ',
                $this->sanitizeString($table));

            $data2 = array();
            foreach ($data as $key => $item) {
                $data2[] = sprintf('%s = :%s', $key, $key);
            }
            $sqlStr .= sprintf(' %s ',
                implode(', ', $data2));


            $sqlStr .= ' WHERE ';
            $counter = 0;
            foreach ($where as $key => $item) {
                if ($counter == 0) {
                    $sqlStr .= sprintf('%s = :%s', $key, $key);
                    $counter++;
                } else {
                    $sqlStr .= sprintf(' AND %s = :%s', $key, $key);
                }

            }

            $STH = $this->DBH->prepare($sqlStr);

            $data = $this->prepareData($data);
            $where = $this->prepareData($where);
            $STH->execute(array_merge($data, $where));
            $this->rowCount = $STH->rowCount();
            return $STH;

        } catch (\PDOException $ex) {
            throw new \PDOException($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param string $string Filtered Data table names from SQL escapes
     * @return mixed
     */
    private function sanitizeString($string)
    {
        return preg_replace("/[^a-zA-Z0-9_\-\.\`]+/", "", $string);
    }

    /**
     * Addes : to the key value
     *
     * @param array $data
     * @return mixed
     */
    private function prepareData(array $data)
    {
        $newArray = array();
        foreach ($data as $key => $value) {
            $newArray[":" . $key] = $value;
        }
        return $newArray;
    }

    /**
     * Can handle any sql string.
     *
     * EXAMPLE $sql -> custom('UPDATE `begform_db` SET B_TOEL= :B_TOEL',['B_TOEL' => $toel]
     * EXAMPLE $sql -> insert2('insert `begform_db` SET B_TOEL= :B_TOEL',['B_TOEL' => $toel]
     *
     * @param       $statement
     * @param array $values
     * @return \PDOStatement
     * @throws \Exception
     */
    public function custom($statement, array $values = [])
    {
        try {
            $statement = trim(preg_replace('/\s\s+/', ' ', $statement));
            $STH = $this->DBH->prepare($statement);
            $data = $this->prepareData($values);
            $STH->execute($data);
            $this->rowCount = $STH->rowCount();
            return $STH;
        } catch (\PDOException $ex) {
            throw new \PDOException($ex->getMessage(), $ex->getCode());
        }
    }


    /**
     *$sql -> insert("garcia_test_db", ["f1" => 1,"f2" => 2,"f3" => 3 ])
     *
     * @param       $table
     * @param array $data
     * @param bool  $checkNullValue if true check columm fields if null is allowed,
     * where it isn't allowed we will check the corresponding $data if its null.
     * If its null we will replace it with empty string
     * @return bool
     * @throws \Exception
     */
    public function insert($table, array $data, $checkNullValue = false)
    {
        try {
            if ($checkNullValue) {
                $data = $this->checkNullableFields($table, $data);
            }

            $data2 = array();
            foreach ($data as $key => $item) {
                $data2[] = ':' . $key;
            }

            $sqlStr = sprintf('INSERT INTO %s ( %s ) VALUE ( %s )',
                $this->sanitizeString($table)
                , implode(', ', array_keys($data))
                , implode(', ', $data2));


            $STH = $this->DBH->prepare($sqlStr);

            $data = $this->prepareData($data);
            return $STH->execute($data);

        } catch (\PDOException $ex) {
            throw new \PDOException($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * Checks all colums with the corresponding data fields and check if null is allowed.
     * If null isnt allowed and the data is null, it will be replaced with empty
     *
     * @param       $table
     * @param array $data
     * @return array $data with no NULLs
     * @throws \Exception
     */
    private function checkNullableFields($table, array $data)
    {
        try {
            $table = str_replace("`", "", $table);
            $sqlResult = $this->select("SELECT COLUMN_NAME, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$table'");

            foreach ($sqlResult as $row) {
                if ($row['IS_NULLABLE'] == "NO") {
                    foreach ($data as $key => &$value) {
                        if ($row['COLUMN_NAME'] == $key) {
                            if ($value == NULL) {
                                $value = "";
                            }
                        }
                    }
                }
            }

            return $data;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     *PDO::FETCH_COLUMN: Returns the indicated 0-indexed column.
     *PDO::FETCH_CLASS: Returns instances of the specified class, mapping the columns of each row to named properties in the class.
     *PDO::FETCH_FUNC: Returns the results of calling the specified function, using each row's columns as parameters in the call
     * EXAMPLE $sql -> select('SELECT * FROM `garcia_test_db` WHERE `f1` = ?', ["1"]);
     *
     * @param       $statement
     * @param array $values
     * @param int   $fetch_style PDO::FETCH_COLUMN, PDO::FETCH_CLASS , PDO::FETCH_FUNC
     * @return array
     * @throws \Exception
     */
    public function select($statement, array $values = [], $fetch_style = \PDO::FETCH_ASSOC)
    {
        try {
            $STH = $this->DBH->prepare($statement);
            $STH->execute(array_values($values));
            $this->rowCount = $STH->rowCount();
            return $STH->fetchAll($fetch_style);
        } catch (\PDOException $ex) {
            throw new \PDOException($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     *PDO::FETCH_COLUMN: Returns the indicated 0-indexed column.
     *PDO::FETCH_CLASS: Returns instances of the specified class, mapping the columns of each row to named properties in the class.
     *PDO::FETCH_FUNC: Returns the results of calling the specified function, using each row's columns as parameters in the call
     * EXAMPLE $sql -> select2('SELECT * FROM `garcia_test_db` WHERE `f1` = :f1', ["f1" => 1])
     *
     * @param       $statement
     * @param array $values
     * @param int   $fetch_style
     * @return array
     * @throws \Exception
     */
    public function select2($statement, array $values = [], $fetch_style = \PDO::FETCH_ASSOC)
    {
        try {
            $STH = $this->DBH->prepare($statement);
            $data = $this->prepareData($values);
            $STH->execute($data);
            $this->rowCount = $STH->rowCount();
            return $STH->fetchAll($fetch_style);
        } catch (\PDOException $ex) {
            throw new \PDOException($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * EXAMPLE $sql->delete('DELETE FROM `garcia_test_db` WHERE `f1` = ?', ["1"]);
     *
     * @param string $statement
     * @param array  $values
     * @return \PDOStatement
     * @throws \Exception
     */
    public function delete($statement, array $values = [])
    {
        try {
            $STH = $this->DBH->prepare($statement);
            $STH->execute(array_values($values));
            $this->rowCount = $STH->rowCount();
            return $STH;
        } catch (\PDOException $ex) {
            throw new \PDOException($ex->getMessage(), $ex->getCode());
        }
    }

    public function lastInsertId()
    {
        return $this->DBH->lastInsertId();
    }

    /**
     * returns the row count of the last executed query
     *
     * @return int the row count of the last executed query
     */
    public function rowCount()
    {
        return $this->rowCount;
    }

    public function disconnect()
    {
        $this->DBH = null;
    }

    public function connection()
    {
        return $this->DBH;
    }

    /**
     * For debug use ony.
     *
     * @param $sql
     * @param $placeholders
     * @return mixed
     */
    private function pdo_sql_debug($sql, $placeholders)
    {
        foreach ($placeholders as $k => $v) {
            $sql = preg_replace('/:' . $k . '/', "'" . $v . "'", $sql);
        }
        return $sql;
    }
}