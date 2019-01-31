<?php

namespace FC\Database;

require __DIR__ . '/../vendor/autoload.php';

$dbms = 'mysql';
$dbname = 'tests_dbclass';
$host = 'localhost';
$login = 'udbclass';
$password = 'udbclasspassw';

// instantiation of the class and attempted connection to the database
echo '### Connection to the database: ';
$db = new Db($dbms, $dbname, $host, $login, $password, 'utf8mb4', true);

if ($db->isConnected()) { 
    echo "CONNECTED\n"; 
    print_r($db);
}
else {
    echo "NOT CONNECTED\n"; 
    echo $db->getErrMessage();
}

if ($db->isConnected()) {

// select with syntax error
    echo '### Select query with syntax error <br>';
    $db->emptyParams();
    $selectQuery = "select * from users_error";
    $success = $db->select($selectQuery);
    if (!$success) { echo 'ERROR: '.$db->getErrMessage().'<br><br>'; }
    else { 
        while ($row = $db->getNextRow()) {
            print_r($row);
        }
    }

// select with no parameters and order by
    echo '### Select query with no parameters and order by <br>';
    $db->emptyParams();
    $selectQuery = "select * from users order by id desc";
    $db->select($selectQuery);
    while ($row = $db->getNextRow()) {
        echo 'id: '.$row['id'].' / firstname: '.$row['firstname'].' / lastname: '.$row['lastname'].'<br>';
        print_r($row);
    }

// select with parameters
    echo '### Select query with parameters <br>';
    $db->emptyParams();
    $selectQuery = "select * from users where id = :id or id = :id5";
    $db->addParamToBind('id', 1);
    $db->addParamToBind('id5', 5);
    $db->select($selectQuery);
    while ($row = $db->getNextRow()) {
        print_r($row);
    }
    
// select with group by
    echo '### Select query with group by <br>';
    $db->emptyParams();
    $selectQuery = "select level, count(*) totalPerLevel from users group by level";
    $db->select($selectQuery);
    while ($row = $db->getNextRow()) {
        print_r($row);
    }

// insert attempt with duplicated primary key
    echo '### Insert query with duplicated primary key <br>';
    $db->emptyParams();
    $insertQuery = "insert into users (id, firstname, lastname) values (:id, :firstname, :lastname)";
    $db->addParamToBind('id', 1);
    $db->addParamToBind('firstname', 'Michael');
    $db->addParamToBind('lastname', 'Jackson');
    $success = $db->insert($insertQuery);

    if (!$success) { echo 'ERROR: '.$db->getErrMessage().'<br><br>'; }

// insert user Michael Jackson
    echo '### Insert query with parameters <br>';
    $db->emptyParams();
    $insertQuery = "insert into users (firstname, lastname) values (:firstname, :lastname)";
    $db->addParamToBind('firstname', 'Michael');
    $db->addParamToBind('lastname', 'Jackson');
    $db->insert($insertQuery);

    $lastId = $db->getLastId();
    echo 'Last inserted id = '.$lastId;
    $db->emptyParams();
    $selectQuery = "select * from users where id = :id";
    $db->addParamToBind('id', $lastId);
    if ($db->select($selectQuery)) {
        if ($row = $db->getNextRow()) {
            print_r($row);
        }
    }

    // display query and its parameters
    echo $db->getQueryDump();

// update last inserted record
    echo "### Update the last inserted record (id=$lastId) modifying lastname 'Jackson' into 'Jordan' <br>";
    $db->emptyParams();
    $updateQuery = "update users set lastname = :lastname where id = :id";
    $db->addParamToBind('id', $lastId);
    $db->addParamToBind('lastname', 'Jordan');
    $db->update($updateQuery);

    $db->emptyParams();
    $selectQuery = "select * from users where id = :id";
    $db->addParamToBind('id', $lastId);
    if ($db->select($selectQuery)) {
        if ($row = $db->getNextRow()) {
            print_r($row);
        }
    }

// delete last inserted record
    echo "### Delete the last inserted record (id=$lastId) <br>";
    $db->emptyParams();
    $deleteQuery = "delete from users where id = :id";
    $db->addParamToBind('id', $lastId);
    $deleted = $db->delete($deleteQuery);
    if ($deleted) { echo "Record deleted!<br>"; }

    $db->emptyParams();
    $selectQuery = "select * from users where id = :id";
    $db->addParamToBind('id', $lastId);
    if ($db->select($selectQuery)) {
        if ($row = $db->getNextRow()) {
            print_r($row);
        }
        else { echo "Record id $lastId NOT found! <br><br>"; }
    }
}


