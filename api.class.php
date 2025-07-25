<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null) {
        return json_encode(print_r($error, true));
        echo "<pre>Erro fatal: ";
        print_r($error);
        echo "</pre>";
    }
});

/**
 * Created by PhpStorm.
 * User: Silverio
 * Date: 09/03/2017
 * Time: 16:30 teste
 */

//header('Access-Control-Allow-Origin: *');
//header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
//header('Access-Control-Allow-Methods: POST, GET');

//Instanciando o arquivo funcoes.class.php e vou tentar utilizado nos demais arquivos sem precisar instancia-lo novamente.

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

//use OpenSwoole\WebSocket\Server;
require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$app->setBasePath("/api/api-back");

$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);

    $caminho = $_SERVER['DOCUMENT_ROOT'] . '/';
    $_SESSION[session_id()]['caminhoApiLocal'] = $caminho;
    date_default_timezone_set('America/Sao_Paulo');

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-Session-Id')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$secretKey = 'rYCBLhvichk%WPjM%ayW9x7Uv^pQUqRBY#%vpur9!2e9^Y3JYo';

$authMiddleware = function (Request $request, $handler) use ($secretKey, $app) {
    $sessionId = $request->getHeaderLine('x-session-id');

    if($sessionId){
        session_id($sessionId);
    }
    @session_start();

//    $response = new \Slim\Psr7\Response();
//
//    $authHeader = $request->getHeaderLine('Authorization');
//
//    if (!$authHeader || $authHeader !== $secretKey) {
//        $response->withStatus(401)->withHeader('Content-Type', 'application/json')->getBody()->write(json_encode(['erro' => 'Não Autorizado!']));
//        return $response;
//    }


    return $handler->handle($request);
};

$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

function descriptografaarray($valor)
{
    if (is_array($valor)) {
        $retorno = array();
        foreach ($valor as $key => $value) {
            if (!is_array($value)) {
                //echo $key . ' - ' . $value . "\n";
                //echo mb_detect_encoding($value) . "\n";
                $retorno[$key] = mb_convert_encoding(base64_decode(str_replace('_-_', '/', $value)), 'utf-8');
                //echo $key . ' - ' . $retorno[$key] . "\n";
            } else if (is_array($value)) {
                $retorno[$key] = descriptografaarray($value);
            }
        }
    } else {
        $retorno = $valor;
    }
    return $retorno;
}

//Fazendo alteracoes para adaptar a APILocal
$eSite = false;

function continuar(): bool
{
    global $eSite;

    if ($eSite && !isset($_SESSION[session_id()]))
        return false;
    else
        return true;
}

//Configuracao nova, para o caso de haverem mais de uma API, para o mesmo sistema
$configuracoesAPIs = [];

$caminhoTemp = explode('/', pathinfo($_SERVER['SCRIPT_FILENAME'])['dirname']);
unset($caminhoTemp[sizeof($caminhoTemp) - 1]);
$caminho = join('/', $caminhoTemp) . '/';

if (is_file($caminho . 'backLocal/configuracoesAPIs.php')) {
    require_once $caminho . 'backLocal/configuracoesAPIs.php';
}

ini_set('display_errors', 1);

function validaApi($apiKey)
{
    return $apiKey == 'apiKey';
}
$app->get('/{API}/{tabela}/{funcao_executar}/{parametros}', function (Request $request, Response $response, $args) {
    $API = $args['API'];
    $tabela = $args['tabela'];
    $funcaoExecutar = $args['funcao_executar'];
    $parametros = $args['parametros'];

    global $configuracoesAPIs;
    global $caminho;

    if (in_array($API, array_keys($configuracoesAPIs))) {
        @session_start();
        $_SESSION[session_id()]['caminhoApiLocal'] = $caminho;

        if (substr($parametros, 0, 1) == '{') {
            $p = descriptografaarray(json_decode($parametros, true));
            $parametros = $p;
        }

        $arquivo = $caminho . 'backLocal/' . $configuracoesAPIs[$API]['arquivo'];

        require_once $arquivo;
        $classe = new $tabela();
        $response->getBody()->write($classe->$funcaoExecutar($parametros));
        return $response;
    }
});

$app->post('/{API}/{tabela}/{funcao_executar}', function (Request $request, Response $response, $args) {
    $API = $args['API'];
    $tabela = $args['tabela'];
    $funcaoExecutar = $args['funcao_executar'];

    global $configuracoesAPIs;
    global $caminho;

    if (in_array($API, array_keys($configuracoesAPIs))) {
//        @session_start();
        $_SESSION[session_id()]['caminhoApiLocal'] = $caminho;

        $arquivo = $caminho . 'backLocal/' . $configuracoesAPIs[$API]['arquivo'];

        require_once $arquivo;
        $classe = new $tabela();
        $parametros = [];
        if (isset($_POST['parametros']))
            $parametros = $_POST['parametros'];
        else if (isset($_POST))
            $parametros = $_POST;

        //$parametros = isset($_POST['parametros']) ? $_POST['parametros'] : [];
        $retorno = $classe->$funcaoExecutar($parametros);
        $response->getBody()->write($retorno);
    }
    return $response;
});

$app->get('/{tabela}/{funcao_executar}/{parametros}', function (Request $request, Response $response, $argumentos) {
    $tabela = $argumentos['tabela'];
    $funcaoExecutar = $argumentos['funcao_executar'];
    $parametros = $argumentos['parametros'];

    continuar();
    if (substr($parametros, 0, 1) == '{') {
        $p = json_decode($parametros, true);
        $p = descriptografaarray($p);
        $parametros = $p;
    }

    $classe = null;

    require_once 'vendor/autoload.php';;
    $conex = new \ClasseGeral\ClasseGeral();

    //Fazendo alteracoes para adaptar classeGeralLocal
    if ($tabela == 'classeGeral') {
        $usarClasseGeralLocal = false;

        $arqClasseLocal = $conex->pegaCaminhoApi() . 'backLocal/classes/classeGeralLocal.class.php';
        if (is_file($arqClasseLocal)) {
            $classe = new ('\\classeGeralLocal')();
            if (method_exists($classe, $funcaoExecutar)) {
                $usarClasseGeralLocal = true;
            }
        }

        if (!$usarClasseGeralLocal) {
            //require_once $_SESSION[session_id()]['caminhoApiLocal'] . 'api/classes/classeGeral.class.php';
            $classe = new ClasseGeral\ClasseGeral();
        }
    } else
        //Fazendo alteracoes para adaptar a APILocal
        //if (is_file($_SESSION[session_id()]['caminhoApiLocal'] . 'backLocal/classes/' . $tabela . '.class.php')) {
        $arq = '';
        $arq = $conex->pegaCaminhoApi() . 'api/backLocal/classes/' . $tabela . '.class.php';

        if (is_file($arq)) {
            require_once($arq);
            $classe = new $tabela();
        } elseif (is_file('classes/' . $tabela . '.class.php')) {
            require_once('classes/' . $tabela . '.class.php');
            $classe = new $tabela();
        }


    $response->getBody()->write($classe->$funcaoExecutar($parametros));
    return $response;
})->add($authMiddleware);

$app->post('/{tabela}/{funcao}', function (Request $request, Response $response, $argumentos) {
    continuar();
    $tabela = $argumentos['tabela'];
    $funcao = $argumentos['funcao'];

    if($tabela == 'classeGeral') {
        $classe = new \ClasseGeral\ClasseGeral();
    } else {
        //Fazendo alteracoes para adaptar a APILocal
        if (is_file($_SESSION[session_id()]['caminhoApiLocal'] . 'backLocal/classes/' . $tabela . '.class.php')) {
            require_once($_SESSION[session_id()]['caminhoApiLocal'] . 'backLocal/classes/' . $tabela . '.class.php');
        } else if (is_file('classes/' . $tabela . '.class.php')) {
            require_once('classes/' . $tabela . '.class.php');
        }
        $classe = new $tabela();
    }
    $response->getBody()->write($classe->$funcao($_POST));
    return $response;
})
    ->add($authMiddleware)
;

//Anexar Arquivos
$app->post('/anexarArquivos', function (Request $request, Response $response, $argumentos) {
    continuar();
    if (is_file($_SESSION[session_id()]['caminhoApiLocal'] . 'backLocal/classes/classeGeralLocal.class.php')) {
        require_once $_SESSION[session_id()]['caminhoApiLocal'] . 'backLocal/classes/classeGeralLocal.class.php';
        $anexar = new classeGeralLocal();
    } else {
        //require_once 'classes/classeGeral.class.php';
        //$anexar = new classeGeral();
        $anexar = new ClasseGeral\ClasseGeral();
    }

    $response->getBody()->write($anexar->anexarArquivos($_POST, $_FILES));
    return $response;
    //return $anexar->anexarArquivos($_POST, $_FILES);
})->add($authMiddleware);

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-Session-Id')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$app->run();
//*/
?>
