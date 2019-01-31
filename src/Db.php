<?php

namespace FC\Database;

/**
 * NOTE: select, update, delete and execScript, although identical have been split to give room for distintive behavior if needed
 */

/**
 * Libray to communicate with databases
 */
class Db 
{
    const MYSQL = 'mysql';
    const POSTGRESQL = 'pgsql';

    const DBMSs = [
        self::MYSQL,
        self::POSTGRESQL
    ];

    private $dbms;
    private $dbname;
    private $charset;
    private $host;
    private $login;
    private $password;
    private $connection;
    private $connected;
    private $parameters;
    private $statement;
    private $errMessage;
    private $lastId;
    private $debug;
    private $queryDump = '';
    
/* member functions */
    public function setDbms(string $p) { $this->dbms = $p; }
    public function getDbms() { return $this->dbms; }
    public function setDbname(string $p) { $this->dbname = $p; }
    public function getDbname() { return $this->dbname; }
    public function setCharset(string $p) { $this->charset = $p; }
    public function getCharset() { return $this->charset; }
    public function setHost(string $p) { $this->host = $p; }
    public function getHost() { return $this->host; }
    public function setLogin(string $p) { $this->login = $p; }
    public function getLogin() { return $this->login; }
    public function setPassword(string $p) { $this->password = $p; }
    public function getPassword() { return $this->password; }
    public function isConnected() { return $this->connected; }
    public function getErrMessage() { return $this->errMessage; }
    public function getQueryDump() { return $this->queryDump; }
    public function getLastId() { return $this->lastId; }

    // query parameter array functions
    public function emptyParams() { $this->parameters = array(); }
    
    /**
     * Add parameters to bind to SQL query
     *
     * @param string $name parameter name
     * @param string $value parameter value
     * @return void
     */
    public function addParamToBind(string $name, string $value): void
    {
        $arraySize = sizeof($this->parameters);
        $this->parameters[$arraySize][0] = $name;
        $this->parameters[$arraySize][1] = $value;
    }

    /**
     * List all DBMS handled by this library
     *
     * @return string comma separated list of DBMS available for use
     */
    public static function listHandledDbms(): string
    {
        return implode(', ', self::DBMSs);
    }

    /**
     * Attempt to connect to database after initialization of member variables
     * 
     * @param string $dbms DBMS to use (MySQL, PostgreSQL, ... see class constants)
     * @param string $dbName database name
     * @param string $host hostname
     * @param string $login user login
     * @param string $password user password
     * @param string $charset connection character set (only used with MySQL)
     * @param string $debug allow to dump a query with bind parameters for debug purpose
     */
    function __construct(string $dbms, string $dbName, string $host, string $login, string $password, string $charset = '', bool $debug = false)
    {
        if (!in_array($dbms, self::DBMSs)) {
            $msg = sprintf("Cannot use DBMS '%s'. Handled DBMSs are: ", $dbms, implode(', ', self::DBMSs));
            throw new \Exception($msg);
        }

        $this->dbms = $dbms;
        $this->dbname = $dbName;
        $this->host = $host;
        $this->login = $login;
        $this->password = $password;
        $this->parameters = array();
        $this->charset = $charset;
        $this->debug = $debug;

        $this->connect();
    }

    /**
     * Disconnect connection properly when destructing the object
     */
    function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Disconnect from database and reset flag
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->connection = null;
        $this->connected = false;
    }
    
    /**
     * Attempt a connection to the database
     *
     * @return void
     */
    public function connect(): void
    {
        // reset connection status
        $this->connected = false;
        // reset error message
        $this->errMessage = '';

        $charset = $this->dbms == self::MYSQL && $this->charset != '' ? 'charset=' . $this->charset : '';

        try {
            $connString = sprintf('%s:dbname=%s;host=%s;%s', $this->dbms, $this->dbname, $this->host, $charset);
            $this->connection = new \PDO($connString, $this->login, $this->password);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            //$this->connection->setAttribute(\PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES utf8");
            // [OPTIONAL] stop the execution on error occurring during prepare
            // $this->connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

            $this->connected = true;
        }
        catch (\PDOException $e) {
            $this->errMessage = $e->getMessage();
        }
    }

    /**
     * Execute query and catch error message (if any)
     * Return true on success, false on error
     *
     * @return bool 
     */
    private function executeQuery(): bool
    {
        try {
            $this->statement->execute();
            if ($this->debug) { $this->dumpQuery(); }
            return true;
        }
        catch (\PDOException $e) {
            $this->errMessage = $e->getMessage();
            return false;
        }
    }

    /**
     * Dump the prepared query along with its parameters into the coresponding member variable
     *
     * @return void
     */
    private function dumpQuery()
    {
        ob_start();
        $this->statement->debugDumpParams();
        $this->queryDump = ob_get_clean();

        $this->queryDump .= "\n";

        $i = 0;
        foreach($this->parameters as $param) {
            $this->queryDump .= "[" . ++$i . "] $param[0]: $param[1]\n";
        }
    }

    /**
     * Bind parameters to a query (if any)
     *
     * @return void
     */
    private function bindParameters(): void
    {
        if ($this->parameters != null) { 
            $imax = sizeof($this->parameters);
            for($i = 0; $i < $imax; $i++) {
                $this->statement->bindParam(':'.$this->parameters[$i][0], $this->parameters[$i][1]);
            }
        }
    }

    /**
     * Prepare query before binding parameters (if any)
     *
     * @param string $query
     * @return bool true on success, false on failure
     */
    private function prepareQuery(string $query): bool
    {
        if(!($this->statement = $this->connection->prepare($query))) {
            throw new \Exception("Query failed to prepare!");
        }
    }

    /**
     * Retrieve id of the last inserted row and save it to member lastId
     *
     * @return void
     */
    private function retrieveLastId(): void
    {
        $this->lastId = $this->connection->lastInsertId();
    }

    /**
     * Get the next row of a result set for a select query
     *
     * @param int $fetchStyle default PDO::FETCH_ASSOC (see other options at http://php.net/manual/en/pdostatement.fetch.php)
     * @return array|false either return an array containing the next row or false on failure to do so
     */
    public function getNextRow(int $fetchStyle = \PDO::FETCH_ASSOC)
    {
        return $this->statement->fetch($fetchStyle);
    }

    /**
     * Execute a select query
     *
     * @param string $query
     * @return bool true on success, false on failure
     */
    public function select(string $query): bool
    {
        // reset the result set
        $this->statement = false;
        // reset error message
        $this->errMessage = '';
        // prepare query
        $this->prepareQuery($query);
        // bind parameters
        $this->bindParameters();

        // execute query
        $executed = $this->executeQuery();
        return $executed;
    }

    /**
     * Execute an insert query
     *
     * @param string $query
     * @return bool true on success, false on failure
     */
    public function insert(string $query): bool
    {
        // reset the result set
        $this->statement = false;
        // reset error message
        $this->errMessage = '';
        // reset the last inserted id
        $this->lastId = null;
        // prepare query
        $this->prepareQuery($query);
        // bind parameters
        $this->bindParameters();

        // execute query
        $executed = $this->executeQuery();
        if ($executed) { $this->retrieveLastId(); }
        return $executed;
    }

    /**
     * Execute an update query
     *
     * @param string $query
     * @return bool true on success, false on failure
     */
    public function update(string $query): bool
    {
        // reset the result set
        $this->statement = false;
        // reset error message
        $this->errMessage = '';
        // prepare query
        $this->prepareQuery($query);
        // bind parameters
        $this->bindParameters();

        // execute query
        $executed = $this->executeQuery();
        return $executed;
    }

    /**
     * Execute a delete query
     *
     * @param string $query
     * @return bool true on success, false on failure
     */
    public function delete(string $query): bool
    {
        // reset the result set
        $this->statement = false;
        // reset error message
        $this->errMessage = '';
        // prepare query
        $this->prepareQuery($query);
        // bind parameters
        $this->bindParameters();

        // execute query
        $executed = $this->executeQuery();
        return $executed;
    }

    /**
     * Execute a script
     *
     * @param string $script
     * @return bool true on success, false on failure
     */
    public function execScript(string $script): bool
    {
        // reset the result set
        $this->statement = false;
        // reset error message
        $this->errMessage = '';
        // prepare script
        $this->prepareQuery($script);
        // bind parameters
        $this->bindParameters();

        // execute script
        $executed = $this->executeQuery();
        return $executed;
    }
}

