<?php
/*******************************************************************************
 * Copyright 2009-2015 FeG Immanuel GmBH. All Rights Reserved.
 *******************************************************************************
 * PHP Version 5
 * @category General
 * @package  Database
 * @version  2015-4-24
 * Library Version: 2015-4-24
 *
 * Created by PhpStorm.
 * User: William
 * Date: 07/08/16
 * Time: 11:12
 */

/**
 * TableAdapter - wrapper for standard SQL commands
 */

class TableAdapter{

    const   QUOTE_NONE = 0,
            QUOTE_ALL = 1,
            QUOTE_STRING = 2;

    private $fieldArray;
    private $primaryKeyArray;
    private $quotedDataType = array("char",
        "varchar",
        "tinytext",
        "mediumtext",
        "text",
        "longtext",
        "timestamp",
        "datetime",
        "time",
        "date",
        "enum"
    );

    private $quoteOnString = [
        "enum"
    ];

    /** @var  kkConnect */
    private $db;
    /** @var  string */
    private $schema, $table;

    /**
     * @param kkConnect $kkConnectDB = KKLib Object
     * @param $schema = destination database
     * @param $table = destination table
     * @throws Exception = invalid database or table
     */
    function __construct($kkConnectDB,$schema,$table)
    {
        $this->primaryKeyArray = array();
        $query = "  SELECT COLUMN_NAME,DATA_TYPE,COLUMN_KEY
                    FROM `INFORMATION_SCHEMA`.`COLUMNS`
                    WHERE `TABLE_SCHEMA`='$schema'
                        AND `TABLE_NAME`='$table';";
        try{
            $rows = $kkConnectDB->select($query);
            if(!empty($rows)){
                $quoteOnString = array_flip($this->quoteOnString);
                $quotedDataType = array_flip($this->quotedDataType);
                foreach($rows as $row){
                    $this->fieldArray[$row->COLUMN_NAME] = isset($quotedDataType[$row->DATA_TYPE])
                        ? (!isset($quoteOnString[$row->DATA_TYPE])
                            ? self::QUOTE_ALL
                            : self::QUOTE_STRING )
                        : self::QUOTE_NONE;
                    if($row->COLUMN_KEY=='PRI'){
                        $this->primaryKeyArray[]=$row->COLUMN_NAME;
                    }
                }
                $this->db = $kkConnectDB;
                $this->schema = $schema;
                $this->table = $table;
                $this->db->setBatchExecuteTotal(1000);
            }else{
                throw new Exception('Unknown schema or table');
            }
        }catch (Exception $e){
            throw new Exception ("Invalid schema or table. " . $e->getMessage());
        }

    }

    /**
     * for insert into temporary table. mandatory function call because its columns name are not in INFORMATION_SCHEMA table
     * @param $fieldArray
     */
    public function setFieldArray($fieldArray){
        $this->fieldArray = $fieldArray;
    }

    /**
     * @param $data = can be row from kkConnect or array, as long the column/index-name match the destination table
     * @param $conditions = can be row from kkConnect or array, as long the column/index-name match the destination table
     * @param bool $batchExecute = should the insert statements in batch executed
     * @throws Exception
     */
    public function update($data,$conditions=[] ,$batchExecute=false){
        try{
            $dataContainer = $this->parseQueryData($data);
            if($dataContainer->getTotal() !== 0){
                $conditionsContainer = $this->parseQueryData($conditions);
                $this->executeUpdateSQL($dataContainer, $conditionsContainer, $batchExecute);
            }
        }catch(Exception $e){
            throw new Exception($e);
        }
    }

    /**
     * @param $data = can be row from kkConnect or array, as long the column/index-name match the destination table
     * @param bool $updateDuplicate = UPDATE ON DUPLICATE KEY or IGNORE?
     * @param bool $batchExecute = should the insert statements in batch executed
     * @throws Exception
     */
    public function insert($data,$updateDuplicate=false,$batchExecute=false){
        $this->insertSingle($data,$updateDuplicate,$batchExecute,false);
    }

    /**
     * insert single row, get the last inserted
     * @param $data = can be row from kkConnect or array, as long the column/index-name match the destination table
     * @param bool $updateDuplicate = UPDATE ON DUPLICATE KEY or IGNORE?
     * @return mixed last inserted auto-increased-number primary key
     * @throws Exception
     */
    public function insertGetLastInsertId($data,$updateDuplicate=false){
        return $this->insertSingle($data,$updateDuplicate,false,true);
    }

    /**
     * insert array of rows
     * @param $dataArray = can be array of rows from kkConnect or standard array, as long the column/index-name match the destination table
     * @param bool $updateDuplicate = UPDATE ON DUPLICATE KEY or IGNORE?
     * @return mixed last inserted auto-increased-number primary key
     * @throws Exception
     */
    public function insertMultiple($dataArray,$updateDuplicate=false,$catchError=true){

        $dataField = array();
        $valuesArray = array();
        $counter = 0;
        foreach($dataArray as $data){
            $tmpData = $data;
            $data = $this->toArray($data);

            if(isset($data)){
                $dataField = array_keys($data);
            }else{
                if($catchError){
                    echo "data is null @" .$counter++ ."\n";
                    continue;
                }else{
                    throw new Exception ("Encoding error");
                }
            }
            $foundField = array();
            $foundValue = array();
            $updateQuery = array();

            foreach($this->fieldArray as $field=>$quote){
                if(in_array($field,$dataField)){
                    $foundField[] = "`".$field."`";
                    $currentFoundValue = "";
                    if(isset($data[$field])){
                        if($quote === self::QUOTE_ALL
                        || ($quote === self::QUOTE_STRING && !is_numeric($data[$field]) )){
                            $currentFoundValue = $this->db->stringQuote($data[$field]);
                        }else{
                            if(is_numeric($data[$field])){
                                $currentFoundValue = $data[$field];}
                            elseif(is_bool($data[$field])){
                                $currentFoundValue = $data[$field]==true?1:0;
                            }else{
                                $currentFoundValue = "NULL";
                            }
                        }
                    }else{
                        $currentFoundValue = "NULL";
                    }
                    $foundValue[] = $currentFoundValue;
                    $updateQuery[] = " `".$field."` " . "= VALUES(`" .$field."`) ";
                }
            }
            $valuesArray[] = " (" .implode(",",$foundValue) . ") ";
            $counter++;
        }

        $ignoreQuery = " IGNORE ";
        $duplicateQuery = "";

        if($updateDuplicate){
            $duplicateQuery = " ON DUPLICATE KEY UPDATE " . implode(",",$updateQuery);
            $ignoreQuery = "";
        }


        $query = "INSERT " . $ignoreQuery . " INTO " .$this->schema . "." . $this->table .
            " (" . implode(",",$foundField) . ") VALUES " . implode(",",$valuesArray) .
            $duplicateQuery ;
        try{
            $result = $this->db->execute($query);
        }catch (Exception $e){
            if($catchError){
                echo $query;
                echo "Error. Inserting row one by one\n";
                foreach($dataArray as $data){
                    try{
                        $this->insert($data,true,true);
                    }catch (Exception $ex) {
                        echo "Also error when inserting row one by one. Skip the record.\n";
                        continue;
                    }
                }
                $this->flushBatchExecute();
            }else{
                throw new Exception($e->getMessage());
            }
        }

    }

    /**
     * update array of rows
     * @param $dataArray = can be array of rows from kkConnect or standard array, as long all primary keys are available and the column/index-name match the destination table
     * @param bool $catchError = catch or throw in case of error
     * @return
     * @throws Exception
     */
    public function updateMultiple($dataArray,$catchError=true){
        $dataField = array();
        $counter = 0;
        $queries = array();
        foreach($dataArray as $data){
            $data = $this->toArray($data);

            if(isset($data)){
                $dataField = array_keys($data);
            }else{
                echo "data is null @" .$counter ."\n";
                //throw new Exception ("Encoding error");
                continue;
            }
            $foundField = array();
            $updateQuery = array();
            $primaryKeyQuery = array();

            foreach($this->fieldArray as $field=>$quote){
                if(in_array($field,$dataField)){
                    $foundField[] = "`".$field."`";
                    $currentFoundValue = "";
                    if(isset($data[$field])){
                        if($quote === self::QUOTE_ALL
                            || ($quote === self::QUOTE_STRING && !is_numeric($data[$field]) )){
                            $currentFoundValue = $this->db->stringQuote($data[$field]);
                        }else{
                            if(is_numeric($data[$field])){
                                $currentFoundValue = $data[$field];}
                            elseif(is_bool($data[$field])){
                                $currentFoundValue = $data[$field]==true?1:0;
                            }else{
                                $currentFoundValue = "NULL";
                            }
                        }
                    }else{
                        $currentFoundValue = "NULL";
                    }

                    if(in_array($field,$this->primaryKeyArray)){
                        $primaryKeyQuery[] = " `".$field."` " . "= " .$currentFoundValue." ";
                    }else{
                        $updateQuery[] = " `".$field."` " . "= " .$currentFoundValue." ";
                    }
                }elseif(!in_array($field,$dataField) and in_array($field,$this->primaryKeyArray)){
                    throw new Exception("primary key `".$field."` not found in data. Save mode needs all primary keys");
                }
            }
            $counter++;
            if(count($updateQuery)>0){
                $queries[] = " UPDATE `" .$this->schema . "`.`" . $this->table . "` SET " .implode(" , ",$updateQuery) . " WHERE " .implode(" AND ",$primaryKeyQuery).";";
            }
        }

        foreach ($queries as $query){
                $result = $this->db->batchExecute($query);
                if($catchError and isset($result)){
                    print_r($result);
                }elseif (!$catchError and isset($result)){
                    throw new Exception(print_r($result));
                }
        }
        $result = $this->db->flushBatchExecute();
        if($catchError and isset($result)){
            print_r($result);
        }elseif (!$catchError and isset($result)){
            throw new Exception(print_r($result));
        }
    }


    public function flushBatchExecute(){
        return $this->db->flushBatchExecute();
    }

    /**
     * @param string[] $data can be array of rows from kkConnect or standard array, as long the column/index-name match the destination table
     * @return tableAdapterQueryContainer
     * @throws Exception
     */
    private function parseQueryData($data){
        $data = $this->toArray($data);
        $foundField = [];
        $foundValue = [];
        $updateQuery = [];
        $count = 0;
        if(isset($data)){
            $dataField = array_flip(array_keys($data));

            foreach($this->fieldArray as $field=>$quote){
                if(isset($dataField[$field])){
                    $foundField[] = $field;
                    if(isset($data[$field])){
                        if($quote === self::QUOTE_ALL
                            || ($quote === self::QUOTE_STRING && !is_numeric($data[$field]) )){
                            $currentFoundValue = $this->db->stringQuote($data[$field]);
                        }else{
                            $currentFoundValue = $data[$field];
                        }
                    }else{
                        $currentFoundValue = "NULL";
                    }
                    $foundValue[] = $currentFoundValue;
                    $updateQuery[] = '`' . $field . "`=" . $currentFoundValue;
                    $count++;
                }
            }
        }else{
            //echo "data is null @" .$counter ."\n";
            throw new Exception ("Encoding error");
        }

        return new tableAdapterQueryContainer(
            $foundField, $foundValue, $updateQuery, $count
        );
    }
    
    private function insertSingle($data,$updateDuplicate=false,$batchExecute=false,$returnLastId=false){

        $data = $this->toArray($data);

        if(!isset($data)){
            //echo "data is null @" .$counter ."\n";
            throw new Exception ("Encoding error");
        }

        $foundField = array();
        $foundValue = array();
        $updateQuery = array();

        $dataField = array_flip(array_keys($data));

        foreach($this->fieldArray as $field=>$quote){
            if(isset($dataField[$field])){
                $foundField[] = $field;
                $currentFoundValue = "";
                if(isset($data[$field])){
                    if($quote === self::QUOTE_ALL
                    || ($quote === self::QUOTE_STRING && !is_numeric($data[$field]) )){
                        $currentFoundValue = $this->db->stringQuote($data[$field]);
                    }else{
                        $currentFoundValue = $data[$field];
                    }
                }else{
                    $currentFoundValue = "NULL";
                }
                $foundValue[] = $currentFoundValue;
                $updateQuery[] = '`'.$field . "`=" .$currentFoundValue;
            }
        }
        return $this->executeSQL($foundField,$foundValue,$updateQuery,$updateDuplicate,$batchExecute,$returnLastId);
    }

    private function executeSQL($foundField,$foundValue,$updateQuery,$updateDuplicate,$batchExecute=false,$returnLastId=false){
        if (!empty($foundField)){
            $ignoreQuery = " IGNORE ";
            $duplicateQuery = "";

            if($updateDuplicate){
                $duplicateQuery = " ON DUPLICATE KEY UPDATE " . implode(",",$updateQuery);
                $ignoreQuery = "";
            }

            $insertQuery = "INSERT " . $ignoreQuery . " INTO " .$this->schema . "." . $this->table .
                " (`" . implode("`,`",$foundField) . "`) VALUES ( " .
                implode(",",$foundValue) . ") " .$duplicateQuery;

            if($batchExecute){
                return $this->db->batchExecute($insertQuery);
            }else{
                if($returnLastId) {
                    return $this->db->insertGetLastInsertId($insertQuery);
                }else{
                    return $this->db->execute($insertQuery);
                }
            }
        }else{
            throw new Exception ("No column matched");
        }
    }

    /**
     * @param tableAdapterQueryContainer $data
     * @param tableAdapterQueryContainer $conditions
     * @param bool $batchExecute (optional) default: false
     * @return array|bool|string
     * @throws Exception
     */
    private function executeUpdateSQL($data,$conditions,$batchExecute=false){
        if ($data->getTotal() !== 0){
            $qWhere = $conditions->getTotal() !== 0 ? " WHERE ".implode(', ', $conditions->getExpressions()) : null;

            $query =
                "UPDATE " .$this->schema . "." . $this->table .
                " SET " . implode(', ', $data->getExpressions()) .
                $qWhere .
                ";";

            if($batchExecute){
                return $this->db->batchExecute($query);
            }else{
                return $this->db->execute($query);
            }
        }else{
            throw new Exception ("No column matched");
        }
    }

    /**
     * convert object to array
     * @param mixed $data
     * @return array|mixed
     */
    private function toArray($data){
        return is_array($data) ? $data : json_decode(json_encode($data,JSON_UNESCAPED_UNICODE ), true);
    }
}

class tableAdapterQueryContainer{
    /** @var  string[] */
    private $fields, $values, $expressions;
    /** @var  int */
    private $total;

    /**
     * tableAdapterQueryContainer constructor.
     * @param string[] $fields
     * @param string[] $values
     * @param string[] $expressions
     * @param int $total
     */
    public function __construct(array $fields, array $values, array $expressions, $total){
        $this->fields = $fields;
        $this->values = $values;
        $this->expressions = $expressions;
        $this->total = $total;
    }

    /**
     * @return string[]
     */
    public function getFields(){
        return $this->fields;
    }

    /**
     * @return string[]
     */
    public function getValues(){
        return $this->values;
    }

    /**
     * @return string[]
     */
    public function getExpressions(){
        return $this->expressions;
    }

    /**
     * @return int
     */
    public function getTotal(){
        return $this->total;
    }


}

