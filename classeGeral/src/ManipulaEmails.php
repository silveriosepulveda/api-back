<?php

namespace ClasseGeral;

/**
 * Classe utilitária para manipulação de e-mails (ex: envio, validação).
 *
 * Métodos implementados em Funcoes.php.
 */
class ManipulaEmails
{
     public $servidor = '';
    public $email = '';
    public $nome = '';
    public $destinatarios = array();
    public $assunto = '';
    public $usuario = '';
    public $senha = '';
    public $autenticar = false;
    public $texto = '';

    /**
     * Envia um e-mail com base nas propriedades definidas na classe.
     *
     * @return bool Resultado do envio
     */
    public function enviarEmail()
    {
        /*
        date_default_timezone_set("America/Sao_Paulo");
        
        //Primeiro setamos o cabe�alho:
        $header = " Content-type: text/html; charset=utf-8\r\n";
        //instanciamos o objeto
        $mail = new PHPMailer();
        $mail->SetLanguage("br");
        $mail->IsMail();
        // Informamos que vamos enviar atrav�s de SMTP
        //$mail->IsSMTP();
        $mail->IsHTML(true);
        // Colocamos o servidor smtp
        $mail->Host = $this->servidor;
        $mail->SMTPAuth = $this->autenticar;
        $mail->Username = $this->usuario;
        $mail->Password = $this->senha;

        $mail->From = $this->email;
        $mail->FromName = $this->nome;
        $mail->Subject = $this->assunto;

        foreach ($this->destinatarios as $key => $val) {
            $mail->AddAddress($val);
        }

        $mail->MsgHTML($this->texto, ' ');

        if ($mail->Send()) {
            return true;
        } else {
            return false;
        }
            */
    }// ...existing code from Funcoes.php...
}
