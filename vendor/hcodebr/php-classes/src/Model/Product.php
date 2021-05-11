<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Mailer;

class Product extends Model{
   
    public static function listAll(){
        $sql = new Sql();
        return $sql->select("select * from tb_products order by desproduct");
    }

    public static function checkList($list){
        foreach($list as &$row){
            $p = new Product();
            $p->setData($row);
            $row = $p->getValues();
        }
        return $list;
    }
    
    public function save(){
        $sql = new Sql();

        $results = $sql->select("CALL sp_products_save(:idproduct, :desproduct, :vlprice, :vlwidth, :vlheight, :vllength, :vlweight, :desurl)",
        array(
            ":idproduct"=>$this->getidproduct(),
            ":desproduct"=>$this->getdesproduct(),
            ":vlprice"=>$this->getvlprice(),
            ":vlwidth"=>$this->getvlwidth(),
            ":vlheight"=>$this->getvlheight(),
            ":vllength"=>$this->getvllength(),
            ":vlweight"=>$this->getvlweight(),
            ":desurl"=>$this->getdesurl()
       ));
       $this->setData($results[0]); 
    }

    public function get($idproduct){
        $sql = new Sql();
       $results =  $sql->select("select * from tb_products where idproduct = :idproduct", [
            ":idproduct"=>$idproduct
        ]);

        $this->setData($results[0]);
    }

    public function delete(){
        $sql = new Sql();
        $sql->query("delete from tb_products where idproduct like :idproduct", [
            ":idproduct"=>$this->getidproduct()
        ]);
    }

    public function checkPhoto(){
        if(file_exists($_SERVER["DOCUMENT_ROOT"]
        . DIRECTORY_SEPARATOR 
        . "ecommerce" 
        . DIRECTORY_SEPARATOR 
        . "resources" 
        . DIRECTORY_SEPARATOR 
        . "site"
        . DIRECTORY_SEPARATOR
        . "img"
        . DIRECTORY_SEPARATOR
        . "products"
        . DIRECTORY_SEPARATOR
        . $this->getidproduct() . ".jpg"
        )){
            $url =  "/ecommerce/resources/site/img/products/" . $this->getidproduct() . ".jpg";
        }else{
            $url =  "/ecommerce/resources/site/img/product.jpg";
        }
        return $this->setdesphoto($url);
    }

    public function getValues(){
        $this->checkPhoto();
        $values = parent::getValues();        
        return $values;
    }

    public function setPhoto($file){
        $extension = explode('.', $file["name"]);
        $extension = end($extension);

        switch($extension){
            case "jpg":
            case "jpeg":
                $image = imagecreatefromjpeg($file["tmp_name"]);
            break;

            case "gif":
                $image = imagecreatefromgif($file["tmp_name"]);
            break;

            case "png":
                $image = imagecreatefrompng($file["tmp_name"]);
            break;
        }

        $destino = $_SERVER["DOCUMENT_ROOT"] 
        . DIRECTORY_SEPARATOR
        . "ecommerce"
        . DIRECTORY_SEPARATOR 
        . "resources" 
        . DIRECTORY_SEPARATOR 
        . "site"
        . DIRECTORY_SEPARATOR
        . "img"
        . DIRECTORY_SEPARATOR
        . "products"
        . DIRECTORY_SEPARATOR
        . $this->getidproduct() . ".jpg";

        imagejpeg($image, $destino);
        imagedestroy($image);
        $this->checkPhoto();
    }

    public function getFromURL($desurl){
        $sql = new Sql();
        $rows = $sql->select("select * from tb_products where desurl = :desurl limit 1",[
            ":desurl"=>$desurl
        ]);
        $this->setData($rows[0]);
    }

    public function getCategories(){
        $sql = new Sql();
        return $sql->select("
            select * from tb_categories a inner join tb_productscategories b on 
            a.idcategory = b.idcategory where b.idproduct = :idproduct",[
                ":idproduct"=>$this->getidproduct()        
        ]);
    }

     //PAGINAÇÃO DOS PRODUTOS
     public static function getPage($page = 1, $itemsPorPage = 10){

        $start = ($page - 1) * $itemsPorPage;
        $sql = new Sql();
        $results = $sql->select("
            select SQL_CALC_FOUND_ROWS * 
            from tb_products
            order by desproduct
            limit $start, $itemsPorPage;");

       $resultTotal = $sql->select("select FOUND_ROWS() as nrtotal;");
       return [
           "data"=>($results),
           "total"=>(int)$resultTotal[0]["nrtotal"],
           "pages"=>ceil($resultTotal[0]["nrtotal"] / $itemsPorPage)
       ];
    }

    public static function getPageSearch($search, $page = 1, $itemsPorPage = 10){

        $start = ($page - 1) * $itemsPorPage;
        $sql = new Sql();
        $results = $sql->select("
            select SQL_CALC_FOUND_ROWS * 
            from tb_products
            where desproduct like :search
            order by desproduct
            limit $start, $itemsPorPage;",[
                "search"=>'%'.$search.'%'
            ]);

       $resultTotal = $sql->select("select FOUND_ROWS() as nrtotal;");
       return [
           "data"=>($results),
           "total"=>(int)$resultTotal[0]["nrtotal"],
           "pages"=>ceil($resultTotal[0]["nrtotal"] / $itemsPorPage)
       ];
    }
    
}

