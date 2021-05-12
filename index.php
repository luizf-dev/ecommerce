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
use \Hcode\Model\Order;
use \Hcode\Model\OrderStatus;

$app = new Slim();

$app->config('debug', true);

require_once("functions.php");
require_once("admin-orders.php");

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
	$cart = Cart::getFromSession();
	$cart->getCalculateTotal();
	$order = new Order();
	$order->setData([
		"idcart"=>$cart->getidcart(),
		"idaddress"=>$address->getidaddress(),
		"iduser"=>$user->getiduser(),
		"idstatus"=>OrderStatus::EM_ABERTO,
		"vltotal"=>$cart->getvltotal()
		
	]);

	$order->save();

	header("Location: /ecommerce/order/".$order->getidorder());
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

//FINALIZAR COMPRA 
$app->get("/order/:idorder/", function($idorder){

	User::verifyLogin(false);

	$order = new Order();
	$order->get((int)$idorder);
	$page = new Page();
	$page->setTpl("payment", [
		"order"=>$order->getValues()
	]);
});

//GERAR BOLETO // VARIÁVEIS PARA GERAÇÃO DO BOLETO
$app->get("/boleto/:idorder/", function($idorder){

	User::verifyLogin(false);
	$order = new Order();
	$order->get((int)$idorder);

// DADOS DO BOLETO PARA O SEU CLIENTE
$dias_de_prazo_para_pagamento = 10;
$taxa_boleto = 5.00;
$data_venc = date("d/m/Y", time() + ($dias_de_prazo_para_pagamento * 86400));  // Prazo de X dias OU informe data: "13/04/2006"; 
$valor_cobrado = formatPrice($order->getvltotal()); // Valor - REGRA: Sem pontos na milhar e tanto faz com "." ou "," ou com 1 ou 2 ou sem casa decimal
$valor_cobrado = str_replace(".", "", $valor_cobrado);
$valor_cobrado = str_replace(",", ".", $valor_cobrado);
$valor_boleto = number_format($valor_cobrado + $taxa_boleto, 2, ',', '');

$dadosboleto["nosso_numero"] = $order->getidorder();  // Nosso numero - REGRA: Máximo de 8 caracteres!
$dadosboleto["numero_documento"] = $order->getidorder();	// Num do pedido ou nosso numero
$dadosboleto["data_vencimento"] = $data_venc; // Data de Vencimento do Boleto - REGRA: Formato DD/MM/AAAA
$dadosboleto["data_documento"] = date("d/m/Y"); // Data de emissão do Boleto
$dadosboleto["data_processamento"] = date("d/m/Y"); // Data de processamento do boleto (opcional)
$dadosboleto["valor_boleto"] = $valor_boleto; 	// Valor do Boleto - REGRA: Com vírgula e sempre com duas casas depois da virgula

// DADOS DO SEU CLIENTE
$dadosboleto["sacado"] = $order->getdesperson();
$dadosboleto["endereco1"] = $order->getdesaddress() . " - " . $order->getdesdistrict();
$dadosboleto["endereco2"] = $order->getdescity() . " - " . $order->getdesstate() . " - " . $order->getdescountry() . " - CEP : " .  $order->getdeszipcode();

// INFORMACOES PARA O CLIENTE
$dadosboleto["demonstrativo1"] = "Pagamento de Compra na Loja Hcode E-commerce";
$dadosboleto["demonstrativo2"] = "Taxa bancária - R$ 0,00";
$dadosboleto["demonstrativo3"] = "";
$dadosboleto["instrucoes1"] = "- Sr. Caixa, cobrar multa de 2% após o vencimento";
$dadosboleto["instrucoes2"] = "- Receber até 10 dias após o vencimento";
$dadosboleto["instrucoes3"] = "- Em caso de dúvidas entre em contato conosco: suporte@hcode.com.br";
$dadosboleto["instrucoes4"] = "&nbsp; Emitido pelo sistema Projeto Loja Hcode E-commerce - www.hcode.com.br";

// DADOS OPCIONAIS DE ACORDO COM O BANCO OU CLIENTE
$dadosboleto["quantidade"] = "";
$dadosboleto["valor_unitario"] = "";
$dadosboleto["aceite"] = "";		
$dadosboleto["especie"] = "R$";
$dadosboleto["especie_doc"] = "";


// ---------------------- DADOS FIXOS DE CONFIGURAÇÃO DO SEU BOLETO --------------- //


// DADOS DA SUA CONTA - ITAÚ
$dadosboleto["agencia"] = "1690"; // Num da agencia, sem digito
$dadosboleto["conta"] = "48781";	// Num da conta, sem digito
$dadosboleto["conta_dv"] = "2"; 	// Digito do Num da conta

// DADOS PERSONALIZADOS - ITAÚ
$dadosboleto["carteira"] = "175";  // Código da Carteira: pode ser 175, 174, 104, 109, 178, ou 157

// SEUS DADOS
$dadosboleto["identificacao"] = "Hcode Treinamentos";
$dadosboleto["cpf_cnpj"] = "24.700.731/0001-08";
$dadosboleto["endereco"] = "Rua Ademar Saraiva Leão, 234 - Alvarenga, 09853-120";
$dadosboleto["cidade_uf"] = "São Bernardo do Campo - SP";
$dadosboleto["cedente"] = "HCODE TREINAMENTOS LTDA - ME";

// NÃO ALTERAR!

/*$path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . "ecommerce" . DIRECTORY_SEPARATOR.  "resources" . DIRECTORY_SEPARATOR .
"boletophp" . DIRECTORY_SEPARATOR . "include" . DIRECTORY_SEPARATOR . "funcoes_itau.php";

$path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR  . "ecommerce" . DIRECTORY_SEPARATOR. "resources" . DIRECTORY_SEPARATOR .
"boletophp" . DIRECTORY_SEPARATOR . "include" . DIRECTORY_SEPARATOR . "layout_itau.php";*/

require_once("resources/boletophp/include/funcoes_itau.php");
require_once("resources/boletophp/include/layout_itau.php");

});

//MEUS PEDIDOS
$app->get("/profile/orders/", function(){

	User::verifyLogin(false);
	$user = User::getFromSession();
	$page = new Page();
	$page->setTpl("profile-orders", [
		"orders"=>$user->getOrders()
	]);

});

//DETALHES DO PEDIDO
$app->get("/profile/orders/:idorder/", function($idorder){

	User::verifyLogin(false);
	$order = new Order();
	$order->get((int)$idorder);
	$cart = new Cart();
	$cart->get((int)$order->getidcart());
	$cart->getCalculateTotal();
	$page = new Page();
	$page->setTpl("profile-orders-detail", [
		"order"=>$order->getValues(),
		"cart"=>$cart->getValues(),
		"products"=>$cart->getProducts()
	]);
});

//ALTERAR SENHA 
$app->get("/profile/change-password/", function(){

	User::verifyLogin(false);
	$page = new Page();
	$page->setTpl("profile-change-password", [
		"changePassError"=>User::getError(),
		"changePassSuccess"=>User::getSuccess()
	]);
});

//ALTERAR SENHA 
$app->post("/profile/change-password/", function(){
	User::verifyLogin(false);

	//verifica se foi digitada a senha atual ou não está passando o campo vazio//
	if(!isset($_POST['current_pass'])  || $_POST["current_pass"] === ""){

		User::setError("Digite a senha atual!");
		header("Location: /ecommerce/profile/change-password/");
		exit;
	}


	//verifica a nova senha a ser definida//
	if(!isset($_POST['new_pass'])  || $_POST["new_pass"] === ""){

		User::setError("Digite a nova senha!");
		header("Location: /ecommerce/profile/change-password/");
		exit;
	}
	
	//confirma a nova senha a ser definida//
	if(!isset($_POST['new_pass_confirm'])  || $_POST["new_pass_confirm"] === ""){

		User::setError("Confirme a nova senha!");
		header("Location: /ecommerce/profile/change-password/");
		exit;
	}
		//verifica se a senha de confirmaçao é igual a nova senha!
		if ($_POST['new_pass'] != $_POST['new_pass_confirm']) {
		
			User::setError("A senha de confirmação deve ser igual a nova senha!!");
			header("Location: /ecommerce/profile/change-password/");
			exit;
			}

	//confirma a nova senha a ser definida//
	if($_POST['current_pass']  ===  $_POST["new_pass"]){

		User::setError("Sua nova senha deve ser diferente da atual!");
		header("Location: /ecommerce/profile/change-password/");
		exit;
	}
	

	$user = User::getFromSession();
	if(!password_verify($_POST['current_pass'], $user->getdespassword())){

		User::setError("Sua senha está inválida!! ");
		header("Location: /ecommerce/profile/change-password/");
		exit;
	}

	$user->setdespassword($_POST['new_pass']);
	$user->update();
	$_SESSION[User::SESSION] = $user->getValues();	
	User::setSuccess("Senha alterada com sucesso!!");
	header("Location: /ecommerce/profile/change-password/");
	exit;
});

/***************************************************
 * ************************************************
 * **********************************************
 //ADMIN DAQUI PARA BAIXO*/

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

//ADMIN USUARIOS///
$app->get("/admin/users/:iduser/password/", function($iduser){

	User::verifyLogin();
	$user = new User();
	$user->get((int)$iduser);
	$page = new PageAdmin();
	$page->setTpl("users-password", [
		"user"=>$user->getValues(),
		"msgError"=>User::getError(),
		"msgSuccess"=>User::getSuccess()
	]);
});

$app->post("/admin/users/:iduser/password/", function($iduser){

	User::verifyLogin();

	if(!isset($_POST['despassword']) || $_POST['despassword'] === ''){
		User::setError("Preencha a nova senha!");
		header("Location: /ecommerce/admin/users/$iduser/password/");
		exit;
	}

	if(!isset($_POST['despassword-confirm']) || $_POST['despassword-confirm'] === ''){
		User::setError("Preencha a confirmação da nova senha!");
		header("Location: /ecommerce/admin/users/$iduser/password/");
		exit;
	}

	if($_POST['despassword'] !== $_POST['despassword-confirm']){
		User::setError("Confirme corretamente as senhas!");
		header("Location: /ecommerce/admin/users/$iduser/password/");
		exit;

	}
	$user = new User();
	$user->get((int)$iduser);
	$user->setdespassword($_POST['despassword']);
	$user->update();
	$_SESSION[User::SESSION] = $user->getValues();	
	User::setSuccess("Senha alterada com sucesso!");
		header("Location: /ecommerce/admin/users/$iduser/password/");
		exit;
});



$app->get("/admin/users/", function(){
	User::verifyLogin();

	$search = (isset($_GET['search'])) ? $_GET['search'] : "";
	$page = (isset($_GET['page']))? (int)$_GET['page'] : 1;

	if($search != ''){
		$pagination = User::getPageSearch($search, $page);


	}else{
		$pagination = User::getPage($page);
	}

	$pages = [];

	for($x = 0; $x < $pagination['pages']; $x++){
		array_push($pages, [
			'href'=>"/ecommerce/admin/users/?" .http_build_query([
				'page'=>$x + 1,
				'search'=>$search
			]),
			'text'=>$x + 1
		]);
	}

	$page = new PageAdmin();
	$page->setTpl("users", array(
		"users"=>$pagination['data'],
		"search"=>$search,
		"pages"=>$pages
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


//ADMIN CATEGORIAS
$app->get("/admin/categories/", function(){
	User::verifyLogin();

	$search = (isset($_GET['search'])) ? $_GET['search'] : "";
	$page = (isset($_GET['page']))? (int)$_GET['page'] : 1;

	if($search != ''){
		$pagination = Category::getPageSearch($search, $page);


	}else{
		$pagination = Category::getPage($page);
	}

	$pages = [];

	for($x = 0; $x < $pagination['pages']; $x++){
		array_push($pages, [
			'href'=>"/ecommerce/admin/categories/?" .http_build_query([
				'page'=>$x + 1,
				'search'=>$search
			]),
			'text'=>$x + 1
		]);
	}

	$categories = Category::listAll();
	$page = new PageAdmin();
	$page->setTpl("categories",[
		"categories"=>$pagination['data'],
		"search"=>$search,
		"pages"=>$pages
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



//ADMIN PRODUTOS
$app->get("/admin/product/", function(){
	User::verifyLogin();

	$search = (isset($_GET['search'])) ? $_GET['search'] : "";
	$page = (isset($_GET['page']))? (int)$_GET['page'] : 1;

	if($search != ''){
		$pagination = Product::getPageSearch($search, $page);


	}else{
		$pagination = Product::getPage($page);
	}

	$pages = [];

	for($x = 0; $x < $pagination['pages']; $x++){
		array_push($pages, [
			'href'=>"/ecommerce/admin/product/?" .http_build_query([
				'page'=>$x + 1,
				'search'=>$search
			]),
			'text'=>$x + 1
		]);
	}

	$page = new PageAdmin();
	$page->setTpl("products", [
		"products"=>$pagination['data'],
		"search"=>$search,
		"pages"=>$pages
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