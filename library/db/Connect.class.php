<?php
/*******************************************************************************
 * Copyright 2009-2016 FeG Immanuel Berlin. All Rights Reserved.
 *******************************************************************************
 * PHP Version 5
 * @category General
 * @package  Database
 * @version  2015-4-24
 * Library Version: 2016-3-30
 *
 * Created by PhpStorm.
 * User: William
 * Date: 06/08/16
 * Time: 11:12
 */

include_once(dirname(__FILE__)."/config.php");

/**
 * Connect - base class for database connection to DB Server
 */
class Connect {

    /**
     * DB Server
     */

    const DBSERVER = "DBSERVER";

    /** @var PDO database connection */
    private $db, $server;
    private $batchExecuteTotal = 25;
    private $batchExecuteQuery = array();
    private $saveMode = true;

    const QUERY_TYPE_SELECT = 1;
    const QUERY_TYPE_EXECUTE = 2;

    /**
     * Construct new model class
     *
     * @param string $server (optional)
     * @throws Exception
     */
    function __construct($server = null)
    {
        $this->server = $server;

    }

    /**
     * get PDO Connection Object
     *
     * @return PDO Connection as object
     */
    public function PDO(){
        return $this->getDb();
    }

    /**
     * set PDO Connection Object
     *
     * @return null
     */
    public function setPDO($db){
        return $this->db = $db;
    }

    /**
     * connect to database and/or return connection
     *
     * @return PDO
     * @throws Exception
     */
    private function getDb(){
        if($this->db === null){
            // prepare information about requested connection
            $dbInfo = getConnectionInfo();
            $host = "";
            $user = "";
            $password = "";
            $name = "";
            $server = $this->server;
            if(!is_null($server)){
                if(isset($dbInfo[$server])){
                    $host = $dbInfo[$server]["host"];
                    $user = $dbInfo[$server]["user"];
                    $password = $dbInfo[$server]["password"];
                    $name = $dbInfo[$server]["name"];
                }
            }else{
                throw new Exception ("Unable to construct from provided data. Server parameter could not be null.");
            }
            // create connection
            try{
                $this->db = new PDO('mysql:host='.$host.';dbname='.$name, $user, $password);
                $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->db->query("SET NAMES UTF8;");
            }catch(PDOException $e){
                $this->db = null;
                throw new Exception ("Unable to connect to server.");
            }
        }
        return $this->db;
    }

    /**
     * get all data from query
     *
     * @param string $query SQL select query
     * @return array of row as object
     * @throws Exception
     */
    public function select($query){
        $rows = array();
        try{
            $stmt = $this->getDb()->prepare($this->checkQuery($query));
            $stmt->execute();
            while($row = $stmt->fetchObject()){
                $rows[] = $row;
            }
            $stmt->closeCursor();
        }catch(PDOException $e) {
            throw new Exception ("Error Query." . $e->getMessage());
        }
        return $rows;
    }

    /**
     * get all data from query
     *
     * @param string $query SQL select query
     * @param string $key index name
     * @return array
     * @throws Exception
     */
    public function selectKey($query,$key){

        $rows = array();
        try{
            $stmt = $this->getDb()->prepare($this->checkQuery($query));
            $stmt->execute();
            while($row = $stmt->fetchObject()){
                if(isset($row->$key)){
                    $rows[$row->$key] = $row;
                }else{
                    throw new Exception ("Error invalid key found.");
                }
            }
            $stmt->closeCursor();
        }catch(PDOException $e) {
            throw new Exception ("Error Query." . $e->getMessage());
        }
        return $rows;
    }

    /**
     * get all data from query
     *
     * @param string $store_procedure SQL procedure query
     * @return array
     * @throws Exception
     */
    public function call($store_procedure){
        $rows = array();
        try{

            $stmt = $this->getDb()->query("call ".$store_procedure.";");
            $result = $stmt->fetchAll();
            foreach($result as $row){
                $rows[] = $row;
            }
            $stmt->closeCursor();
        }catch(PDOException $e) {
            throw new Exception ("Error Query." . $e->getMessage());
        }
        return $rows;
    }

    /**
     * get all data from query in an array
     *
     * @param string $query SQL select query
     * @param array $options array of options
     * @return array
     * @throws Exception
     */
    public function selectArray($query, $options=[]){
        $isIndex = isset($options["index"]);
        $rows = array();
        try{
            $stmt = $this->getDb()->prepare($this->checkQuery($query));
            $stmt->execute();
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                if(!$isIndex) $rows[] = $row;
                else $rows[$row[$options["index"]]] = $row;
            }
        }catch(PDOException $e) {
            throw new Exception ("Error Query." . $e->getMessage());
        }
        return $rows;
    }

    /**
     * get all data from query by custom mapping
     *
     * @param string $query SQL select query
     * @param callable $callback returns true to continue, false to quit loop
     * @param mixed $value (optional) variable to be passed down to mapping function
     * @return array feteched rows
     * @throws Exception
     */
    public function selectMap($query, $callback, $value=null){
        $fetch = function($row, &$rows){$rows[] = $row;};
        $rows = [];
        $index = 0;
        $run = true;
        try{
            $stmt = $this->getDb()->prepare($this->checkQuery($query));
            $stmt->execute();
            $callback = is_callable($callback) ? $callback : $fetch;
            while($run !== false && $row = $stmt->fetch(PDO::FETCH_ASSOC))
                $run = $callback($row, $rows, $index++, $value);
            $stmt->closeCursor();
        }catch(PDOException $e) {
            throw new Exception ("Error Query." . $e->getMessage());
        }
        return $rows;
    }

    /**
     * get first value from query
     *
     * @param string $query SQL select query
     * @return mixed|false
     * @throws Exception
     */
    public function selectValue($query){
        try{
            $stmt = $this->getDb()->prepare($this->checkQuery($query));
            $stmt->execute();
            if($row = $stmt->fetch(PDO::FETCH_NUM)){
                if(!empty($row)) return $row[0];
            }
            $stmt->closeCursor();
        }catch(PDOException $e) {
            throw new Exception ("Error Query." . $e->getMessage());
        }
        return false;
    }

    /**
     * get first item from query
     *
     * @param string $query SQL select query
     * @return mixed|false
     * @throws Exception
     */
    public function selectItem($query){
        try{
            $stmt = $this->getDb()->prepare($this->checkQuery($query));
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            return $row;
        }catch(PDOException $e) {
            throw new Exception ("Error Query." . $e->getMessage());
        }
    }

    /**
     * get list of first value in each row
     *
     * @param string $query SQL select query
     * @return array
     * @throws Exception
     */
    public function selectList($query){
        $rows = [];
        try{
            $stmt = $this->getDb()->prepare($this->checkQuery($query));
            $stmt->execute();
            while($row = $stmt->fetch(PDO::FETCH_NUM)){
                if(!empty($row)) $rows[] = $row[0];
            }
            $stmt->closeCursor();
        }catch(PDOException $e) {
            throw new Exception ("Error Query." . $e->getMessage());
        }
        return $rows;
    }

    /**
     * execute query
     *
     * @param string $query SQL INSERT UPDATE DELETE query
     * @return bool
     * @throws Exception
     */
    public function execute($query){
        try{
            $stmt = $this->getDb()->prepare($this->checkQuery($query,self::QUERY_TYPE_EXECUTE));
            return $stmt->execute();
        }catch(PDOException $e) {
            throw new Exception ("Error Query." . $e->getMessage());
        }
    }

    /**
     * intialize transaction and commit after query has been executed successfully
     * note: MyISAM does not support transactions, use InnoDB instead
     * @param string $query
     * @return mixed
     * @throws Exception
     */
    public function executeTransaction($query){
        $isTransaction = false;
        $db = null;
        try{
            $db = $this->getDb();
            $isTransaction = $db->beginTransaction();
            if($db->exec($this->checkQuery($query,self::QUERY_TYPE_EXECUTE)) === false){
                throw new Exception("Transaction Error:\n".print_r($db->errorInfo(), true));
            }
            return $db->commit();
        }catch(Exception $e) {
            if($isTransaction && $db !== null){
                $db->rollBack();
            }
            throw new Exception ("Error Query." . $e->getMessage());
        }
    }

    /**
     * intialize transaction and rollback changes after query has been executed successfully
     * note: MyISAM does not support transactions, use InnoDB instead
     * @param string $query
     * @return bool
     * @throws Exception
     */
    public function executeRollback($query){
        try{
            $db = $this->getDb();
            $db->beginTransaction();
            if($db->exec($this->checkQuery($query,self::QUERY_TYPE_EXECUTE)) === false){
                throw new Exception("Transaction Error:\n".print_r($db->errorInfo(), true));
            }
            return $db->rollBack();
        }catch(Exception $e) {
            throw new Exception ("Error Query." . $e->getMessage());
        }
    }

    /**
     * execute multiple transactions and get all data from select statements
     * note: MyISAM does not support transactions, use InnoDB instead
     * @param string[] $queries
     * @return array
     * @throws Exception
     */
    public function selectMultipleTransactionArray($queries){
        $rows = [];
        $isTransaction = false;
        $db = null;
        try{
            $db = $this->getDb();
            $isTransaction = $db->beginTransaction();
            foreach($queries as $query){
                $stmt = $db->query($this->checkQuery($query));
                // do not fetch from other statements than select
                if(strtoupper(substr($query, 0, 6)) === 'SELECT'){
                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                        $rows[] = $row;
                    }
                }
                $stmt->closeCursor();
            }
            $db->commit();
        }catch(Exception $e) {
            if($isTransaction && $db !== null){
                $db->rollBack();
            }
            throw new Exception ("Error Query." . $e->getMessage());
        }
        return $rows;
    }

    /**
     * insert and returns the ID of the last inserted row or sequence value
     *
     * @param string $query SQL INSERT query
     * @return string last inserted Id
     * @throws Exception
     */
    public function insertGetLastInsertId($query){
        try{
            $stmt = $this->getDb()->prepare($this->checkQuery($query,self::QUERY_TYPE_EXECUTE));
            $stmt->execute();
            return $this->getDb()->lastInsertId();
        }catch(PDOException $e) {
            throw new Exception ("Error Query." . $e->getMessage());
        }
    }

    /**
     * set total for batchExecute
     * total is how many queries should be executed
     *
     * @param integer $total
     */
    public function setBatchExecuteTotal($total){
        $this->batchExecuteTotal = $total;
    }

    /**
     * add query to be executed in batch if there are as many queries as batchExecuteTotal.
     * queries will be deleted after execution.
     *
     * @param string $query SQL INSERT UPDATE DELETE query
     * @return array of message error
     */
    public function batchExecute($query){
        if($this->endsWith(trim($query),";")===false){
            $query .= ";";
        }
        $this->batchExecuteQuery[] = $this->checkQuery($query,self::QUERY_TYPE_EXECUTE);
        $errors = null;
        if(count($this->batchExecuteQuery) >= $this->batchExecuteTotal)
        {
            $allQuery= implode($this->batchExecuteQuery);
            try{
                $stmt = $this->getDb()->prepare($allQuery);
                $stmt->execute();
            }catch(PDOException $e) {
                $errors = $this->fallBackQuery($this->batchExecuteQuery);
            }
            $this->batchExecuteQuery = array();
        }
        return $errors;
    }

    /**
     * execute all queries left from batchExecute.
     *
     * @return array of message error
     */
    public  function flushBatchExecute(){
        $errors = null;
        if(!empty($this->batchExecuteQuery)){
            $allQuery = implode( $this->batchExecuteQuery);
            try{
                $stmt = $this->getDb()->prepare($allQuery);
                $stmt->execute();
            }catch(PDOException $e) {
                $errors = $this->fallBackQuery($this->batchExecuteQuery);
            }
            $this->batchExecuteQuery = array();
        }
        return $errors;
    }

    /**
     * close active connection
     */
    public function closeConnection(){
        $this->db = null;
    }

    /**
     * execute all queries one by one if batchExecute fails
     *
     * @param array $queryArray array of string of SQL INSERT UPDATE DELETE query
     * @return array|null array of errors or NULL
     */
    private function fallBackQuery($queryArray){
        $errors = array();
        foreach ($queryArray as $query) {
            try{
                $stmt = $this->getDb()->prepare($query);
                $stmt->execute();
            }catch (PDOException $e) {
                $error = array();
                $error["query"] = $query;
                $error["errorMessage"] = $e->getMessage();
                $errors[] = $error;
            }
        }
        if(count($errors)>0)
        {
            return $errors;
        }
        return null;
    }

    /**
     * check wether the haystack with needle ends
     *
     * @param string $haystack source text
     * @param string $needle end with
     * @return boolean
     */
    private function endsWith($haystack, $needle) {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
    }

    /**
     * quote given string using current database connection
     *
     * @param string $str
     * @return string
     * @throws Exception
     */
    public function stringQuote($str)
    {
        return $this->getDb()->quote($str);
    }

    /**
     * quote given string using current database connection
     *
     * @param string $str
     * @return string
     * @throws Exception
     */
    public function quote($str)
    {
        return $this->getDb()->quote($str);
    }

    /*
     * convert object in array
     *
     * @param mixed $obj object to be converted
     * @return string|array returns a string on error
     */
    public function convertObjectToArray($obj){

        try {
            return json_decode(json_encode($obj),true);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function setSaveMode($saveMode=true){
        $this->saveMode = $saveMode;
    }
    /*
     * check query from dangerous SQL words
     */
    private function checkQuery($query,$type=self::QUERY_TYPE_SELECT){
        if($this->saveMode){
            $dangerousWords = array();
            if($type==self::QUERY_TYPE_SELECT){
                $dangerousWords = array("drop ","delete ","alter ","insert into");
            }elseif ($type==self::QUERY_TYPE_EXECUTE){
                $dangerousWords = array("drop ","delete ","alter ");
            }else{
                throw new Exception ("Invalid query type");
            }
            foreach ($dangerousWords as $word){
                $pos = strpos($query, $word);
                if ($pos !== false) {
                    throw new Exception ("Query contains SQL Command. Use save mode to force it. ");
                }
            }
        }
        return $query;
    }

    /*
    * DEPRECATED: Typo. Don't use it anymore
    */
    public function convertObejctToArray($array){

        try {
            return json_decode(json_encode($array),true);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }


    }

}