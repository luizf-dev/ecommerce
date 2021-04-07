<?php
 /*require_once("vendor/autoload.php");*/

namespace Hcode;

use Rain\Tpl;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class Mailer {
    const USERNAME =  'luizfernandovidal4@gmail.com';
    const PASSWORD = 'canecodechopp';
    const NAME_FROM = "Hcode Store";

    private $mail;

    public function __construct($toAddress, $toName, $subject, $tplName, $data = array()){     
        
        $config = array(
            "tpl_dir"   => "views/email/",
            "cache_dir" => "./views-cache/",
            "debug"     => true
           );
        Tpl::configure( $config );
        $tpl =  new Tpl;

        foreach($data as $key => $value){
            $tpl->assign($key, $value);
        }

        $html = $tpl->draw($tplName, true);
        
            // Instância da classe
            $this->mail = new \PHPMailer;

            $this->mail->SMTPOptions = array( 'ssl' => array( 'verify_peer' => false,
            'verify_peer_name' => false, 'allow_self_signed' => true ));

            // Configurações do servidor
            $this->mail->IsSMTP();        //Define o uso de SMTP no envio
            $this->mail->SMTPAuth = true; //Habilita a autenticação SMTP
            $this->mail->Username   = Mailer::USERNAME;
            $this->mail->Password   = Mailer::PASSWORD;
            $this->mail->SMTPDebug = 0;
            // Criptografia do envio SSL também é aceito
            $this->mail->SMTPSecure = 'ssl';
            // Informações específicadas pelo Google
            $this->mail->Host = 'smtp.gmail.com';
            $this->mail->Port = 465;
            $this->mail->CharSet = "UTF-8";
            // Define o remetente
            $this->mail->SetFrom(Mailer::USERNAME, Mailer::NAME_FROM);
            // Define o destinatário
            $this->mail->AddAddress($toAddress, $toName);
            // Conteúdo da mensagem
            $this->mail->isHTML(true);  // Seta o formato do e-mail para aceitar conteúdo HTML
            $this->mail->Subject = $subject;
            $this->mail->Body    = $html;
            $this->mail->AltBody = 'Este é o corpo da mensagem para clientes de e-mail que não reconhecem HTML';
            
            
    }
    // Enviar
    public function send(){
        return $this->mail->send();
    }
}
?>