<?php 

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Model\Cart;



class Order extends Model{

    const SUCCESS = "Order-Success";
    const ERROR = "Order-Error";

    public function save(){

        $sql = new Sql();

        $results = $sql->select("call sp_orders_save(:idorder, :idcart, :iduser,
        :idstatus, :idaddress, :vltotal)", [
            ":idorder"=>$this->getidorder(),
            ":idcart"=>$this->getidcart(),
            ":iduser"=>$this->getiduser(),
            ":idstatus"=>$this->getidstatus(),
            ":idaddress"=>$this->getidaddress(),
            ":vltotal"=>$this->getvltotal()
        ]);

        if(count($results) > 0){
            $this->setData($results[0]);
        }
    }

    public function get($idorder){

        $sql = new Sql();

        $results = $sql->select("
        select * from tb_orders a
        inner join tb_ordersstatus b 
        using (idstatus)
        inner join tb_carts c 
        using(idcart)
        inner join tb_users d 
        on d.iduser = a.iduser 
        inner join tb_addresses e 
        using(idaddress)
        inner join tb_persons f
        on f.idperson = d.idperson
        where a.idorder = :idorder", [

            ":idorder"=>$idorder
        ]);

        if(count($results) > 0 ){
            $this->setData($results[0]);
        }        
    }

    public static function listAll(){

            $sql = new Sql();
            return  $sql->select("
            select * from tb_orders a
            inner join tb_ordersstatus b using (idstatus)
            inner join tb_carts c using(idcart)
            inner join tb_users d on d.iduser = a.iduser 
            inner join tb_addresses e using(idaddress)
            inner join tb_persons f on f.idperson = d.idperson
            order by a.dtregister desc   
        ");
    }

    public function delete(){

        $sql = new Sql();
        $sql->query("delete from tb_orders where idorder = :idorder", [
            ":idorder"=>$this->getidorder()
        ]);
    }

    public function getCart():Cart{

        $cart = new Cart();
        $cart->get((int)$this->getidcart());
        return $cart;
    }

    //M??TODOS PARA MENSAGENS DE ERRO//************************************/
    public static function setError($msg){

        $_SESSION[Order::ERROR] = $msg;
    }

    public static function getError(){
        
        $msg =  (isset($_SESSION[Order::ERROR]) && $_SESSION[Order::ERROR]) ? $_SESSION[Order::ERROR] : "";           

        Order::clearError();
        return $msg;
    }

    public static function clearError(){

        $_SESSION[Order::ERROR] = NULL;
    }
    //AT?? AQUI//M??TODOS PARA MENSAGENS DE ERRO//************************************/
    

    //M??TODOS PARA MENSAGENS DE SUCESSO//************************************/
    public static function setSuccess($msg){

        $_SESSION[Order::SUCCESS] = $msg;
    }

    public static function getSuccess(){
        
        $msg =  (isset($_SESSION[Order::SUCCESS]) && $_SESSION[Order::SUCCESS]) ? $_SESSION[Order::SUCCESS] : "";           

        Order::clearSuccess();
        return $msg;
    }

    public static function clearSuccess(){

        $_SESSION[Order::SUCCESS] = NULL;
    }
     //AT?? AQUI //M??TODOS PARA MENSAGENS DE SUCESSO//************************************/



     //PAGINA????O DOs PEDIDOS
     public static function getPage($page = 1, $itemsPorPage = 10){

        $start = ($page - 1) * $itemsPorPage;
        $sql = new Sql();
        $results = $sql->select("
            select SQL_CALC_FOUND_ROWS * 
            from tb_orders a
            inner join tb_ordersstatus b using (idstatus)
            inner join tb_carts c using(idcart)
            inner join tb_users d on d.iduser = a.iduser 
            inner join tb_addresses e using(idaddress)
            inner join tb_persons f on f.idperson = d.idperson
            order by a.dtregister desc   
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
            from tb_orders a
            inner join tb_ordersstatus b using (idstatus)
            inner join tb_carts c using(idcart)
            inner join tb_users d on d.iduser = a.iduser 
            inner join tb_addresses e using(idaddress)
            inner join tb_persons f on f.idperson = d.idperson
            where a.idorder = :id or f.desperson like :search
            order by a.dtregister desc   
            limit $start, $itemsPorPage;",[
                ":search"=>'%'.$search.'%',
                ":id"=>$search
            ]);

       $resultTotal = $sql->select("select FOUND_ROWS() as nrtotal;");
       return [
           "data"=>($results),
           "total"=>(int)$resultTotal[0]["nrtotal"],
           "pages"=>ceil($resultTotal[0]["nrtotal"] / $itemsPorPage)
       ];
    }


}







?>