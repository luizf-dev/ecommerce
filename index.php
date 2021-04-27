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
	$address = new Address();
	$cart = Cart::getFromSession();

	if(isset($_GET['zipcode'])){

		$_GET['zipcode'] = $cart->getdeszipcode();
	}

	if(isset($_GET['zipcode'])){
		$address->loadFromCEP($_GET['zipcode']);
		$cart->setdeszipcode($_GET['zipcode']);
		$cart->save();
		$cart->getCalculateTotal();
	}

	if (!$address->getdesaddress()) $address->setdesaddress('');
	if (!$address->getdescomplement()) $address->setdescomplement('');
	if (!$address->getdesdistrict()) $address->setdesdistrict('');
	if (!$address->getdescity()) $address->setdescity('');
	if (!$address->getdesstate()) $address->setdesstate('');
	if (!$address->getdescountry()) $address->setdescountry('');
	if (!$address->getdeszipcode()) $address->setdeszipcode('');

	$page = new Page();
	$page->setTpl("checkout", [
		"cart"=>$cart->getValues(),
		"address"=>$address->getValues(),
		"product"=>$cart->getProducts(),
		"error"=>Address::getMsgError()
	]);
});

//FINALIZANDO E SALVANDO O ENDEREÇO NO BANCO DE DADOS
$app->post("/checkout/", function(){

	User::verifyLogin(false);

	if(!isset($_POST['zipcode']) || $_POST['zipcode'] === ""){

		Address::setMsgError("Por favor informe o CEP!");
		header("Location: /ecommerce/checkout/");
		exit;
	}

	if(!isset($_POST['desaddress']) || $_POST['desaddress'] === ""){

		Address::setMsgError("Por favor informe o endereço!");
		header("Location: /ecommerce/checkout/");
		exit;
	}

	if(!isset($_POST['desdistrict']) || $_POST['desdistrict'] === ""){

		Address::setMsgError("Por favor informe o bairro!");
		header("Location: /ecommerce/checkout/");
		exit;
	}

	if(!isset($_POST['descity']) || $_POST['descity'] === ""){

		Address::setMsgError("Por favor informe a cidade!");
		header("Location: /ecommerce/checkout/");
		exit;
	}

	if(!isset($_POST['desstate']) || $_POST['desstate'] === ""){

		Address::setMsgError("Por favor informe o estado!");
		header("Location: /ecommerce/checkout/");
		exit;
	}

	if(!isset($_POST['descountry']) || $_POST['descountry'] === ""){

		Address::setMsgError("Por favor informe o país!");
		header("Location: /ecommerce/checkout/");
		exit;
	}

	$user = User::getFromSession();
	$address = new Address();
	$_POST['deszipcode'] = $_POST['zipcode'];
	$_POST['idperson'] = $user->getidperson();

	$address->setData($_POST);
	$address->save();
	header("Location: /ecommerce/");
	exit;
});

//TEMPLATE DE LOGIN DO SITE
$app->get("/login/", function(){

	$page = new Page();
	$page->setTpl("login", [
		"error"=>User::getError(),
		"errorRegister"=>User::getErrorRegister(),
		"registerValues"=>(isset($_SESSION['registerValues'])) 
		? $_SESSION['registerValues'] 
		: ['name'=>'', 'email'=>'', 'phone'=>'']
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

//CADASTRAR USUARIO NO SITE
$app->post("/register/", function(){

	$_SESSION['registerValues'] = $_POST;

	if(!isset($_POST['name']) || $_POST['name'] == ""){
		User::setErrorRegister("Preencha seu nome!");
		header("Location: /ecommerce/login/");
		exit;
	}

	if(!isset($_POST['email']) || $_POST['email'] == ""){
		User::setErrorRegister("Preencha seu e-mail!");
		header("Location: /ecommerce/login/");
		exit;
	}

	if(!isset($_POST['password']) || $_POST['password'] == ""){
		User::setErrorRegister("Preencha sua senha!");
		header("Location: /ecommerce/login/");
		exit;
	}

	if(User::checkLoginExist($_POST['email']) === true){
		User::setErrorRegister("Endereço de email já está cadastrado! Por favor utilize outro e-mail!");
		header("Location: /ecommerce/login/");
		exit;
	}

	$user = new User();
	$user->setData([
		"inadmin"=>0,
		"deslogin"=>$_POST['email'],
		"desperson"=>$_POST['name'],
		"desemail"=>$_POST['email'],
		"despassword"=>$_POST['password'],
		"nrphone"=>$_POST['phone']		
	]);

	$user->save();

	User::Login($_POST['email'], $_POST['password']);

	header("Location: /ecommerce/checkout/");
	exit;

});

//ESQUECEU A SENHA SITE
$app->get("/forgot/", function(){
	$page = new Page();
	$page->setTpl("forgot");
});

//ESQUECEU A SENHA SITE
$app->post("/forgot/", function(){
	
	$user = User::getForgot($_POST["email"], false);
	header("Location: /ecommerce/forgot/sent/");
	exit;
});

//ESQUECEU A SENHA SITE
$app->get("/forgot/sent/", function(){
	$page = new Page();
	$page->setTpl("forgot-sent");
});

//ESQUECEU A SENHA SITE
$app->get("/forgot/reset", function(){
	$user = User::recuperarSenha($_GET["code"]);	 
	$page = new Page();
	$page->setTpl("forgot-reset" , array(
		"name"=>$user["desperson"],
		"code"=>$_GET["code"]
	));
});

//ESQUECEU A SENHA SITE
$app->post("/forgot/reset/", function(){

	$forgot = User::recuperarSenha($_POST["code"]);	

	User::setFogotUsed($forgot["idrecovery"]);

	$user = new User();

	$user->get((int)$forgot["iduser"]);

	$password = User::getPasswordHash($_POST['password']);

	$user->setPassword($password);

	$page = new Page();

	$page->setTpl("forgot-reset-success");
});


//PERFIL DO USUARIO SITE
$app->get("/profile/", function(){

	User::verifyLogin(false);
	$user = User::getFromSession();
	
	$page = new Page();
	$page->setTpl("profile", [
		"user"=>$user->getValues(),
		"profileMsg"=>User::getSuccess(),
		"profileError"=>User::getError()
	]);
});


//PERFIL DO USUARIO SITE
$app->post("/profile/", function(){

	User::verifyLogin(false);

	if(!isset($_POST['desperson']) || $_POST['desperson'] === ''){
		User::setError("Preencha o seu nome!");
		header("Location: /ecommerce/profile/");
		exit;
	}

	if(!isset($_POST['desemail']) || $_POST['desemail'] === ''){
		User::setError("Preencha o seu e-mail!");
		header("Location: /ecommerce/profile/");
		exit;
	}

	$user = User::getFromSession();
	if($_POST['desemail'] !== $user->getdesemail()){
		if(User::checkLoginExist($_POST['desemail']) === true){
			User::setError("Este endereço de e-mail já está cadastrado!");
			header("Location: /ecommerce/profile/");
			exit;
		}
	}
	
	$_POST['inadmin'] = $user->getinadmin();
	$_POST['despassword'] = $user->getdespassword();
	$_POST['deslogin'] =  $_POST['desemail'];
	$user->setData($_POST);
	$user->update();
	User::setSuccess("Dados alterados com sucesso!! :)");
	header("Location: /ecommerce/profile/");
	exit;
});











//daqui para baixo parte do admin/////
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
 
    $page->setTpl("users-create", array(
        "error" => User::getError()
    ));
});


$app->get("/admin/users/:iduser/delete/", function($iduser) {

	User::verifyLogin();  	
	$users = new User(); 	
	$users->get((int)$iduser); 	
	$users->delete();  	
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

	if (User::checkLoginExist($_POST['desemail']) === true) {
 
        User::setError("Este endereço de e-mail já está cadastrado.");
        header("Location: /admin/users/create/");
        exit; 
    }

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