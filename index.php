<?php 
session_start();
require_once("vendor/autoload.php");

use \Slim\Slim;
use \Hcode\Page;
use \Hcode\PageAdmin;
use \Hcode\Model\User;
use \Hcode\Model\Category;
use \Hcode\Model\Product;
use \Hcode\Model\Cart;
use \Hcode\Model\Address;

$app = new Slim();

$app->config('debug', true);

require_once("functions.php");

//INDEX DA HOME DO  SITE
$app->get('/', function() {
	$products = Product::listAll();
 
	$page = new Page();
	$page->setTpl("index", [
		"products"=>Product::checkList($products)
	]);
});


//PAGINAÇÃO DAS CATEGORIAS
$app->get("/categories/:idcategory/", function($idcategory){
	$page = (isset($_GET['page'])) ? (int)$_GET['page'] : 1;
	$category = new Category();
	$category->get((int)$idcategory);
	$pagination = $category->getProductsPage($page);
	$pages = [];
	for($i=1; $i <= $pagination["pages"]; $i++){
		array_push($pages, [
			"link"=> "/ecommerce/categories/" . $category->getidcategory() . "/?page=" . $i,
			"page"=>$i
		]);
	}
	$page = new Page();
	$page->setTpl("category", [
		"category"=>$category->getValues(),
		"products"=>$pagination["data"],
		"pages"=>$pages
	]);
});

// DETALHES DO PRODUTO
$app->get("/product/:desurl/", function($desurl){
	$product = new Product();
	$product->getFromURL($desurl);
	$page = new Page();
	$page->setTpl("product-detail",[
		"product"=>$product->getValues(),
		"categories"=>$product->getCategories()
	]);
});

//CARRINHO DE COMPRAS 
$app->get("/cart/", function(){

	$cart = Cart::getFromSession();
	$page = new Page();
	$page->setTpl("cart", [
		"cart"=>$cart->getValues(),
		"products"=>$cart->getProducts(),
		"error"=>Cart::getMsgError()
	]);
});

//ADICIONAR PRODUTO NO CARRINHO
$app->get("/cart/:idproduct/add/", function($idproduct){

	$product = new Product();
	$product->get((int)$idproduct);
	$cart = Cart::getFromSession();
	$qtd = (isset($_GET['qtd'])) ? (int)$_GET['qtd'] : 1;

	for($i = 0; $i < $qtd; $i++){
		$cart->addProduct($product);
	}

	header("Location: /ecommerce/cart/");
	exit;
});

//REMOVER APENAS 1 PRODUTO DO CARRINHO
$app->get("/cart/:idproduct/minus/", function($idproduct){

	$product = new Product();
	$product->get((int)$idproduct);
	$cart = Cart::getFromSession();
	$cart->removeProduct($product);
	header("Location: /ecommerce/cart/");
	exit;
});

//REMOVE TODOS OS PRODUTOS DO CARRINHO
$app->get("/cart/:idproduct/remove/", function($idproduct){

	$product = new Product();
	$product->get((int)$idproduct);
	$cart = Cart::getFromSession();
	$cart->removeProduct($product, true);
	header("Location: /ecommerce/cart/");
	exit;
});

//CALCULO DE FRETE
$app->post("/cart/freight/", function(){

	$cart = Cart::getFromSession();

	$cart->setFreight($_POST['zipcode']);

	header("Location: /ecommerce/cart/");
	exit;
});

//CHECKOUT DO SITE
$app->get("/checkout/", function(){

	User::verifyLogin(false);

	$cart = Cart::getFromSession();
	$address = new Address();
	$page = new Page();
	$page->setTpl("checkout", [
		"cart"=>$cart->getValues(),
		"address"=>$address->getValues()
	]);
});

//TEMPLATE DE LOGIN DO SITE
$app->get("/login/", function(){

	$page = new Page();
	$page->setTpl("login", [
		"error"=>User::getError()
	]);
});

//LOGIN DO SITE
$app->post("/login/", function(){

	try{		
	User::login($_POST['login'], $_POST['password']);
	}catch(Exception $e){
		User::setError($e->getMessage());
	}

	header("Location: /ecommerce/checkout/");
	exit;	
});

//LOGOUT DO SITE
$app->get("/logout/", function(){

	User::logout();
	header("Location: /ecommerce/login/");
	exit;
});


$app->get('/admin/', function() {
	User::verifyLogin();

	$page = new PageAdmin();

	$page->setTpl("index");
});

$app->get('/admin/login/', function(){

	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);
	$page->setTpl("login");

});

$app->post('/admin/login/', function(){

	User::login($_POST['login'], $_POST['password']);
	header("Location: /ecommerce/admin/");
	exit;
});

$app->get('/admin/logout/', function(){
	User::logout();

	header("Location: /ecommerce/admin/login/");
	exit;
});

$app->get("/admin/users/", function(){
	User::verifyLogin();
	$users = User::listAll();
	$page = new PageAdmin();
	$page->setTpl("users", array(
		"users"=>$users
	));
});

$app->get("/admin/users/create/", function(){
	User::verifyLogin();
	$page = new PageAdmin();
	$page->setTpl("users-create");
});

$app->get("/admin/users/:iduser/delete/", function($iduser){
	User::verifyLogin();
	$user = new User();
	$user->get((int)$iduser);
	$user->delete();
	header("Location: /ecommerce/admin/users/");
	exit;

});

$app->get("/admin/users/:iduser/", function($iduser){
	User::verifyLogin();
    $user = new User();
    $user->get((int)$iduser);
    $page = new PageAdmin();
    $page ->setTpl("users-update", array(
        "user"=>$user->getValues()
    ));
});

$app->post("/admin/users/create/", function(){
	User::verifyLogin();
	$user = new User();
	$_POST["inadmin"] = (isset($_POST["inadmin"]))?1:0;
	$_POST['despassword'] = password_hash($_POST["despassword"], PASSWORD_DEFAULT, [
		"cost"=>12
	]);
	$user->setData($_POST);
	$user->save();
	header("Location: /ecommerce/admin/users/");
	exit;

});

$app->post("/admin/users/:iduser/", function($iduser){
	User::verifyLogin();
	$user = new User();
	$_POST["inadmin"] = (isset($_POST["inadmin"]))?1:0;
	$user->get((int)$iduser);
	$user->setData($_POST);
	$user->update();
	header("Location: /ecommerce/admin/users/");
	exit;
});

$app->get("/admin/forgot/", function(){
	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);
	$page->setTpl("forgot");
});

$app->post("/admin/forgot/", function(){
	
	$user = User::getForgot($_POST["email"]);
	header("Location: /ecommerce/admin/forgot/sent/");
	exit;
});

$app->get("/admin/forgot/sent/", function(){
	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);
	$page->setTpl("forgot-sent");
});

$app->get("/admin/forgot/reset", function(){
	$user = User::recuperarSenha($_GET["code"]);	 
	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);
	$page->setTpl("forgot-reset" , array(
		"name"=>$user["desperson"],
		"code"=>$_GET["code"]
	));
});



$app->post("/admin/forgot/reset/", function(){

	$forgot = User::recuperarSenha($_POST["code"]);	

	User::setFogotUsed($forgot["idrecovery"]);

	$user = new User();

	$user->get((int)$forgot["iduser"]);

	$password = password_hash($_POST["password"], PASSWORD_DEFAULT,[
		"cost"=>12

	]);

	$user->setPassword($password);

	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);

	$page->setTpl("forgot-reset-success");
});

$app->get("/admin/categories/", function(){
	User::verifyLogin();
	$categories = Category::listAll();
	$page = new PageAdmin();
	$page->setTpl("categories",[
		"categories"=>$categories
	]);
});

$app->get("/admin/categories/create/", function(){
	User::verifyLogin();
	$page = new PageAdmin();
	$page->setTpl("categories-create");
});

$app->post("/admin/categories/create/", function(){
	User::verifyLogin();
	$category = new Category();
	$category->setData($_POST);
	$category->save();
	header("Location: /ecommerce/admin/categories/");
	exit;
});

$app->get("/admin/categories/:idcategory/delete/", function($idcategory){
	User::verifyLogin();
	$category = new Category();
	$category->get((int)$idcategory);
	$category->delete();
	header("Location: /ecommerce/admin/categories/");
	exit;
});

$app->get("/admin/categories/:idcategory/", function($idcategory){
	User::verifyLogin();
	$category = new Category();
	$category->get((int)$idcategory);
	$page = new PageAdmin();
	$page->setTpl("categories-update", [
		"category"=>$category->getValues()
	]);
});

$app->post("/admin/categories/:idcategory/", function($idcategory){
	User::verifyLogin();
	$category = new Category();
	$category->get((int)$idcategory);	
	$category->setData($_POST);
	$category->save();
	header("Location: /ecommerce/admin/categories/");
	exit;
});



$app->get("/admin/categories/:idcategory/product/", function($idcategory){
	User::verifyLogin();
	$category = new Category();
	$category->get((int)$idcategory);
	$page = new PageAdmin();
	$page->setTpl("categories-products", [
		"category"=>$category->getValues(),
		"productsRelated"=>$category->getProducts(true),
		"productsNotRelated"=>$category->getProducts(false)
	]);
});

$app->get("/admin/categories/:idcategory/product/:idproduct/add/", function($idcategory, $idproduct){
	User::verifyLogin();
	$category = new Category();
	$category->get((int)$idcategory);
	$product = new Product();
	$product->get((int)$idproduct);
	$category->addProduct($product);
	header("Location: /ecommerce/admin/categories/" . $idcategory . "/product/");
	exit; 
});


$app->get("/admin/categories/:idcategory/product/:idproduct/remove/", function($idcategory, $idproduct){
	User::verifyLogin();
	$category = new Category();
	$category->get((int)$idcategory);
	$product = new Product();
	$product->get((int)$idproduct);
	$category->removeProduct($product);
	header("Location: /ecommerce/admin/categories/" . $idcategory . "/product/");
	exit; 
});


$app->get("/admin/product/", function(){
	User::verifyLogin();
	$products = Product::listAll();
	$page = new PageAdmin();
	$page->setTpl("products", [
		"products"=>$products
	]);
});

$app->get("/admin/product/create/", function(){
	User::verifyLogin();
	$page = new PageAdmin();
	$page->setTpl("products-create");
});

$app->post("/admin/product/create/", function(){
	User::verifyLogin();
	$product = new Product();
	$product->setData($_POST);
	$product->save();
	header("Location: /ecommerce/admin/product/");
	exit;
});

$app->get("/admin/product/:idproduct/", function($idproduct){
	User::verifyLogin();
	$product = new Product();
	$product->get((int)$idproduct);
	$page = new PageAdmin();
	$page->setTpl("products-update", [
		"products"=>$product->getValues()
	]);
});

$app->post("/admin/product/:idproduct/", function($idproduct){
	User::verifyLogin();
	$product = new Product();
	$product->get((int)$idproduct);
	$product->setData($_POST);
	$product->save();
	$product->setPhoto($_FILES["file"]);
	header("Location: /ecommerce/admin/product/");
	exit;
});

$app->get("/admin/product/:idproduct/delete/", function($idproduct){
	User::verifyLogin();
	$product = new Product();
	$product->get((int)$idproduct);
	$product->delete();
	header("Location: /ecommerce/admin/product/");
	exit;
});



$app->run();

 ?>