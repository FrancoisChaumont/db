<?php

namespace FC\Database;

/**
 * NOTE: select, update, delete and execScript, although identical have been split to give room for distintive behavior if needed
 */

/**
 * Database management library
 * 
 * @method void emptyParams() Reset binding parameters
 * @method void addParamToBind() Add parameters to bind to the query
 * @method string listHandledDbms() List all DBMS handled by this library
 * @method void disconnect() Disconnect from database and mark object as disconnected
 * @method void connect() Attempt a connection to the database
 * @method mixed getNextRow() Get the next row of a result set for a select query
 * @method bool select() Execute a select query
 * @method bool insert() Execute an insert query
 * @method bool update() Execute an update query
 * @method bool delete() Execute a delete query
 * @method bool execScript() Execute a SQL script
 */
class Db
{
    // DBMS handled
    const MYSQL = 'mysql';
    const MARIADB = 'mysql';
    const POSTGRESQL = 'pgsql';

    const DBMSs = [
        self::MYSQL,
        self::MARIADB,
        self::POSTGRESQL
    ];

    /** @var string */
    private $dbms;
    /** @var string */
    private $dbname;
    /** @var string */
    private $charset;
    /** @var string */
    private $host;
    /** @var int */
    private $port;
    /** @var string */
    private $login;
    /** @var string */
    private $password;
    /** @var \PDO */
    private $connection;
    /** @var bool */
    private $connected;
    /** @var array */
    private $parameters;
    /** @var \PDOstatement */
    private $statement;
    /** @var string */
    private $errMessage = '';
    /** @var string */
    private $lastId;
    /** @var bool */
    private $debug;
    /** @var string */
    private $queryDump = '';
    
    public function setDbms(string $p): void { $this->dbms = $p; }
    public function setDbname(string $p): void { $this->dbname = $p; }
    public function setCharset(string $p): void { $this->charset = $p; }
    public function setHost(string $p): void { $this->host = $p; }
    public function setPort(int $p): void { $this->port = $p; }
    public function setLogin(string $p): void { $this->login = $p; }
    public function setPassword(string $p): void { $this->password = $p; }
    public function isConnected(): bool { return $this->connected; }
    public function getErrMessage(): string { return $this->errMessage; }
    public function getQueryDump(): string { return $this->queryDump; }
    public function getLastId(): string { return $this->lastId; }

    /**
     * Reset binding parameters
     *
     * @return void
     */
    public function emptyParams(): void
    {
        $this->parameters = array();
    }
    
    /**
     * Add parameters to bind to the query
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
     * @param int $port port on the host
     * @param string $charset connection character set (only used with MySQL)
     * @param string $debug allow to dump a query with bind parameters for debug purpose
     */
    function __construct(string $dbms, string $dbName, string $host, string $login, string $password, int $port = 0, string $charset = '', bool $debug = false)
    {
        if (!in_array($dbms, self::DBMSs)) {
            $msg = sprintf("Cannot use DBMS '%s'. Handled DBMSs are: ", $dbms, implode(', ', self::DBMSs));
            throw new \Exception($msg);
        }

        $this->dbms = $dbms;
        $this->dbname = $dbName;
        $this->host = $host;
        $this->port = $port;
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
     * Disconnect from database and mark object as disconnected
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

        $charset = in_array($this->dbms, [self::MYSQL, self::MARIADB]) && $this->charset != '' ? "charset={$this->charset}" : '';
        $port = $this->port > 0 ? "port={$this->port}" : '';

        try {
            $connString = sprintf('%s:dbname=%s;host=%s;%s;%s', $this->dbms, $this->dbname, $this->host, $port, $charset);
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
     * Execute a query and catch error message (if any)
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
     * @return void
     */
    private function prepareQuery(string $query): void
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
     * @return mixed|false false on failure
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
     * Execute a SQL script
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

