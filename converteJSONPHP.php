<form method="post" enctype="multipart/form-data" action="">
    <input type="file" name="variavel" id="">
    <button type="submit">Converter</button>
</form>

<?php

if (isset($_FILES) && sizeof($_FILES) > 0) {
    $q = "\n";

    $caminhoApi = $_SERVER['DOCUMENT_ROOT'];

    $json = file_get_contents($_FILES['variavel']['tmp_name']);
    $array = json_decode($json, true);
    $tabela = isset($array['tabela']) ? $array['tabela'] : '';
    $tabelaConsulta = isset($array['tabelaConsulta']) ? $array['tabelaConsulta'] : $tabela;

    $campoChave = isset($array['campo_chave']) ? $array['campo_chave'] : '';
    $campoChave = isset($array['campoChave']) ? $array['campoChave']  : $campoChave;

    $campoValor = isset($array['campoValor']) ? $array['campoValor'] : '';

    $temp = explode('.', $_FILES['variavel']['name']);
    $novoNome = $temp[0] . '.class.php';

    $arquivo = fopen($caminhoApi . '/api/backLocal/tmpls/' . $novoNome, 'w+');
    fwrite($arquivo, '<?php' . $q);
   // fwrite($arquivo, '@session_start();' . $q);
    //fwrite($arquivo, '$caminho = $_SESSION[session_id()]["caminhoApiLocal"];' . $q);
    //fwrite($arquivo, '$arqcon = $caminho . "api/classes/classeGeral.class.php";' . $q);
    //fwrite($arquivo, 'include_once $arqcon;' . $q);

    fwrite($arquivo, 'class ' . $temp[0] . ' extends \ClasseGeral\ClasseGeral {' . $q);
    fwrite($arquivo, '    private string $funcoes = "BaseArcabouco/funcoes.class.php";' . $q);
    fwrite($arquivo, '    private string $tabela = "' . $tabela . '";' . $q);
    fwrite($arquivo, '    private string $tabelaConsulta = "' . $tabelaConsulta . '";' . $q);
    fwrite($arquivo, '    private string $campoChave = "' . $campoChave . '";' . $q);
    fwrite($arquivo, '    private string $campoValor = "' . $campoValor . '";' . $q);
  //  fwrite($arquivo, '    function __construct(){' . $q);
   // fwrite($arquivo, "        date_default_timezone_set('America/Sao_Paulo');" . $q);
   // fwrite($arquivo, '    }' . $q . $q);

    fwrite($arquivo, 'public function estrutura(){' . $q);
    fwrite($arquivo, '    return ' . var_export($array, true) . ';' . $q);
    fwrite($arquivo, '    }' . $q . '}' . $q);

    fclose($arquivo);
}

//    file_put_contents($caminhoApi . 'apiLocal/tmpls/' . $novoNome, var_export($array, true));
//
//}
