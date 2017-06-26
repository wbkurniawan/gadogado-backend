<?php
/**
 * Created by PhpStorm.
 * User: wkurn
 * Date: 05.11.2015
 * Time: 12:21
 */


define("DBSERVER","DBSERVER");

function getConnectionInfo(){
    $dbConnectionInfo = array(
        DBSERVER => array( 'host'=>'bodyandmindfitness.de',
            'user'=>'chef',
            'password'=>'dO3((;liDtoid',
            'name'=>'gadogado')
    );
    return $dbConnectionInfo;
}

