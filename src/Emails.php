<?php

namespace ClasseGeral;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Emails extends \ClasseGeral\ClasseGeral {

    private string $emailOrigem = 'naoresponda@theplayeron.com.br';
    private string $senhaOrigem = 'dvDezHX,L^pj#Rd?';

    public function enviarEmail($parametros, string $tipoRetorno = 'json'): string|array
    {
        $retorno = ['sucesso' => false];

        $p = $parametros;
        $destinatario = $this->antiInjection($p['destinatario']);
        $assunto = $this->antiInjection($p['assunto']);
        $mensagem = $this->antiInjection($p['mensagem']);
        
        try {
            // Carregar autoload do Composer
            $caminhoApi = $this->pegaCaminhoApi();
            
            // Carregar autoload do Composer
            $caminhoAutoload = $caminhoApi . 'api/api-back/vendor/autoload.php';
            if (file_exists($caminhoAutoload)) {
                require_once $caminhoAutoload;
            } else {
                // Tentar caminho alternativo
                $caminhoAutoload = __DIR__ . '/../../vendor/autoload.php';
                if (file_exists($caminhoAutoload)) {
                    require_once $caminhoAutoload;
                } else {
                    throw new \Exception('Arquivo autoload.php do Composer não encontrado');
                }
            }
            
            // Criar instância do PHPMailer usando autoload
            $mail = new PHPMailer(true);
            
            // Configurações do servidor SMTP próprio (cPanel/WHM)
            $mail->isSMTP();
            $mail->Host = 'mail.theplayeron.com.br'; // Servidor SMTP local do cPanel/WHM
            $mail->SMTPAuth = true;
            $mail->Username = $this->emailOrigem; // Email completo usado no cPanel
            $mail->Password = $this->senhaOrigem; // Senha do email configurada no cPanel
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL direto para porta 465
            $mail->Port = 465; // Porta padrão do cPanel para SMTP com SSL
            $mail->CharSet = 'UTF-8';
            $mail->setLanguage('pt_br');
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Remetente
            $mail->setFrom($this->emailOrigem);
            
            // Destinatário
            $mail->addAddress($destinatario);
            
            // Conteúdo do email
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body = $mensagem;
            $mail->AltBody = strip_tags($mensagem); // Versão texto puro


            // Enviar email
            if ($mail->send()) {
                $retorno = ['sucesso' => true, 'mensagem' => 'Email enviado com sucesso'];
            } else {
                $retorno = ['sucesso' => false, 'mensagem' => 'Erro ao enviar email: ' . $mail->ErrorInfo];
            }
            
        } catch (\Exception $e) {
            $retorno = ['sucesso' => false, 'mensagem' => 'Erro ao enviar email: ' . $e->getMessage()];
        }

        return $tipoRetorno == 'json' ? json_encode($retorno) : $retorno;
    }
}
