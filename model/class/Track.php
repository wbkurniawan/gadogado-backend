<?php
/**
 * Created by PhpStorm.
 * User: William
 * Date: 11/26/2017
 * Time: 12:37 PM
 */
include_once(__DIR__.'/../../config/global.php');
include_once(__DIR__.'/../../library/db/Connect.class.php');
include_once(__DIR__.'/../../library/db/TableAdapter.class.php');


class Track
{
    private $db;
    private $ta;
    private $allFields = " id, user_id, category, value1, value2, created ";

    public function __construct()
    {
        $this->db = new Connect(Connect::DBSERVER);
        $this->ta = new TableAdapter($this->db,"gadogado","track");
    }

    public function add($userId,$category,$value1=null,$value2=null,$created=null){
        try{
            $newRecord = array();
            if(isset($created)){
                $newRecord = ["user_id"=>$userId,"category"=>$category,"value1"=>$value1,"value2"=>$value2,"created"=>$created];
            }else{
                $newRecord = ["user_id"=>$userId,"category"=>$category,"value1"=>$value1,"value2"=>$value2];
            }
            $this->ta->insert($newRecord,false);
            return true;
        }catch (Exception $e){
            return false;
        }
    }

    public function getLastEntryByCategory($userId,$category){
        $query = "SELECT  " .$this->allFields . " FROM gadogado.track 
                  WHERE user_id = " . $this->db->quote($userId). " AND category = " .$this->db->quote($category) ."
                  AND created < NOW() 
                  ORDER by created DESC limit 0,1";
        $rows = $this->db->selectArray($query);
        if(count($rows)>0)
            return $rows[0];
        else
            return null;

    }
}