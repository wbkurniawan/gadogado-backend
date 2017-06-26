<?php
/**
 * Created by PhpStorm.
 * User: William
 * Date: 8/7/2016
 * Time: 10:31 PM
 */

include_once(__DIR__.'/../model/class/UserSession.php');
include_once(__DIR__.'/../model/class/Books.php');
include_once(__DIR__.'/../library/db/Connect.class.php');
include_once(__DIR__.'/../lock.php');
header('Content-type: application/json');

$bookId = isset($_GET["bookId"])?$_GET["bookId"]:"";
if(!isset($_SESSION["user"])){
    $isAdmin= 0;
    $userId = 0;
}else{
    $userSession =  unserialize($_SESSION["user"]);
    $isAdmin= (integer) $userSession->admin;
    $userId = $userSession->userId;
}

try{
    $book = new Books();
    $bookArray = $book->getBookById($bookId);
    if(isset($bookArray[0]["description"])){
        $bookArray[0]["description"] = nl2br($bookArray[0]["description"]);
    }
    $response = ['data'=>$bookArray];
    if($isAdmin==1){
        $response["editable"] = 1;
    }
    $response["owner"] = 0;
    if(isset($bookArray[0]["user_id"])){
        if($userId==$bookArray[0]["user_id"]){
            $response["editable"] = 1;
            $response["owner"] = 1;
        }
    }

}catch(Exception $e) {
    $response = array('error' => true,
        'error_message' => $e->getMessage(),
        'error_code' => 500);
}
echo json_encode($response);
