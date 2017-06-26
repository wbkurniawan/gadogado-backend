<?php
/**
 * Created by PhpStorm.
 * User: William
 * Date: 4/16/2017
 * Time: 9:29 AM
 */

include_once(__DIR__.'/../../config/global.php');
include_once(__DIR__.'/../../library/db/Connect.class.php');
include_once(__DIR__.'/../../library/db/TableAdapter.class.php');


class Product{

    private $db;
    private $products;
    private $productId;
    private $returnStats;
    private $inJSON;
    private $page;
    private $filters;
    private $orders;
    private $foundRows;
    private $properties;

    public function __construct($productId = null)
    {
        $this->productId = $productId;
        $this->db = new Connect(Connect::DBSERVER);
        $this->page = 1;
        $this->returnStats = false;
    }

    public function getProduct()
    {
        if(isset($this->productId)){
            $this->loadProducts();
            return $this->getResult();
        }else{
            return null;
        }
    }

    public function getProductById($productId)
    {
        $this->productId = $productId;
        $this->loadProducts();
        return $this->getResult();
    }

    public function search($key,$page=1,$limit=PRODUCTS_VIEW_LIMIT_DEFAULT)
    {
        $this->page = $page;
        if(!empty($key)){
            $key = str_replace(" ","%",$key);
            $this->filters = ["(`product`.`name` like '%" . $key ."%' OR `product`.`description` like '%" . $key ."%')"];
        }
        $this->filters[] = "`product`.`deleted` = 0 ";
        $this->loadProducts($this->page,$limit);
        return $this->getResult();
    }

    public function getProductByCategoryId($categoryId,$page=1,$limit=PRODUCTS_VIEW_LIMIT_DEFAULT){
        $this->page = $page;
        if(!empty($categoryId)){
            $this->filters = ["`product`.`category_id` = ". $categoryId." "];
        }
        $this->filters[] = "`product`.`deleted` = 0 ";
        $this->loadProducts($this->page,$limit);
        return $this->getResult();
    }

    public function getProductByOriginId($originId,$page=1,$limit=PRODUCTS_VIEW_LIMIT_DEFAULT){
        $this->page = $page;
        if(!empty($originId)){
            $this->filters = ["`product`.`origin_id` = ". $originId." "];
        }
        $this->filters[] = "`product`.`deleted` = 0 ";
        $this->loadProducts($this->page,$limit);
        return $this->getResult();
    }

    public function getProductByUserId($userId,$page=1,$limit=PRODUCTS_VIEW_LIMIT_DEFAULT){
        $this->page = $page;
        if(!empty($userId)){
            $this->filters = ["`product`.`user_id` = ". $userId." "];
        }
        $this->filters[] = "`product`.`deleted` = 0 ";
        $this->loadProducts($this->page,$limit);
        return $this->getResult();
    }

    private function loadProducts($page=1,$limit=PRODUCTS_VIEW_LIMIT_DEFAULT){
        if(isset($this->productId)){
            $this->filters[] = " product.product_id = ".$this->productId;
        }

        $filterQuery = "";
        if(count($this->filters)>0){
            $filterQuery = " WHERE " . implode(" AND ",$this->filters);
        }

        $orderQuery = "";
        if(count($this->orders)>0){
            $orderQuery = " ORDER BY " . implode(" , ",$this->orders);
        }


        $queries["command"] = " SELECT ";
        if($this->returnStats){
            $queries["option"] = " SQL_CALC_FOUND_ROWS ";
        }
        $queries["fields"] = " `product`.`product_id`,
                                `product`.`name`,
                                `product`.`description`,
                                `product`.`price`,
                                `product`.`stock`,
                                `product`.`user_id`,
								`user`.`name` as user_name,
                                `product`.`zip`,
                                `product`.`deleted`,
                                DATE_FORMAT(`product`.`pickup_date`, '%d.%m.%Y %H:%i') as `pickup_date`,
                                `product`.`category_id`,
                                `product`.`origin_id`,
                                `product`.`type_id`,
                                `product`.`timestamp`,
                                 GROUP_CONCAT(product_image.filename SEPARATOR '|') as images
                                 ";
        $queries["tables"] = "  FROM gadogado.product
									INNER JOIN
								gadogado.user ON product.user_id = user.user_id								
                                    LEFT JOIN
                                gadogado.product_image								
                                ON product.product_id = product_image.product_id AND product_image.deleted = 0 ";
        $queries["conditions"] = $filterQuery;
        $queries["group"] = " GROUP BY  `product`.`product_id` ";
        $queries["order"] = $orderQuery;
        $queries["limit"] = " LIMIT ".(($page-1)*$limit).",".$limit;

        $query = implode("",$queries);
        $this->products = $this->db->selectArray($query);
        foreach ($this->products as &$product){
			if(!empty($product["images"])){
				$images = explode("|",$product["images"]);
				$product["images"] = array();
				foreach ($images as $image){
					$product["images"][] = IMAGE_PATH.$image;
				}
			}            
        }
        if($this->returnStats){
            $this->foundRows = $this->db->selectValue("SELECT FOUND_ROWS();");
        }
    }

    public function setInJson($inJSON=true){
        $this->inJSON = $inJSON;
    }

    public function setReturnStats($returnStats=true){
        $this->returnStats = $returnStats;
    }

    public function toJSON(){

        $result = ['data'=>$this->products,
                    'page'=>$this->page];
        if($this->returnStats){
            $result['stats'] = ['total'=>$this->foundRows];
        }
        return json_encode($result);
    }

    private function getResult(){
        if($this->inJSON){
            return $this->toJSON();
        }else{
            return $this->products;
        }
    }
}