<?php
if (!isset($_POST) || sizeof($_POST) == 0) { ?>
    <meta charset="utf-8">
    <form action="montaEstrutura.php" method="post" enctype="multipart/form-data">
        <label>Diretório</label>
        <input type="text" name="diretorio" size="30">
        <br>
        <label>Nome Usual</label>
        <input type="text" name="nomeUsual" size="30">
        <br>
        <label>Tabela</label>
        <input type="text" name="tabela" size="30">
        <br>
        <label>Arquivo</label>
        <input type="text" name="arquivo" size="30">
        <br>
        <label>Controller</label>
        <input type="text" name="controller" size="30">
        <br>
        <label>Tipo Estrutura</label>
        <select name="tipoEstrutura">
            <option value="padrao">Padrão</option>
            <option value="consultaDireta">Consulta Direta</option>
            <option value=""></option>
            <option value=""></option>
        </select>

        <br>
        <input type="submit" value="Criar Arquivos">
    </form>

    <?php
} else if (isset($_POST) && sizeof($_POST) > 0) {
    @session_start();
    $caminhoApi = @$_SESSION[session_id()]['caminhoApiLocal'];

    $nomeUsual = $_POST['nomeUsual'];
    $tabela = $_POST['tabela'];

    $arquivoHTML = $_POST['arquivo'] . '.html';
    $arquivoController = $_POST['arquivo'] . '.js';
    $arquivoJS = $_POST['arquivo'] . '.tmpl.json';
    $diretorioCriar = $caminhoApi . 'src/frontLocal/tmpls';
    $diretorio = join('/', explode('-', $_POST['diretorio']));
    $controller = $_POST['controller'];
    $tipoEstrutura = $_POST['tipoEstrutura'];


    $html = '<meta charset="utf-8">
        <script src="'.$diretorio.'/'.$arquivoController.'"></script>
        <div ng-controller="'.$controller.'Ctrl">
            <estrutura-gerencia url-template="'.$diretorio.'/'.$arquivoJS.'"></estrutura-gerencia>
        </div>';

    $controllerCtrl = $controller.'Ctrl';
    $codigoController = "app.controller('$controllerCtrl', function(){});";

    require __DIR__ . '/vendor/autoload.php';
    $con = new \ClasseGeral\conClasseGeral();
    $tbInfo = new \ClasseGeral\TabelasInfo();
    //require_once('BaseArcabouco/bancodedados/conexao.php');
    //$con = new conexao();
    $dataBase = $con->pegaDataBase($tabela);

    $e = array();

    $e['tipoEstrutura'] = $tipoEstrutura;
    $e['tabela'] = $tabela;
    $e['campo_chave'] = strtolower($tbInfo->campochavetabela($tabela));
    $e['raizModelo'] = strtolower($controller);
    $e['textoPagina'] = 'Gerenciamento de ' . $nomeUsual;
    $e['textoNovo'] = 'Incluir ' . $nomeUsual;
    $e['nomeUsual'] = $nomeUsual;
    $e['textoFormCadastro'] = 'Inclusao de ' . $nomeUsual;
    $e['textoFormAlteracao'] = 'Alteracao de ' . $nomeUsual;
    $e['ocultarBotoesSuperiores'] = true;
    $e['todosCamposMaiusculo'] = true;
    $e['todasEtiquetasEmbutidas'] = true;
    $e['filtrarAoIniciar'] = true;
    

    $e['listaConsulta'] = '{}';

    $campos_tabela = array_change_key_case($tbInfo->campostabela($tabela), CASE_LOWER);
//print_r($campos_tabela);
    $e['campos'] = array();

    foreach ($campos_tabela as $key => $val) {
        $temp = array();
        if (substr($val['campo'], 0, 5) == 'CHAVE') {
            $temp['tipo'] = "oculto";
        } else {
            $temp['texto'] = '';
            $temp['sm'] = '2';
        }


        $e['campos'][$key] = $temp;
    }


    $estrutura = json_encode($e);
    
    echo $diretorioCriar;

    file_put_contents($diretorioCriar.'/'.$arquivoJS, $estrutura);
    file_put_contents($diretorioCriar.'/'.$arquivoHTML, $html);
    file_put_contents($diretorioCriar.'/'.$arquivoController, $codigoController);
}
?>