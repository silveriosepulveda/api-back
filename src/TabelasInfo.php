<?php
namespace ClasseGeral;

class TabelasInfo extends \ClasseGeral\ClasseGeral{
    /**
     * Retorna o campo chave de uma tabela a partir do nome da tabela.
     *
     * @param string $tabela Nome da tabela.
     * @param array $dados (Opcional) Dados adicionais para a busca da chave.
     * @return string Campo chave da tabela.
     */
    public function campochavetabela($tabela, $dados = array())
    {
        $dataBase = $this->pegaDataBase($tabela);
        $tabela = $this->nometabela($tabela);

        if (isset($dados['campo_chave']) && $dados['campo_chave'] != '') {
            return $dados['campo_chave'];
        } else if (isset($dados['campoChave']) && $dados['campoChave'] != '') {
            return $dados['campoChave'];
        } else {
            $this->conecta($dataBase);
            $configuracoesTabela = $this->buscaConfiguracoesTabela($tabela);

            if (isset($configuracoesTabela['campoChave'])) {
                return $configuracoesTabela['campoChave'];
            } else {
                $base = $dataBase;
                $sql = "SELECT c.COLUMN_NAME AS chave_primaria FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE c";
                $sql .= " WHERE c.TABLE_SCHEMA = '$base' AND c.TABLE_NAME = '$tabela'";
                $sql .= " AND c.CONSTRAINT_NAME = 'PRIMARY' ";

                $lin = $this->retornosqldireto($sql, '', $tabela)[0];
                return $lin['chave_primaria'];
            }
        }
    }

    /**
     * Retorna o nome da tabela a partir do nome da tabela (pode conter prefixos como 'view').
     *
     * @param string $tabela Nome da tabela.
     * @return string Nome da tabela sem prefixos.
     */
    public function nometabela($tabela)
    {
        $tabela = strtolower((string)$tabela);
        if (substr($tabela, 0, 4) == 'view') {
            $tabela = trim(substr($tabela, 5, 99));
        }
        return $tabela;
    }

    /**
     * Busca as configurações de uma tabela a partir do nome da tabela.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @return array Configurações da tabela.
     */
    public function buscaConfiguracoesTabela($tabela)
    {
        $caminhoAPILocal = $this->pegaCaminhoApi();
        $configuracoesTabela = [];

        if (is_file($caminhoAPILocal . 'api/backLocal/classes/configuracoesTabelas.class.php')) {
            require_once $caminhoAPILocal . 'api/backLocal/classes/configuracoesTabelas.class.php';


            $configuracoesTabelaTemp = new('\\configuracoesTabelas')();

            if (method_exists($configuracoesTabelaTemp, $tabela)) {
                $configuracoesTabela = $configuracoesTabelaTemp->$tabela();
            }

            if (isset($configuracoesTabelaTemp->valoresConsiderarDisponivel))
                $configuracoesTabela['valoresConsiderarDisponivel'] = $configuracoesTabelaTemp->valoresConsiderarDisponivel;
        }
        return $configuracoesTabela;
    }

    /**
     * Retorna os campos de uma tabela a partir do nome da tabela.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @param string $dataBase (Opcional) Nome da base de dados.
     * @param string $tiporetorno (Opcional) Tipo de retorno desejado.
     * @return array Lista de campos da tabela.
     */
    public function campostabela($tabela, $dataBase = '', $tiporetorno = 'padrao', $origem = '')
    {
        $tabela = is_string($tabela) ? strtolower($tabela) : '';

        $configTabela = $this->buscaConfiguracoesTabela($tabela);

        $retorno = array();

        if (isset($this->camposTabelas[$tabela]) && sizeof($this->camposTabelas[$tabela]) > 0) {
            return $this->camposTabelas[$tabela];
        } else {
            $dataBase = $dataBase != '' ? $dataBase : $this->pegaDataBase($tabela);

            $this->conecta($dataBase);

            $base = $dataBase; //strtolower($this->MyBase);
            $sql = "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM `INFORMATION_SCHEMA`.`COLUMNS`";
            $sql .= "WHERE `TABLE_SCHEMA` = '$base' AND `TABLE_NAME`='$tabela' ORDER BY ORDINAL_POSITION";

            $res = $this->executasql($sql, $dataBase);

            while ($lin = $this->retornosql($res)) {
                //print_r($lin);
                $tamanho = '';
                $tam = explode('(', $lin['COLUMN_TYPE']);
                if (sizeof($tam) > 1) {
                    $tam1 = explode(')', $tam[1]);
                    $tamanho = $tam1[0];
                }

                $campo = $lin['COLUMN_NAME'];
                $tipo = isset($configTabela['campos'][$campo]['tipo']) ? $configTabela['campos'][$campo]['tipo'] : $lin['DATA_TYPE'];

                $tipoConsulta = isset($configTabela['campos'][$campo]['tipoConsulta']) ? $configTabela['campos'][$campo]['tipoConsulta'] : '';


                if ($tiporetorno == 'padrao') {
                    $linha = array('campo' => $campo, 'tipo' => $tipo, 'tamanho' => $tamanho, 'tipoConsulta' => $tipoConsulta);
                    $retorno[$lin['COLUMN_NAME']] = $linha;
                } else if ($tiporetorno == 'camponachave') {
                    $linha = array('tipo' => $tipo, 'tamanho' => $tamanho);
                    $retorno[$campo] = $linha;
                }
            }

            $this->camposTabelas[$tabela] = $retorno;
            return $retorno;
        }

        //*/
    }

    /**
     * Retorna as tabelas que possuem um determinado campo.
     *
     * @param string $campo Nome do campo a ser pesquisado.
     * @return array Tabelas que possuem o campo.
     */
    public function tabelasPorCampo($campo)
    {
        $sql = "SELECT table_name as tabela FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'central_resultados_site'
            AND column_name = '$campo'";
        return $this->retornosqldireto($sql);
    }
}
