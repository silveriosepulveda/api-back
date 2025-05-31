<?php

use ClasseGeral\ConClasseGeral;

/**
 * Created by PhpStorm.
 * User: Silverio
 * Date: 09/06/2017
 * Time: 14:44
 */

//$arqcon = is_file("../BaseArcabouco/bancodedados/conexao.php") ? "../BaseArcabouco/bancodedados/conexao.php" : "BaseArcabouco/bancodedados/conexao.php";
//require_once $arqcon;

class listas extends \ClasseGeral\ConClasseGeral
{
    private $funcoes = "../BaseArcabouco/funcoes.class.php";

    function __construct()
    {
        clearstatcache();
        $con = new ConClasseGeral;
        date_default_timezone_set('America/Sao_Paulo');
    }

    public function nomesListas()
    {
        //$sql = 'select distinct(nome_apresentar), nome  from listas order by nome_apresentar';
        $sql = 'select distinct nome  from listas order by nome';
        return json_encode($this->retornosqldireto($sql, '', 'listas'));
    }

    public function buscarLista($lista)
    {
        $sql = "select chave_lista, descricao, nome_apresentar from listas where nome = '$lista' order by descricao";
        return json_encode($this->retornosqldireto($sql, '', 'listas', false, false));
    }

    public function excluirLista($chave_lista)
    {
        $chave = $chave_lista;

        if (!$this->objetoemuso('listas', 'chave_lista', $chave_lista)) {
            $chave = $this->exclui('listas', 'chave_lista', $chave_lista);
        }
        $retorno = array('chave' => $chave);
        return json_encode($retorno);
        //*/
    }

    public function alterarLista($lista)
    {
        $lista = json_decode($lista['dados'], true);
        $chave = $this->altera('listas', $lista, $lista['chave_lista']);
        return json_encode(array('chave' => $chave));
    }

    public function substituirLista($parametros)
    {
        $p = $parametros;

        $campo_chave = 'chave_' . strtolower($p['nome_lista']);

        //primeiro eu seleciono os relacionamentos na view relacionamentos
        $sqlv = "select tabela_secundaria from view_relacionamentos where campo_secundario = '$campo_chave' and tabela_principal = 'listas'";
        $tabelas = $this->retornosqldireto($sqlv);

        $dataBase = $this->pegaDataBase('listas');

        foreach ($tabelas as $key => $val) {
            $tabela = $val['tabela_secundaria'];
            $campoChaveTabela = $this->campochavetabela($tabela);
            $sqll = "UPDATE $tabela SET $campo_chave = $p[chave_substituir] WHERE $campo_chave = $p[chave_lista] and $campoChaveTabela > 0";

            $res = $this->executasql($sqll, $dataBase);
        }

        $sqld = "DELETE FROM listas WHERE chave_lista = $p[chave_lista]";
        $resd = $this->executasql($sqld, $dataBase);
        return json_encode(array('chave' => 0));
        //*/
    }
}


//$t = new classeGeral();
//$t->logar(array('login' => 0, 'senha' => 'ttybx9b4'));
//echo json_encode(array('login' => base64_encode(0), 'senha' => base64_encode('ttybx9b4')));
?>
