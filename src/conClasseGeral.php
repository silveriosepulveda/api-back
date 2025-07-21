<?php

namespace ClasseGeral;

/**
 * Classe base para operações de conexão e manipulação de dados gerais.
 * 
 * Responsável por fornecer métodos utilitários para conexão com banco de dados,
 * manipulação de sessões, validação de campos obrigatórios, entre outros.
 */

if (isset($_SESSION[session_id()]['caminhoApiLocal']))
    include $_SESSION[session_id()]['caminhoApiLocal'] . 'api/backLocal/classes/dadosConexao.class.php';
else
    include $_SERVER['DOCUMENT_ROOT'] . '/api/backLocal/classes/dadosConexao.class.php' ;


class ConClasseGeral extends dadosConexao
{
    /**
     * Objeto de configuração do banco de dados.
     * @var mixed
     */

    /**
     * Mapeamento de sexo por extenso.
     * @var array
     */
    public $sexoporextenso = array('M' => 'Masculino', 'F' => 'Feminino');

    /**
     * Mapeamento de tipo de pessoa.
     * @var array
     */
    public $tipoPessoa = array('F' => 'Física', 'J' => 'Jurídica');

    /**
     * Extensões de arquivos de imagem suportadas.
     * @var array
     */
    public $extensoes_imagem = array('jpg', 'jpeg', 'gif', 'png');

    /**
     * Extensões de arquivos suportadas.
     * @var array
     */
    public $extensoes_arquivos = array('doc', 'docx', 'pdf', 'xls', 'xlsx', 'txt', 'rar');

    /**
     * Quebra de linha padrão.
     * @var string
     */
    public $q = "\n";

    /**
     * Array de conexões abertas.
     * @var array
     */
    public $Conexoes;

    /**
     * Nome da base de dados.
     * @var string
     */
    private $base;

    /**
     * Cache de campos das tabelas.
     * @var array
     */
    private $camposTabelas = [];

    /**
     * Retorna o usuário logado na sessão.
     * @return mixed Usuário logado ou null.
     */


    public function __construct()
    {
        parent::__construct();
        $teste = $this->bases;
    }

    public function buscaUsuarioLogado()
    {     
        $sessao = new \ClasseGeral\ManipulaSessao();
        return $sessao->pegar('usuario');        
    }

    /**
     * Carrega funções utilitárias do sistema (placeholder).
     */
    public function carregarFuncoes()
    {
        // $caminho = $this->pegaCaminhoFuncoes();
        // require_once($caminho);
    }

    /**
     * Retorna o caminho das funções utilitárias (placeholder).
     * @return string|null
     */
    public function pegaCaminhoFuncoes()
    {
        // return $this->pegaCaminhoApi() . 'api/BaseArcabouco/funcoes.class.php';
    }

    /**
     * Retorna o caminho base da API local.
     * @return string Caminho absoluto da API local.
     */
    public function pegaCaminhoApi()
    {
        if (isset($_SESSION[session_id()]['caminhoApiLocal']))
            return $_SESSION[session_id()]['caminhoApiLocal'];
        else
            return $_SERVER['DOCUMENT_ROOT'] . '/';
    }

    /**
     * Valida campos obrigatórios de acordo com a configuração informada.
     *
     * @param array $configuracao Configuração dos campos obrigatórios.
     * @param array $dados Dados a serem validados.
     * @param array $retorno (Opcional) Array de retorno para campos inválidos.
     * @return array Lista de campos obrigatórios não preenchidos.
     */
    public function validarCamposObrigatorios($configuracao, $dados, $retorno = [])
    {
        if (!is_array($configuracao) || !is_array($configuracao['camposObrigatorios']))
            return [];

        $camposIgnorar = [];
        $camposObrigatorios = $configuracao['camposObrigatorios'];

        if (isset($camposObrigatorios['ignorarObrigatorio'])) {
            require_once $this->pegaCaminhoApi() . 'api/BaseArcabouco/funcoes.class.php';
            $compara = new manipulaValores();
            $camposIgnorar = $camposObrigatorios['ignorarObrigatorio'];
            unset($camposObrigatorios['ignorarObrigatorio']);
        }

        foreach ($camposObrigatorios as $campo => $tipo) {
            if (!is_array($tipo) && (!isset($dados[$campo]) or ($dados[$campo] == '' || $dados[$campo] == 'undefined' || $dados[$campo] == 'null'))) {
                if (!isset($camposIgnorar[$campo])) {
                    $retorno[$campo] = $tipo;
                } else {
                    if (sizeof($camposIgnorar[$campo]) == 1) {
                        //Neste caso sera ignorado apenas com um valor
                        $val = $camposIgnorar[$campo][0];
                        if (!isset($dados[$val['campo']]) || !$compara->compararValor($dados[$val['campo']], $val['operador'], $val['valor'])) {
                            $retorno[$campo] = $tipo;
                        }
                    } else if (sizeof($camposIgnorar[$campo]) > 1) {
                        $temp = [];

                        foreach ($camposIgnorar[$campo] as $key => $val) {
                            $ignorar = isset($dados[$val['campo']]) && $compara->compararValor($dados[$val['campo']], $val['operador'], $val['valor']);

                            $tipoIgnorar = isset($val['tipoIgnorar']) ? $val['tipoIgnorar'] : 'e';

                            if (!$ignorar) {
                                //Nao ignorar
                                if ($key == 0) {
                                    //Nao existe ainda pois e o primeiro no
                                    $retorno[$campo] = $tipo;
                                } else if ($key > 0) {
                                    if (!isset($retorno[$campo]) && $tipoIgnorar == 'e') {
                                        //Nao e o primeiro no, mas os anteriores foram ignorados
                                        $retorno[$campo] = $tipo;
                                    }
                                }
                            } else if ($ignorar) {
                                if ($tipoIgnorar == 'ou' && isset($retorno[$campo])) {
                                    unset($retorno[$campo]);
                                }
                            }
                        }
                    }
                }
            } else if (is_array($tipo)) {
                //Nesse caso e um array
                $camposIgnorar = isset($tipo['ignorarObrigatorio']) ? array_merge($camposIgnorar, $tipo['ignorarObrigatorio']) : [];
                //Varrendo o bloco para validar cada no

                foreach (isset($dados[$campo]) ? $dados[$campo] : [] as $keyBloco => $dadosValidarBloco) {
                    foreach ($dadosValidarBloco as $campoValidarBloco => $valorValidar) {
                        //Vendo se o campo e obrigatorio
                        if (isset($camposObrigatorios[$campo][$campoValidarBloco])) {
                            //Caso o campo seja obribatorio valido ele
                            if (!$this->validarCampo($valorValidar)) {
                                $ignorarCampo = isset($camposIgnorar[$campoValidarBloco]) ? $camposIgnorar[$campoValidarBloco] : [];

                                if (sizeof($ignorarCampo) == 1) {
                                    //Neste caso sera ignorado apenas com um valor
                                    $val = $ignorarCampo[0];
                                    $campoBuscarDados = $val['campo'];
                                    $valorComparar = $val['valor'];

                                    $valorCampoIgnorar = '';
                                    if (isset($dados[$campoBuscarDados])) {
                                        $valorCampoIgnorar = $dados[$campoBuscarDados];
                                    } else if (isset($dados[$campo][$campoBuscarDados])) {
                                        $valorCampoIgnorar = $dados[$campo][$campoBuscarDados];
                                    }

                                    if (!$this->validarCampo($valorCampoIgnorar) || !$compara->compararValor($valorCampoIgnorar, $val['operador'], $valorComparar)) {
                                        $retorno[$campo][$keyBloco][$campoValidarBloco] = $tipo[$campoValidarBloco];
                                    }

                                } else if (sizeof($ignorarCampo) > 1) {
                                    $temp = [];
                                    $ignorarGeral = false;

                                    foreach ($ignorarCampo as $keyIgnorar => $val) {
                                        $campoBuscarDados = $val['campo'];
                                        $valorComparar = $val['valor'];

                                        $valorCampoIgnorar = '';
                                        if (isset($dados[$campoBuscarDados])) {
                                            $valorCampoIgnorar = $dados[$campoBuscarDados];
                                        } else if (isset($dados[$campo][$keyBloco][$campoBuscarDados])) {
                                            $valorCampoIgnorar = $dados[$campo][$keyBloco][$campoBuscarDados];
                                        }

                                        $ignorar = $this->validarCampo($valorCampoIgnorar) && $compara->compararValor($valorCampoIgnorar, $val['operador'], $valorComparar);
                                        
                                        $tipoIgnorar = isset($val['tipoIgnorar']) ? $val['tipoIgnorar'] : 'e';

                                        if (!$ignorar) {
                                            //Nao ignorar
                                            if ($keyIgnorar == 0) {
                                                //Nao existe ainda pois e o primeiro no
                                                $temp[$campo][$keyBloco][$campoValidarBloco] = $tipo[$campoValidarBloco];

                                            } else if ($keyIgnorar > 0) {
                                                if (!isset($retorno[$campo][$keyBloco][$campoValidarBloco]) && $tipoIgnorar == 'e') {
                                                    //Nao e o primeiro no, mas os anteriores foram ignorados
                                                    $temp[$campo][$keyBloco][$campoValidarBloco] = $tipo[$campoValidarBloco];
                                                }
                                            }
                                        } else if ($ignorar) {
                                            if ($tipoIgnorar == 'ou' && isset($temp[$campo][$keyBloco][$campoValidarBloco])) {
                                                $ignorarGeral = true;
                                            }
                                        }
                                    }
                                    if (!$ignorarGeral && sizeof($temp) > 0) {
                                        $retorno[$campo] = $temp[$campo];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $retorno;        
    }

    /**
     * Valida se um determinado valor é considerado um campo preenchido.
     *
     * @param mixed $valor Valor a ser validado.
     * @return bool Retorna true se o campo é válido, caso contrário, false.
     */
    public function validarCampo($valor)
    {
        return $valor != '' && $valor != 'undefined' && $valor != 'null';
    }

    /**
     * Verifica duplicidade de cadastro com base nas configurações informadas.
     *
     * @param array $configuracoes Configurações para verificação de duplicidade.
     * @param array $dados Dados a serem verificados.
     * @return bool Retorna true se houver duplicidade, caso contrário, false.
     */
    public function buscarDuplicidadeCadastro($configuracoes, $dados)
    {
        $camposSeparados = isset($configuracoes['camposNaoDuplicar']) ? $configuracoes['camposNaoDuplicar'] : [];
        $camposJuntos = isset($configuracoes['camposNaoDuplicarJuntos']) ? $configuracoes['camposNaoDuplicarJuntos'] : [];

        $tabela = $configuracoes['tabela'];
        $camposTabela = $this->campostabela($tabela);

        $campoChave = isset($configuracoes['campoChave']) ? $configuracoes['campoChave'] : $this->campochavetabela($tabela);
        $chave = isset($dados[$campoChave]) && $dados[$campoChave] > 0 ? $dados[$campoChave] : 0;
        $retorno = false;

        //Verificando Campos Separados
        foreach ($camposSeparados as $campo) {

            if (!is_array($campo) && isset($dados[$campo])) {
                $sqlS = "select $campoChave from $tabela where $campoChave <> $chave";
                $valor = $this->retornavalorparasql($camposTabela[$campo]['tipo'], $dados[$campo]);
                $sqlS .= " and $campo = $valor";
                $sqlS .= isset($camposTabela['disponivel']) ? " and disponivel = 'S' " : '';

                if (sizeof($this->retornosqldireto($sqlS, '', $tabela)) > 0) {

                    $retorno = true;
                }
            } else if (is_array($campo) && isset($dados[$campo['raizModeloCampo']])) {
                $valores = $dados[$campo['raizModeloCampo']];
                if (is_array($valores)) {
                    foreach ($valores as $valorCampo) {
                        $valor = $valorCampo[$campo['campoValor']];
                        $sqlRelacionado = "select $campo[campoChave] from $campo[tabela] where $campo[campoValor] = '$valor' and disponivel = 'S'";
                        $chaveRelacionada = isset($valorCampo[$campo['campoChave']]) ? $valorCampo[$campo['campoChave']] : 0;
                        $sqlRelacionado .= $chaveRelacionada > 0 ? " and $campo[campoChave] <> $chaveRelacionada" : '';
                        if (sizeof($this->retornosqldireto($sqlRelacionado, '', $campo['tabela'], false, false)) > 0) {
                            $retorno = true;
                        }
                    }
                } //Tenho que fazer depois caso nao seja array
            }
        }
        //Fim campos separados

        //Campos Juntos

        if (sizeof($camposJuntos) > 0) {
            $sqlJ = "select $campoChave from $tabela where $campoChave <> $chave";
            foreach ($camposJuntos as $campo) {
                if (isset($dados[$campo])) {
                    $valor = $this->retornavalorparasql($camposTabela[$campo]['tipo'], $dados[$campo]);
                    $sqlJ .= " and $campo = $valor";
                }
            }
            $sqlJ .= isset($camposTabela['disponivel']) ? " and disponivel = 'S' " : '';
            if (sizeof($this->retornosqldireto($sqlJ, '', $tabela)) > 0) {

                $retorno = true;
            }
        }
        return $retorno;
        //*/
    }

    /**
     * Retorna os campos de uma tabela a partir do nome da tabela.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @param string $dataBase (Opcional) Nome da base de dados.
     * @param string $tiporetorno (Opcional) Tipo de retorno desejado.
     * @return array Lista de campos da tabela.
     */
    public function campostabela($tabela, $dataBase = '', $tiporetorno = 'padrao')
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
     * Retorna o nome da base de dados a ser utilizada para uma tabela específica.
     *
     * @param string $tabela Nome da tabela.
     * @param string $dataBase (Opcional) Nome da base de dados.
     * @return string Nome da base de dados.
     */
    public function pegaDataBase($tabela = '', $dataBase = '')
    {
//        $dados_con = $this->pegaCaminhoApi() . 'api/backLocal/classes/dadosConexao.class.php';
//        require_once($dados_con);
        //$this->db = new ('\\dadosConexao')();

        $configuracoesTabela = [];

        if ($tabela != '') {
            $configuracoesTabela = $this->buscaConfiguracoesTabela($tabela);
        }

        if ($dataBase != '' && gettype($dataBase) == 'string' && isset($this->bases[$dataBase]))
            $retorno = $dataBase;
        else
            if ($tabela != '' && isset($configuracoesTabela['dataBase']) && isset($this->bases[$configuracoesTabela['dataBase']]))
                $retorno = $configuracoesTabela['dataBase'];
            else
                //$retorno = $this->conexaoPadrao;
                $retorno = $this->conexaoPadrao;

        return $retorno;
    }

    /**
     * Estabelece uma conexão com o banco de dados.
     *
     * @param string $dataBase (Opcional) Nome da base de dados a ser utilizada na conexão.
     */
    public function conecta($dataBase = '')
    {
        if ($dataBase != '' /*&& !isset($this->Conexoes[$dataBase])*/) {
            if (isset($this->Conexoes[$dataBase])) {
                $this->desconecta($dataBase);
            }

            date_default_timezone_set('America/Sao_Paulo');
            
            $servidor = $this->bases[$dataBase]['servidor'];
            $usuario = $this->bases[$dataBase]['usuario'];
            $senha = $this->bases[$dataBase]['senha'];

            $this->Conexoes[$dataBase] = new \mysqli($servidor, $usuario, $senha, $dataBase);
            
            mysqli_set_charset($this->Conexoes[$dataBase], "utf8");

        } 
    }

    /**
     * Desconecta uma conexão com o banco de dados.
     *
     * @param string $dataBase Nome da base de dados da conexão a ser encerrada.
     */
    public function desconecta($dataBase)
    {
        
    }

    /**
     * Executa uma query SQL no banco de dados.
     *
     * @param string $sql Query SQL a ser executada.
     * @param string $dataBase (Opcional) Nome da base de dados onde a query será executada.
     * @return mixed Resultado da execução da query.
     */
    public function executasql($sql, $dataBase = '')
    {
        $TipoBase = isset($this->TipoBase) ? $this->TipoBase : 'MySQL';
        
        $dataBase = $this->pegaDataBase('', $dataBase);

        $this->conecta($dataBase);


        if ($this->bases[$dataBase]['tipo_base'] === 'MySQL') {
            $con = $this->Conexoes[$dataBase];
            ini_set('error_reporting', '~E_DEPRECATED');
            $con->query('set sql_mode=""');
            $retorno = $con->query($sql);
            if (!$retorno) {                
                $this->desconecta($dataBase);
            }            
        } else if ($TipoBase === 'SQLite') {
            $retorno = $this->ConexaoBase->query($sql);
        }
        return $retorno;
    }

    /**
     * Retorna o próximo registro de um resultado de query.
     * 
     * @param mixed $resultado Resultado da query.
     * @return array|null Retorna os dados do próximo registro ou null se não houver mais registros.
     */
    public function retornosql($resultado)
    {        
        $TipoBase = isset($this->TipoBase) ? $this->TipoBase : 'MySQL';
        if ($TipoBase === 'MySQL') {
            if ($resultado) {
                return $resultado->fetch_assoc();
            }
        } else if ($TipoBase === 'SQLite') {
            return $resultado->fetchArray(SQLITE3_ASSOC);
        }
    }

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
     * Executa uma query SQL diretamente e retorna os resultados processados.
     *
     * @param string $sql Query SQL a ser executada.
     * @param string $acao (Opcional) Ação a ser realizada com os dados retornados.
     * @param string $tabela (Opcional) Nome da tabela relacionada à query.
     * @param string $dataBase (Opcional) Nome da base de dados onde a query será executada.
     * @param bool $mostrarsql (Opcional) Se deve ou não mostrar a query SQL executada.
     * @param bool $formatar (Opcional) Se deve ou não formatar os valores retornados.
     * @return array Resultado da query processado.
     */
    public function retornosqldireto($sql, $acao = '', $tabela = '', $dataBase = '', $mostrarsql = false, $formatar = true)
    {
        $retorno = [];

        $dataBase = $this->pegaDataBase($tabela, $dataBase);

        $campos = $tabela != '' ? array_change_key_case($this->campostabela($tabela), CASE_LOWER) : '';

        if ($acao == 'montar') {
            $sql = $this->montasql($sql);
        }

        if ($mostrarsql) {
            echo $sql;
        }

        $res = $this->executasql($sql, $dataBase);

        $linhasAfetadas = $this->linhasafetadas($dataBase);

        if ($linhasAfetadas == 1) {
            $lin = $this->retornosql($res);
            $retorno[] = array_change_key_case($lin, CASE_LOWER);
        } else if ($linhasAfetadas > 1) {
            while ($lin = $this->retornosql($res)) {
                $retorno[] = array_change_key_case($lin, CASE_LOWER);
            }
        }

        if ($campos != '') {
            foreach ($retorno as $key => $val) {
                foreach ($val as $campo => $valor) {
                    if ((isset($campos[$campo]['tipo']) && $campos[$campo]['tipoConsulta'] != '') || isset($campos[$campo]['tipoConsulta'])) {

                        $tipo = isset($campos[$campo]['tipoConsulta']) && $campos[$campo]['tipoConsulta'] != '' ? $campos[$campo]['tipoConsulta'] : $campos[$campo]['tipo'];

                        $retorno[$key][$campo] = $formatar ? $this->formatavalorexibir($valor, $tipo, false) : $valor;
                    }
                }
            }
        }

        $this->desconecta($dataBase);
        return $retorno;
    }

    /**
     * Função que monta o sql de acordo com os parâmetros passados pelo usuário
     * @access public
     * @param array $p são os parametros a serem consultados, sendo eles
     *      Tabela     - Nome da tabela ou visao que sera consultada
     *      Campos     - Array contendo os nomes dos campos a serem buscados
     *                   ou '*' para todos os campos -- caso nao haja o campo
     *                   serao pesquisados todos os campos da tabela
     *      Comparacao - Array contendo as diversas comparacoes a serem feitas
     *                   cada uma vem encapsulada em um array com os seguintes campos
     *                   $tipo     => tipo de campo
     *                   $campo    => nome do campo a ser comparado
     *                   $operador => Operador lógico ( = > < >= <= like)
     *
     * Depois tenho que ver como funciona para usar o or.
     *
     */

    public function montasql($p, $campo_chavee = '')
    {
        //Vendo se é uma view
        //TP - TABELA PRINCIPAL
        //TS - TABELA RELACIONADA

        $tabela = $p['tabela'];
        $tabelaConsulta = isset($p['tabelaConsulta']) ? $p['tabelaConsulta'] : $tabela;

        $tabelaConsulta = strtolower($tabelaConsulta);
        $campo_chave = '';

        if ($campo_chavee != '') {
            $campo_chave = $campo_chavee;
        } else if ($this->campochavetabela($tabela) != '') {
            $campo_chave = $this->campochavetabela($tabela);
        } else if ($this->campochavetabela($tabelaConsulta) != '') {
            $campo_chave = $this->campochavetabela($tabelaConsulta);
        }

        $campos_tabela = $this->campostabela($tabelaConsulta);

        $sql = 'select ';

        if (isset($p['campos']) and is_array($p['campos'])) {
            foreach ($p['campos'] as $campo) {
                $campos[$campo] = $campo;
            }
        } else {
            $campos = $campos_tabela;
        }

        foreach ($campos_tabela as $campo => $valuesCampo) {
            if (in_array($campo, $campos)) {
                $campos[$campo] = $valuesCampo;
            }
        }

        if (is_array($campos)) {
            foreach ($campos as $campo => $valuesCampo) {
                $campo = strtolower((string)$campo);
                $temp = explode('--', $campo);
                $campo = sizeof($temp) > 1 ? $temp[1] : $campo;

                if (array_key_exists($campo, $campos_tabela) or $campo === '*') {

                    $sql .= $campos_tabela[$campo]['tipo'] == 'json' ? ' JSON_UNQUOTE(' : '';
                    $sql .= sizeof($temp) > 1 ?
                        ' TP.' . $temp[0] . '(' . $campo . ')' :
                        ' TP.' . $campo;
                    $sql .= $campos_tabela[$campo]['tipo'] == 'json' ? ') AS ' . $campo . ',' : ', ';
                } else if (substr($campo, 0, 8) == 'distinct') {//Acrescentando o distinct
                    $campoDistinct = substr(trim($campo), 9, strlen($campo) - 10);
                    $sql .= ' distinct(TP.' . $campoDistinct . '),';
                } else if (substr($campo, 0, 3) == 'sum') {
                    $campoSum = substr(trim($campo), 4, strlen($campo) - 4);
                    $sql .= ' sum(TP.' . $campoSum . ') AS ' . $campoSum . ',';
                } else if (substr($campo, 0, 5) == 'count') {
                    $campoCount = trim(substr($campo, 6, strlen($campo) - 7));
                    $sql .= " count(TP.$campoCount) as $campoCount ";
                }
            }

            $sql = substr($sql, 0, strlen($sql) - 1);
        } else {
            $sql .= 'TP.*';
        }

        $sql = substr($sql, strlen($sql) - 1, 1) == ',' ? substr($sql, 0, strlen($sql) - 1) : $sql;
        $sql .= ' from ' . $tabelaConsulta . ' TP ';

        $sql .= ' where TP.' . $campo_chave . ' >= 0';


        if (isset($p['comparacao'])) {
            //Estou tentando mudar a rotina para funcionar com OR para isso todos os parâmetros exceto o valor
            //passarão a ser um array

            foreach ($p['comparacao'] as $op) {
                if (!is_array($op[0])) { //Neste caso é uma comparação simples

                    $tipo = $op[0] != 'undefined' ? $op[0] : 'varchar';
                    //$campo = strtolower($op[1]);
                    $campo = $op[1];

                    $valor = isset($op[3]) ? $op[3] : '';

                    $operador = isset($op[2]) ? $op[2] : '';
                    if ($valor !== '') {
                        $valor = $valor === 'chave_usuario_logado' ? $this->pegaChaveUsuario() : $this->retornavalorparasql($tipo, $valor, 'consulta');
                    } else {
                        $valor = '';
                    }

                    if ($tipo == 'inArray') {
                        $sql .= ' and TP.' . $op[1] . ' in("' . join('","', $op[3]) . '")';
                    } elseif ($tipo == 'in') {
                        $sql .= ' AND TP.' . strtolower($op[1]) . ' IN (SELECT TS.' . strtolower($op[1]) . ' FROM ' . strtolower($op[2]) . ' TS WHERE TS.' . strtolower($op[3]);
                        $camposTabelaIn = $this->campostabela($op[2]);

                        $tipoComp = $camposTabelaIn[strtolower($op[3])]['tipo'];
                        $valorComparar = $this->retornavalorparasql($tipoComp, $op[5]);

                        if ($op[4] == 'between') {
                            $op[3] = strtolower($op[3]);
                            $tempB = explode('__', $op[5]);
                            $temDi = $tempB[0] != 'undefined' && $tempB[0] != '';
                            $temDf = $tempB[1] != 'undefined' && $tempB[1] != '';

                            if ($temDi) {
                                $di = $this->retornavalorparasql('date', $tempB[0]);
                                $sql .= " >= $di ";
                            }
                            if ($temDf) {
                                $df = $this->retornavalorparasql('date', $tempB[1]);
                                $sql .= $temDi ? " AND TS.$op[3] <= $df" : " <= $df";
                            }
                        } elseif ($tipoComp == 'varchar') {
                            $operadorIn = isset($op[4]) ? $op[4] : ' like ';
                            $valorComparar = preg_replace('/(\'|")/', '', $valorComparar);
                            $sql .= " $operadorIn  '%$valorComparar%'";
                        } else {
                            $sql .= " $op[4] $valorComparar";
                        }

                        if (isset($camposTabelaIn['disponivel'])) {
                            $sql .= " AND TS.disponivel = 'S'";
                        }
                        $sql .= ')';
                    } elseif ($tipo == 'SQL') {
                        $sql .= $campo; //Neste caso o sql esta na segunda posicao do array, por isso e jogada na variavel campo
                    } elseif ($operador == 'like') {
                        $valor = substr($valor, 1, strlen($valor) - 2);
                        $valor = "'%" . $valor . "%'";
                        $sql .= ' AND TP.' . $campo . ' LIKE ' . $valor;
                    } elseif ($operador == 'inicial') {
                        $valor = substr($valor, 1, strlen($valor) - 2);
                        $valor = "'" . $valor . "%'";
                        $sql .= ' AND TP.' . $campo . ' LIKE ' . $valor;
                    } elseif ($operador == 'is' && $valor == "'null'") { //Neste caso é para comparar se o campo é nulo
                        $sql .= ' AND TP.' . $campo . ' IS NULL';
                    } elseif ($operador == 'between') {

                        $tempB = explode('__', $op[3]);
                        $temDi = $tempB[0] != 'undefined' && $tempB[0] != '';
                        $temDf = $tempB[1] != 'undefined' && $tempB[1] != '';

                        $di = $temDi ? $this->retornavalorparasql('date', $tempB[0]) : '';
                        $df = $temDf ? $this->retornavalorparasql('date', $tempB[1]) : '';

                        $di = strlen($di) == 12 ? "'" . substr($di, 1, 10) . ' 00:00:00' . "'" : $di;
                        $df = strlen($df) == 12 ? "'" . substr($df, 1, 10) . ' 23:59:59' . "'" : $df;

                        if ($temDi && !$temDf) {
                            $sql .= " AND TP.$campo >= $di ";
                        } else if (!$temDi && $temDf) {
                            $sql .= " AND TP.$campo <= $df ";
                        } else if ($temDi && $temDf) {
                            $sql .= " AND TP.$campo >= $di AND TP.$campo <= $df";
                        }

                    } else if ($operador == 'in') {
                        $teste = ' AND TP.' . $campo . ' ' . $operador . ' ' . $op[3];
                        $sql .= ' AND TP.' . $campo . ' ' . $operador . ' ' . $op[3];
                    } else {
                        $sql .= ' AND TP.' . $campo . ' ' . $operador . ' ' . $valor;
                    }
                } else if (is_array($op[0])) { //Neste caso é uma comparação utilizando or primeiro vou fazer para duas comparações, depois posso expandir para infinitas
                    $tipos = $op[0];                    
                    $campo = $op[1];
                    $operadores = $op[2];
                    $valores = $op[3];

                    $sql .= ' AND (';

                    foreach ($tipos as $key => $tipo) {
                        if ($key > 0) {
                            $sql .= ' OR ';
                        }
                        if ($operadores[$key] == 'is' && $valores[$key] == "'null'") { //Neste caso é para comparar se o campo é nulo
                            $sql .= ' ' . $campo . ' IS NULL ';
                        } else {
                            $valor = $this->retornavalorparasql($tipos[$key], $valores[$key]);
                            if ($operadores[$key] === 'like') {
                                $valor = substr($valor, 1, strlen($valor) - 2);
                                $valor = "'%" . $valor . "%'";
                            }
                            $sql .= ' ' . $campo . ' ' . $operadores[$key] . ' ' . $valor;
                        }
                    }
                    $sql .= ')';
                }
            }

        }

        if (isset($p['verificarEmpresaUsuario']) && $p['verificarEmpresaUsuario']) {
            @session_start();
            $chave_usuario = $_SESSION[session_id()]['usuario']['chave_usuario'];
            $sql .= " AND TP.CHAVE_EMPRESA IN(SELECT CHAVE_EMPRESA FROM USUARIOS_EMPRESAS WHERE CHAVE_USUARIO = $chave_usuario)";
        }

        //Acrescentei a comparacao do campo chave, pois quando tem ) antes do collate da erro.
        $sql .= " and TP.$campo_chave >= 0 ";//collate utf8_unicode_ci ";
        
        if (isset($p['ordem'])) {
            $temp = strtolower($p['ordem']);
            //Separo por virgula os campos que vao ordenar
            $ordenacao = explode(',', $temp);
            $sqlOrdem = '';

            //varro o array
            foreach ($ordenacao as $key => $campo_o) {
                //Separo pelo espaco, pois pode ter desc na frente
                $ordem = explode(' ', $campo_o);

                if (array_key_exists($ordem[0], $campos_tabela) || $ordem[0] == 'rand()') {
                    $sqlOrdem .= $sqlOrdem == '' ? " order by " : ', ';
                    $sqlOrdem .= isset($ordem[1]) ? " $ordem[0] $ordem[1]" : " $ordem[0]";
                }
            }
            $sql .= $sqlOrdem;
        }


        if (isset($p['limite'])) {
            if (is_array($p['limite'])) {
                $sql .= ' LIMIT ' . $p['limite'][0] . ', ' . $p['limite'][1];
            } else {
                $sql .= ' LIMIT ' . $p['limite'];
            }
        }

        return $sql;        
    }

    /**
     * Retorna a chave de usuário logado na sessão.
     *
     * @return mixed Chave de usuário ou null.
     */
    public function pegaChaveUsuario()
    {
        return isset($_SESSION[session_id()]['usuario']['chave_usuario']) ? $_SESSION[session_id()]['usuario']['chave_usuario'] : null;
    }

    /**
     * Retorna um valor formatado para uso em uma query SQL, de acordo com seu tipo.
     *
     * @param string $tipo Tipo do dado.
     * @param mixed $valor Valor a ser formatado.
     * @param string $origem (Opcional) Origem do dado (consulta, inclusao, alteracao).
     * @param string $campo (Opcional) Nome do campo relacionado ao valor.
     * @return mixed Valor formatado.
     */
    public function retornavalorparasql($tipo, $valor, $origem = 'consulta', $campo = '')
    {        
        if ($valor === 'undefined')
            $valor = null;

        if (($tipo == 'varchar' || $tipo == 'char') && !is_array($valor)) {
                 $valor = "'" . trim(str_replace("'", "\'", $valor), '"') . "'";
        } else if ($tipo == 'longtext' || $tipo == 'text') {     
            if ($valor != 'undefined') {
                $valor = stripslashes($valor);
                //Fazendo esta linha para salvar as ' dentro de '
                $valor = str_replace("'", "\'", $valor);                
                $valor = "'" . $valor . "'";
            } else {
                $valor = 'null';
            }
        } else if ($tipo == 'float' || $tipo == 'decimal' || $tipo == 'real') {
            $valor = $this->configvalor($valor);
        } else if ($tipo == 'int') {
            if ($origem != 'consulta') {
                if ($valor === null || $valor === '') {
                    $valor = 'null';
                } else
                    $valor = (int)$valor;
            } else {
                if ($campo != '')
                    echo $valor . $this->q;

                $valor = ($valor != '' && (int)$valor >= 0) ? (int)$valor : '0';
            }
        } else if ($tipo == 'date') {
            if ($valor != '' && $valor != 'CURRENT_DATE' && $valor != 'undefined') {
                $d = explode('/', $valor);
                if (sizeof($d) > 1) {//Neste caso a data vem da tela
                    $valor = $d[2] . '-' . $d[1] . '-' . $d[0];
                    $valor = "'" . $valor . "'";
                } else {//Neste caso a data j� est� em formato de tabela
                    $valor = $valor;
                }
            } else if ($valor == '' || $valor == 'undefined') {
                $valor = 'null';
            }            
        } else if ($tipo == 'time') {
            if (strtolower($valor) != 'CURRENT_TIME')
                $valor = "'" . $valor . "'";
        } else if ($tipo == 'timestamp') {

            if ($valor != '') {
                if ($valor == 'dataAtual') {
                    $valor = "'" . $this->pegaDataHora() . "'";
                } else {
                    $temp = explode(' ', $valor);
                    if (sizeof($temp) == 2) {
                        $data = $this->retornavalorparasql('date', $temp[0]);
                        $valor = "'" . str_replace("'", '', $data) . ' ' . $temp[1] . "'";
                    }
                }
            } else {
                $valor = 'null';
            }
        } else if ($tipo == 'json') {
            if (!is_array($valor)) {
                $valor = $valor != '' ? "JSON_QUOTE('" . $valor . "') " : 'null';
            } else {
                $valor = $valor != '' ? "JSON_QUOTE('" . json_encode($valor, JSON_UNESCAPED_UNICODE) . "')" : 'null';
            }
        }

        $valor_saida = $valor;
        return $valor_saida;
    }

    /**
     * Configura o valor numérico para o formato adequado ao banco de dados.
     *
     * @param mixed $valor Valor a ser configurado.
     * @return float Valor configurado.
     */
    public function configvalor($valor)
    {
        if ($valor == '' || $valor == 'undefined')
            $valor = 0;
        $temp = $valor;
        $temp = str_replace('.', '', $temp);
        $temp = str_replace(',', '.', $temp);
        return ($temp);
    }

    /**
     * Retorna a data e hora atual formatada.
     *
     * @return string Data e hora atual.
     */
    public function pegaDataHora()
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Retorna o número de linhas afetadas pela última operação no banco de dados.
     *
     * @param string $dataBase (Opcional) Nome da base de dados.
     * @return int Número de linhas afetadas.
     */
    public function linhasafetadas($dataBase = '')
    {
        $TipoBase = isset($this->TipoBase) ? $this->TipoBase : 'MySQL';

        //Depois tenho que altrar.
        $dataBase = $dataBase != '' ? $dataBase : $this->conexaoPadrao;

        return $this->Conexoes[$dataBase]->affected_rows;

        if ($TipoBase === 'MySQL') {
            //return $this->ConexaoBase->affected_rows;
        } else if ($TipoBase === 'SQLite') {
            //return $this->ConexaoBase->data_count($res);
        }
    }

    /**
     * Formata um valor para exibição de acordo com seu tipo.
     *
     * @param mixed $valor Valor a ser formatado.
     * @param string $tipo Tipo do dado.
     * @param bool $htmlentitie (Opcional) Se deve ou não aplicar htmlentities no valor.
     * @return mixed Valor formatado para exibição.
     */
    public function formatavalorexibir($valor, $tipo, $htmlentitie = true)
    {
        $retorno = '';

        if ($tipo == 'int' || $tipo == 'bigint' || $tipo == 'tinyint') {
            $retorno = $valor != '' && $valor != null ? (int)$valor : $valor;

        } else if ($tipo == 'float' || $tipo == 'double' || $tipo == 'decimal' || $tipo == 'real') {
            if (sizeof(explode(',', $valor)) > 1) {                
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            }
            $retorno = $valor != '' ? number_format($valor, 2, ',', '.') : '';

        } else if ($tipo == 'varchar' || $tipo == 'char' || $tipo == 'text') {
            $retorno = $valor != null && $valor != 'undefined' ? $valor : '';
        } else if ($tipo == 'urlYoutube') {
            $retorno = str_replace('watch?v=', 'embed/', $valor);
        } else if ($tipo == 'longtext' || $tipo == 'text') {
            
            if (substr(trim($valor), 0, 1) == '{') {
                $retorno = json_decode(preg_replace('/(\r\n)|\n|\r/', '\\n', $valor), true);
            } else {
                $retorno = $htmlentitie ? htmlentities($valor) : $valor;
                $retorno = str_replace("\'", "'", $valor);
            }
        } else if ($tipo == 'date') {

            if ($valor != '') {
                $temp = explode('/', $valor);
                if (sizeof($temp) > 1) {
                    $retorno = $valor;
                } else {
                    date_default_timezone_set('America/Sao_Paulo');

                    $retorno = date('d/m/Y', strtotime($valor));
                }
            } else {
                $retorno = '';
            }        
        } else if ($tipo == 'time') {
            $retorno = substr($valor, 0, 5);
        } else if ($tipo == 'timestamp') {
            $retorno = $valor != '' ?
                $this->formatavalorexibir(explode(' ', $valor)[0], 'date') . ' ' . $this->formatavalorexibir(explode(' ', $valor)[1], 'time') : '';
        } else if ($tipo == 'varbinary') {
            $retorno = $valor;
        } else if ($tipo == 'json') {            
            $retorno = json_decode(preg_replace('/(\r\n)|\n|\r/', '\\n', $valor), true);
            $retorno = gettype($retorno) == 'string' ? json_decode($retorno, true) : $retorno;
        } else {
            $retorno = '';
        }

        ini_set("display_errors", 1);
        return $retorno;
    }

    /**
     * Busca a estrutura de uma tabela para geração de formulários, relatórios, etc.
     *
     * @param array $parametros Parâmetros para a busca da estrutura.
     * @param string $tipoRetorno (Opcional) Tipo de retorno desejado (json ou array).
     * @return mixed Estrutura da tabela em formato JSON ou array.
     */
    public function buscarEstrutura($parametros, $tipoRetorno = 'json')
    {
        //Para essa funcao funcionar e necessario que a estrutura esteja no arquivo da classe

        $parametros = isset($parametros['parametros']) ? json_decode($parametros['parametros'], true) : $parametros;
        $classeEntrada = !is_array($parametros) ? $parametros : $parametros['classe'];

        $parametrosEnviados = isset($parametros['parametrosEnviados']) ? json_decode(base64_decode($parametros['parametrosEnviados']), true) : [];

        $retorno = [];
        $classe = $this->nomeClase($classeEntrada);

        $caminhoAPILocal = $_SESSION[session_id()]['caminhoApiLocal'];
        $arquivo = $caminhoAPILocal . 'api/backLocal/classes/' . $classe . '.class.php';

        if (file_exists($arquivo)) {
            require_once($arquivo);

            $temp = new $classe();

            $funcaoEstrutura = !is_array($parametros) || !isset($parametros['funcaoEstrutura']) ? 'estrutura' : $parametros['funcaoEstrutura'];

            if (method_exists($temp, $funcaoEstrutura)) {
                $retorno = $temp->$funcaoEstrutura();
            }

            $retorno['caminhoClasse'] = $arquivo;
            $retorno['classe'] = isset($retorno['classe']) ? $retorno['classe'] : $classe;

            if (is_array($parametrosEnviados) && sizeof($parametrosEnviados) > 0) {
                foreach ($parametrosEnviados as $campo => $valores) {
                    $novoCampo = [
                        'texto' => isset($valores['texto']) ? $valores['texto'] : '',
                        'padrao' => isset($valores['valor']) ? $valores['valor'] : '',
                        'tipo' => !isset($valores['texto']) ? 'oculto' : 'texto',
                    ];

                    if (isset($retorno['campos'][$campo])) {
                        $novoCampo['atributos_input']['ng-disabled'] = isset($retorno['campos'][$campo]['atributos_input']['ng-disabled']) ?
                            $retorno['campos'][$campo]['atributos_input']['ng-disabled'] : true;
                    } else {
                        $novoCampo['atributos_input']['ng-disabled'] = true;
                    }
                    $retorno['campos'][$campo] = isset($retorno['campos'][$campo]) ?
                        array_merge($retorno['campos'][$campo], $novoCampo) : $novoCampo;
                }
            }
        }

        $origemCampos = isset($parametros['origem']) ? $parametros['origem'] : 'cadastro';

        $retorno['camposObrigatorios'] = $this->camposObrigatorios($retorno, $origemCampos);

        //Vendo se alguma Acao do Item tem comparacao com o usuario logado
        foreach (isset($retorno['acoesItensConsulta']) ? $retorno['acoesItensConsulta'] : [] as $nome => $val) {

        }


        return $tipoRetorno == 'array' ? $retorno : json_encode($retorno);    
    }

    /**
     * Converte um nome de tabela para o formato de classe em PHP.
     *
     * @param string $tabela Nome da tabela a ser convertida.
     * @return string Nome da classe gerada a partir da tabela.
     */
    public function nomeClase($tabela)
    {
        $temp = explode('_', $tabela);
        $iniciaisExcluir = ['tb', 'tabela', 'table', 'view'];

        foreach ($temp as $valor) {
            if (!in_array($valor, $iniciaisExcluir)) {
                $nomes[] = $valor;
            }
        }

        $classe = '';
        if (sizeof($nomes) > 0) {
            foreach ($nomes as $key => $item) {
                $classe .= $key == 0 ? $item : strtoupper(substr($item, 0, 1)) . substr($item, 1, strlen($item));
            }
        }
        return $classe;
    }

    private function camposObrigatorios($variavel, $origem = 'cadastro', $retorno = [])
    {
        if ($origem == 'cadastro' && !isset($variavel['campos'])) return [];

        if ($origem == 'consulta' && !isset($variavel['listaConsulta'])) return [];

        $variavelVarrer = $origem == 'cadastro' ? $variavel['campos'] : $variavel['listaConsulta'];

        foreach ($variavelVarrer as $campo => $val) {
            if (substr($campo, 0, 5) == 'bloco' && isset($val['variavelSalvar'])) {
                $retorno[$val['variavelSalvar']] = $this->camposObrigatorios($val, $origem, $retorno);
            } else if (isset($val['obrigatorio']) && $val['obrigatorio']) {

                $retorno[$campo] = isset($val['tipo']) ? $val['tipo'] : 'varchar';
                if (isset($val['ignorarObrigatorio'])) {
                    $retorno['ignorarObrigatorio'][$campo] = $val['ignorarObrigatorio'];
                }
            }
        }
        return $retorno;
    }

    /**
     * Monta uma condição SQL do tipo BETWEEN para um campo específico.
     *
     * @param string $campo Campo a ser utilizado na condição.
     * @param string $valor Valores a serem considerados no formato 'valor1__valor2'.
     * @return string Condição SQL montada.
     */
    public function montaSQLBetween($campo, $valor)
    {
        $tempB = explode('__', $valor);
        $temDi = $tempB[0] != 'undefined' && $tempB[0] != '';
        $temDf = $tempB[1] != 'undefined' && $tempB[1] != '';

        $retorno = $campo;
        if ($temDi) {
            $di = $this->retornavalorparasql('date', $tempB[0]);
            $retorno .= " >= $di ";
        }
        if ($temDf) {
            $df = $this->retornavalorparasql('date', $tempB[1]);
            $retorno .= $temDi ? " AND $campo <= $df" : " <= $df";
        }
        return $retorno;
    }

    /**
     * Formata valores para exibição em um relatório, considerando as configurações da tabela.
     *
     * @param string $tabela Nome da tabela cujos valores serão formatados.
     * @param array $valores Valores a serem formatados.
     * @return array Valores formatados.
     */
    public function formatarValoresExibir($tabela, $valores)
    {
        $campos = array_change_key_case($this->campostabela($tabela), CASE_LOWER);

        foreach ($campos as $campo => $val) {
            if (isset($valores[$campo])) {
                $valores[$campo] = $this->formatavalorexibir($valores[$campo], $val['tipo']);
            }
        }
        return $valores;
    }

    /**
     * Busca um campo distinto em uma tabela.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @param string $campo Nome do campo a ser buscado.
     * @return array Valores distintos encontrados.
     */
    public function buscarCampoDistintoTabela($tabela, $campo)
    {
        $retorno = [];
        $campos = $this->campostabela($tabela);
        if (in_array($campo, $campos)) {
            $temp = $this->retornosqldireto(strtolower("SELECT DISTINCT $campo from $tabela ORDER BY $campo"), '', $tabela);
            foreach ($temp as $item) {
            }
        }
    }

    /**
     * Monta os filtros para um relatório a partir dos parâmetros informados.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @param array $filtros Filtros a serem aplicados na consulta.
     * @return array Filtros formatados para exibição.
     */
    public function montaFiltrosRelatorios($tabela, $filtros)
    {
        $campos_tabela = $this->campostabela($tabela);
        $retorno['filtrosExibir'] = '';

        $txt = new ManipulaStrings();
        foreach ($filtros as $key => $val) {
            if ($val['campo'] != '') {
                $retorno['filtrosExibir'] .= $retorno['filtrosExibir'] != '' ? ' e ' : '';

                if ($val['operador'] == 'between') {
                    $temp = explode('__', $val['valor']);
                    $temDi = $temp[0] != 'undefined' && $temp[0] != '';
                    $temDf = $temp[1] != 'undefined' && $temp[1] != '';

                    if ($temDi && $temDf) {
                        $valorExibir = $temp[0] . ' e ' . $temp[1];
                        $operadorExibir = 'Entre:';
                    } else if ($temDi && !$temDf) {
                        $valorExibir = $temp[0];
                        $operadorExibir = 'A Partir de:';
                    } else if (!$temDi && $temDf) {
                        $valorExibir = $temp[1];
                        $operadorExibir = 'Até:';
                    }
                    $retorno['filtrosExibir'] .= $val['texto'] . ' ' . $operadorExibir . ' ' . $valorExibir;
                } else {
                    $operador = $val['operador'] == 'like' ? 'contendo' : $val['operador'];
                    $retorno['filtrosExibir'] .= $val['campo'] . ' ' . $operador . ' ' . $val['valor'];
                    $campo = strtolower($val['campo']);
                }
            }
        }
        return $retorno;
    }

    /**
     * Retorna a quantidade de itens selecionados em uma tabela para um determinado campo e valor.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @param string $campo Nome do campo a ser filtrado.
     * @param mixed $valor Valor a ser filtrado.
     * @return int Quantidade de itens selecionados.
     */
    public function qtditensselecionados($tabela, $campo, $valor)
    {
        $this->conecta();
        $tabela = strtolower((string)$tabela);
        $campo = strtolower($campo);
        $sql = "SELECT COALESCE(COUNT($campo), 0) AS QTD FROM $tabela WHERE $campo = $valor";
        $res = $this->executasql($sql);
        $lin = $this->retornosql($res);
        return $lin["QTD"];
    }

    /**
     * Monta uma cláusula WHERE para uma consulta SQL a partir dos parâmetros informados.
     *
     * @param array $parametros Parâmetros para a montagem da cláusula WHERE.
     * @return string Cláusula WHERE montada.
     */
    public function montaWhereSQL($parametros)
    {
        $p = $parametros;
        $tabela = strtolower($parametros['tabela']);
        $tabela_buscar_chave_primaria = $this->nometabela($tabela);

        $campos_tabela = $this->campostabela($tabela);

        $sql = ' FROM ' . $tabela;
        $campo_chave = $this->campochavetabela($tabela_buscar_chave_primaria);

        $sql .= ' WHERE ' . $campo_chave . ' >= 0';

        if (isset($p['comparacao'])) {
            foreach ($p['comparacao'] as $op) {
                if (!is_array($op[0])) { //Neste caso é uma comparação simples

                    $tipo = $op[0];
                    $campo = strtolower($op[1]);

                    $operador = isset($op[2]) ? $op[2] : '';
                    $valor = isset($op[3]) ? $this->retornavalorparasql($tipo, $op[3]) : '';

                    if ($tipo == 'SQL') {
                        $sql .= $campo; //Neste caso o sql esta na segunda posicao do array, por isso e jogada na variavel campo
                    } elseif ($operador == 'like') {
                        $valor = substr($valor, 1, strlen($valor) - 2);
                        $valor = "'%" . $valor . "%'";
                        $sql .= ' AND ' . $campo . ' LIKE ' . $valor;
                    } elseif ($operador == 'inicial') {
                        $valor = substr($valor, 1, strlen($valor) - 2);
                        $valor = "'" . $valor . "%'";
                        $sql .= ' AND ' . $campo . ' LIKE ' . $valor;
                    } elseif ($operador == 'is' && $valor == "'null'") { //Neste caso é para comparar se o campo é nulo
                        $sql .= ' AND ' . $campo . ' IS NULL';
                    } else {
                        $sql .= ' AND ' . $campo . ' ' . $operador . ' ' . $valor;
                    }
                } else if (is_array($op[0])) { //Neste caso é uma comparação utilizando or primeiro vou fazer para duas comparações, depois posso expandir para infinitas
                    $tipos = $op[0];
                    $campo = strtolower($op[1]);
                    $operadores = $op[2];
                    $valores = $op[3];

                    $sql .= ' AND (';

                    foreach ($tipos as $key => $tipo) {
                        if ($key > 0) {
                            $sql .= ' OR ';
                        }
                        if ($operadores[$key] == 'is' && $valores[$key] == "'null'") { //Neste caso é para comparar se o campo é nulo
                            $sql .= ' ' . $campo . ' IS NULL ';
                        } else {
                            $valor = $this->retornavalorparasql($tipos[$key], $valores[$key]);
                            if ($operadores[$key] === 'like') {
                                $valor = substr($valor, 1, strlen($valor) - 2);
                                $valor = "'%" . $valor . "%'";
                            }
                            $sql .= ' ' . $campo . ' ' . $operadores[$key] . ' ' . $valor;
                        }
                    }
                    $sql .= ')';
                }
            }
        }

        if (isset($p['ordem'])) {
            //Passo para maiusculo
            $temp = strtolower($p['ordem']);
            //Separo por virgula os campos que vao ordenar
            $ordenacao = explode(',', $temp);
            //varro o array
            foreach ($ordenacao as $key => $campo_o) {
                //Separo pelo espaco, pois pode ter desc na frente
                $ordem = explode(' ', $campo_o);

                if (array_key_exists($ordem[0], $campos_tabela)) {
                    $sql .= isset($ordem[1]) ? " ORDER BY $ordem[0] $ordem[1]" : " ORDER BY $ordem[0]";
                }
            }
        }

        if (isset($p['limite'])) {
            if (is_array($p['limite'])) {
                $sql .= ' LIMIT ' . $p['limite'][0] . ', ' . $p['limite'][1];
            } else {
                $sql .= ' LIMIT ' . $p['limite'];
            }
        }
        return $sql;
    }

    /**
     * Retorna o nome das tabelas da base de dados.
     *
     * @return array Lista de tabelas da base de dados.
     */
    public function tabelasbase()
    {
        $array = array();
        $this->conecta();
        $base = $this->MyBase;
        $sql = 'SHOW TABLES FROM ' . $base;
        $res = $this->executasql($sql);
        while ($lin = $this->retornosql($res)) {
            $tabela = $lin['Tables_in_' . $base];
            $array[] = $tabela;
        }
        $array = json_encode($array);
        echo $array;
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

    /**
     * Retorna o valor a ser exibido em uma consulta, formatando-o de acordo com o tipo do campo.
     *
     * @param string $tabela Nome da tabela do campo.
     * @param string $campo Nome do campo.
     * @param mixed $valor Valor a ser exibido.
     * @return mixed Valor formatado para exibição.
     */
    public function valorexibirconsulta($tabela, $campo, $valor)
    {
        $retorno = '';
        $tabela = strtolower((string)$tabela);
        $campo = strtolower($campo);

        $tipo = $this->tipodadocampo($tabela, $campo);
        //Comparando os campos para montar a variável de retorno
        return $this->formatavalorexibir($valor, $tipo);
    }

    /**
     * Retorna o tipo de dado de um campo em uma tabela.
     *
     * @param string $tabela Nome da tabela.
     * @param string $campo Nome do campo.
     * @return string Tipo do dado do campo.
     */
    public function tipodadocampo($tabela, $campo)
    {
        $retorno = '';
        $tabela = strtolower($tabela);
        $campo = strtolower($campo);
        $campos = $this->campostabela($tabela);
        foreach ($campos as $key => $valores) {
            if ($valores['campo'] == $campo)
                $retorno = $valores['tipo'];
        }
        return $retorno;
    }

    /**
     * Verifica se um objeto existe com base nos parâmetros informados.
     *
     * @param array $parametros Parâmetros para a verificação da existência do objeto.
     * @return string JSON com informações sobre a existência do objeto.
     */
    public function objetoexistesimples($parametros)
    {
        $tabela = strtolower($parametros['tabela']);
        $config = $this->buscaConfiguracoesTabela($tabela);
        $tabela = $config['tabelaOrigem'] ?? $tabela;

        $campo = strtolower($parametros['campo']);
        $valor = strtolower($parametros['valor']);
        $chave = isset($parametros['chave']) ? $parametros['chave'] : 0;

        $campoChave = $this->campochavetabela($tabela);
        $valorinformar = isset($parametros['valorinformar']) ? strtolower($parametros['valorinformar']) : $this->campochavetabela($tabela);

        if ($valorinformar !== '') {
            $sql = "SELECT $campo, $campoChave, $valorinformar FROM $tabela WHERE $campo = '$valor'";
        } else {
            $sql = "SELECT $campo, $campoChave FROM $tabela WHERE $campo = '$valor'";
        }

        $campo_chave = $this->campochavetabela($tabela);
        if ($chave > 0) {
            $sql .= " AND $campo_chave <> $chave";
        }

        $obj = $this->retornosqldireto($sql, '', $tabela);

        $retorno['existe'] = 0;
        $retorno['valorinformar'] = '';

        if (sizeof($obj) >= 1) {
            $lin = $obj[0];

            $retorno['existe'] = 1;
            $retorno['valorinformar'] = $valorinformar != '' ? $lin[$valorinformar] : '';
        }

        return json_encode($retorno);
    }

    /**
     * Verifica se um objeto existe em uma tabela com base nos parâmetros informados.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @param string $campo Nome do campo a ser filtrado.
     * @param mixed $valor Valor a ser filtrado.
     * @param mixed $chave (Opcional) Chave primária do registro.
     * @param string $campo_tab_pri (Opcional) Campo da tabela primária.
     * @param mixed $valor_ctp (Opcional) Valor da chave primária.
     * @return bool Retorna true se o objeto existe, caso contrário, false.
     */
    public function objetoexiste($tabela, $campo, $valor, $chave_primaria = '', $campo_tab_pri = '', $valor_ctp = '')
    {
        $tabela = strtolower($tabela);
        $campo = strtolower($campo);
        $campo_tab_pri = strtolower($campo_tab_pri);
        if ($valor_ctp > 0) {
            $sql = "SELECT $campo FROM $tabela WHERE LOWER($campo) = LOWER('$valor')";
            $sql .= " AND $campo_tab_pri = $valor_ctp";
        } else {
            $sql = "SELECT $campo FROM $tabela WHERE $campo = " . $this->retornavalorparasql('varchar', $valor);
        }

        if ($chave_primaria > 0) {
            $campo_chave = $this->campochavetabela($tabela);
            $sql .= " AND $campo_chave != $chave_primaria";
        }

        $res = $this->executasql($sql, $this->pegaDataBase($tabela));
        if ($this->linhasafetadas() > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Verifica se um objeto composto existe em uma tabela com base nos parâmetros informados.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @param array $campos Campos a serem filtrados.
     * @param array $valores Valores a serem considerados na filtragem.
     * @param string $campo_chave (Opcional) Campo chave da tabela.
     * @param mixed $chave_primaria (Opcional) Chave primária do registro.
     * @param string $tipo (Opcional) Tipo de verificação (composto ou simples).
     * @return mixed Retorna o valor da chave composta se existir, caso contrário, 0.
     */
    public function objetoexistecomposto($tabela, $campos, $valores, $campo_chave = '', $chave_primaria = '', $tipo = 'composto')
    {
        $tabela = strtolower($tabela);
        $campo_chave = $campo_chave != '' ? strtolower($campo_chave) : $this->campochavetabela($tabela);

        $sql = "SELECT $campo_chave FROM $tabela WHERE $campo_chave > 0";

        foreach ($campos as $key => $val) {
            $sql .= " AND " . strtolower($val) . " = " . $this->retornavalorparasql('varchar', $valores[$val]);
        }

        if ($chave_primaria > 0) {
            $campo_chave = $this->campochavetabela($tabela);
            $sql .= " AND $campo_chave != $chave_primaria";
        }

        $temp = $this->retornosqldireto($sql);

        if (sizeof($temp) > 0) {
            return $temp[0][strtolower($campo_chave)];
        } else {
            return 0;
        }
    }

    /**
     * Verifica se um registro está em uso em tabelas relacionadas.
     *
     * @param string $tabela_e Nome da tabela a ser consultada.
     * @param string $campo_chave_e Nome do campo chave da tabela.
     * @param mixed $chave_e Valor da chave a ser verificado.
     * @param string $tabela_ignorar (Opcional) Tabela a ser ignorada na verificação.
     * @param bool $exibirsql (Opcional) Se deve ou não exibir a query SQL gerada.
     * @return int Retorna 1 se o registro estiver em uso, caso contrário, 0.
     */
    public function objetoemuso($tabela_e, $campo_chave_e, $chave_e, $tabela_ignorar = 'nenhuma', $exibirsql = false)
    {
        $retorno = 0;
        $tabela = $this->nometabela($tabela_e);
        $campo_chave = strtolower($campo_chave_e);
        $dataBase = $this->pegaDataBase($tabela_e);

        $sql = "select tabela_secundaria, campo_secundario from view_relacionamentos";
        $sql .= " where tabela_principal = '$tabela' and campo_principal = '$campo_chave'";

        //Esta comparacao e principalmente para os casos de tabelas de imagens,
        //onde ao excluir o item principal excluirei tambem as imagens
        if ($tabela_ignorar != 'nenhuma' && $tabela_ignorar != '') {
            $tabela_ignorar = strtolower($tabela_ignorar);
            $sql .= "and tabela_secundaria != '$tabela_ignorar'";
        }

        $res = $this->executasql($sql, $dataBase);

        while ($lin = $this->retornosql($res)) {

            $tabela_sec = $lin['tabela_secundaria'];
            $campo_sec = $lin['campo_secundario'];

            $sql1 = "select $campo_sec from $tabela_sec where $campo_sec = $chave_e";
            $res1 = $this->executasql($sql1, $this->pegaDataBase($tabela));

            if ($this->linhasafetadas() > 0) {
                $retorno = 1;
            }
        }

        if ($exibirsql == true) {
            echo $sql;
        }
        return $retorno;
    }

    /**
     * Retorna os dados de um registro como um array associativo.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @param mixed $chave Valor da chave do registro.
     * @return array Dados do registro.
     */
    public function arraydadostabela($tabela, $chave)
    {
        $tabela = strtolower($tabela);
        $campo_chave = $this->campochavetabela($tabela);
        $chave = $chave;
        //buscando os campos da tabela
        $campos = $this->campostabela($tabela);

        $retorno = array();
        //Montando o sql
        $sql = "SELECT * FROM $tabela WHERE $campo_chave = $chave";
        $res = $this->executasql($sql);
        $lin = $this->retornosql($res);

        foreach ($campos as $key => $valores) {
            $tipo = $valores['tipo'];
            $campo = $valores['campo'];
            //Comparando os campos para montar a variável de retorno
            if ($tipo == 'int' || $tipo == 'float') {
                $retorno[$campo] = $lin[$campo];
            } else if ($tipo == 'varchar' || $tipo == 'char') {
                $retorno[$campo] = $lin[$campo];
            } else if ($tipo == 'longtext') {
                $retorno[$campo] = base64_decode($lin[$campo]);
            } else if ($tipo == 'date') {
                $retorno[$campo] = date('d/m/Y', strtotime($lin[$campo]));
            }
        }
        return $retorno;
    }

    /**
     * Busca um valor em uma tabela com base na chave primária.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @param string $campo Nome do campo a ser buscado.
     * @param string $campo_chave Nome do campo chave da tabela.
     * @param mixed $chave Valor da chave a ser buscada.
     * @return mixed Valor encontrado ou null.
     */
    public function buscaumcampotabela($tabela, $campo, $campo_chave, $chave)
    {
        $tabela = strtolower($tabela);
        $campo = strtolower($campo);
        $campo_chave = strtolower($campo_chave);
        $sql = "SELECT $campo FROM $tabela WHERE $campo_chave = $chave";
        $res = $this->executasql($sql);
        $lin = $this->retornosql($res);
        return $lin[$campo];
    }

    /**
     * Função que retorno a chave de um registro por um ou mais campos
     * @param texto $tabela Tabela que sera buscada
     * @param texto /array $campos Pode ser um campo ou um array com varios
     * @param texto /array $valores Tem que seguir a quantidade de campos
     * @param boolean $mostrarsql Se ira mostrar o sql gerado pela rotina
     * @return integer Retorna a chave do registro
     */
    public function buscachaveporcampos($tabela, $campos, $valores, $tabelaOrigem = '', $mostrarsql = false)
    {
        $campo_chave = $tabelaOrigem != '' ? $this->campochavetabela($tabelaOrigem) : $this->campochavetabela($tabela);
        $camposTabela = $this->campostabela($tabela);

        $s['tabela'] = $tabelaOrigem != '' ? $tabelaOrigem : $tabela;
        $s['tabelaConsulta'] = $tabela;
        $s['tabelaOrigem'] = $tabelaOrigem;
        $s['campos'] = array($campo_chave);
        if (is_array($campos)) {
            foreach ($campos as $key => $campo) {
                $s['comparacao'][] = array('varchar', $campo, '=', trim($valores[$key]));
            }
        } else {
            $s['comparacao'][] = array('varchar', $campos, '=', trim($valores));
        }

        $dataBase = $this->pegaDataBase($s['tabela']);

        $sql = $this->montasql($s);

        $res = $this->executasql($sql, $dataBase);
        $lin = $this->retornosql($res);
        $chave = isset($lin[$campo_chave]) && $lin[$campo_chave] > 0 ? $lin[$campo_chave] : '';
        if ($mostrarsql) {
            echo $sql;
        }
        return $chave;
    }

    /**
     * Funcao que busca um ou mais campos de uma tabela por sua chave
     * @param texto $tabela Tabela que sera buscada
     * @param array $campos Campos que serao buscados
     * @param integer $chave chave do registro a ser buscado
     * @param boolean $mostrarsql Se true retorna o sql da rotina
     * @return array Retorna os dados do registro solicitado em array com os nomes dos campos em
     */
    public function buscacamposporchave($tabela, $campos, $chave, $mostrarsql = false)
    {

        $campo_chave = $this->campochavetabela($tabela);

        $s['tabela'] = $tabela;
        $s['campos'] = sizeof($campos) > 0 ? $campos : '*';
        $s['comparacao'][] = array('int', $campo_chave, '=', $chave);

        $sql = $this->montasql($s);

        $lin = $this->retornosqldireto($sql, '', $tabela)[0];

        if ($mostrarsql) {
            echo $sql;
        }
        return $lin;
    }

    /**
     * Busca dados para alteração em um registro.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @param string $campo_chave (Opcional) Campo chave da tabela.
     * @param mixed $chave (Opcional) Valor da chave do registro.
     * @param bool $mostrarsql (Opcional) Se deve ou não mostrar a query SQL executada.
     * @return array Dados do registro para alteração.
     */
    public function buscadadosalterar($tabela, $campo_chave = '', $chave = 0, $mostrarsql = false)
    {
        $tabela = strtolower($tabela);
        $campo_chave = strtolower($campo_chave);
        $chave = $chave;


        //buscando os campos da tabela
        $campos = $this->campostabela($tabela);
        $retorno = array();
        //Montando o sql
        if ($campo_chave != '') {
            $sql = "SELECT * FROM $tabela WHERE $campo_chave = $chave";
        } else {//Este segundo caso foi acrescentado para paginas únicas
            $sql = "SELECT * FROM $tabela";
        }

        if ($mostrarsql) {
            echo $sql;
        }

        $retorno = $this->retornosqldireto($sql, '', $tabela);

        $retorno = count($retorno) == 1 ? $retorno[0] : array();
        return $retorno;
    }

    /**
     * Funcao que busca apenas um campo do tipo longtext em uma tabela
     * @param type $tabela
     * @param type $campo
     * @return string
     */
    public function buscatextoalterar($tabela, $campo)
    {
        $tabela = strtolower((string)$tabela);
        $campo = strtolower((string)$campo);

        $retorno = array();
        //Montando o sql
        $sql = "SELECT $campo FROM $tabela";

        $res = $this->executasql($sql);
        $lin = $this->retornosql($res);
        $retorno[strtolower($campo)]['valor'] = base64_decode($lin[$campo]);
        $retorno[strtolower($campo)]['tipo'] = 'longtext';
        return $retorno;
    }

    /**
     * Busca dados para alteração em um registro no formato JSON.
     *
     * @param mixed $parametros Parâmetros para a busca dos dados.
     * @return void
     */
    public function buscadadosalterarjson($parametros)
    {
        $p = array();
        if (is_string($parametros)) {
            if (is_string($parametros)) {
                if (is_string($parametros)) {
                    if (is_string($parametros)) {
                        if (is_string($parametros)) {
                            parse_str($parametros, $p);
                        } else {
                            $p = $parametros;
                        }
                    } else {
                        $p = $parametros;
                    }
                } else {
                    $p = $parametros;
                }
            } else {
                $p = $parametros;
            }
        } else {
            $p = $parametros;
        }
        $tabela = strtolower($p['tabela']);
        $campo_chave = strtolower($p['campo_chave']);
        $chave = $p['chave'];

        //buscando os campos da tabela
        $campos = $this->campostabela($tabela);

        $retorno = array();
        //Montando o sql
        $sql = "SELECT * FROM $tabela WHERE $campo_chave = $chave";

        $res = $this->executasql($sql);
        $lin = $this->retornosql($res);


        foreach ($campos as $key => $valores) {
            $tipo = $valores['tipo'];
            $campo = $valores['campo'];
            //Comparando os campos para montar a variável de retorno
            if ($tipo == 'int' || $tipo == 'float') {
                $retorno[strtolower($campo)]['valor'] = $lin[$campo];
            } else if ($tipo == 'varchar' || $tipo == 'char') {
                $retorno[strtolower($campo)]['valor'] = utf8_decode(htmlentities($lin[$campo]));
            } else if ($tipo == 'longtext') {
                $retorno[strtolower($campo)]['valor'] = base64_decode($lin[$campo]);
            } else if ($tipo == 'date') {
                $retorno[strtolower($campo)]['valor'] = date('d/m/Y', strtotime($lin[$campo]));
            }
            $retorno[strtolower($campo)]['tipo'] = $tipo;
        }
        $json = json_encode($retorno);
        echo $json;
    }

    /**
     * Busca dados de um registro para impressão.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @param string $campo_chave Nome do campo chave da tabela.
     * @param mixed $chave Valor da chave do registro.
     * @return array Dados do registro formatados para impressão.
     */
    public function buscadadosimprimir($tabela, $campo_chave, $chave)
    {
        $tabela = strtolower($tabela);
        $campo_chave = strtolower($campo_chave);
        $chave = $chave;

        //buscando os campos da tabela
        $campos = $this->campostabela($tabela);
        $retorno = array();
        //Montando o sql
        $sql = "SELECT * FROM $tabela WHERE $campo_chave = $chave";

        $res = $this->executasql($sql);
        $lin = $this->retornosql($res);

        foreach ($campos as $key => $valores) {
            $tipo = $valores['tipo'];
            $campo = $valores['campo'];
            //Comparando os campos para montar a variável de retorno
            if ($tipo == 'int' || $tipo == 'float') {
                $retorno[strtolower($campo)]['valor'] = $lin[$campo];
            } else if ($tipo == 'varchar' || $tipo == 'char') {
                $retorno[strtolower($campo)]['valor'] = $lin[$campo];
            } else if ($tipo == 'longtext') {
                $texto = base64_decode($lin[$campo]);
                $texto = utf8_decode($texto);
                $retorno[strtolower($campo)]['valor'] = $texto;
            } else if ($tipo == 'date') {
                $retorno[strtolower($campo)]['valor'] = date('d/m/Y', strtotime($lin[$campo]));
            }
            $retorno[strtolower($campo)]['tipo'] = $tipo;
        }
        return $retorno;
    }

    /**
     * Atualiza um registro no banco de dados.
     *
     * @param string $tabela Nome da tabela onde o registro será atualizado.
     * @param array $dados Dados a serem atualizados.
     * @param mixed $chave (Opcional) Chave do registro a ser atualizado.
     * @param bool $mostrarsql (Opcional) Se deve ou não mostrar a query SQL executada.
     * @param bool $inserirLog (Opcional) Se deve ou não inserir um log da operação.
     * @return mixed Retorna a chave do registro atualizado ou 0 em caso de falha.
     */
    public function altera($tabela, $dados, $chave = 0, $mostrarsql = false, $inserirLog = true)
    {        
        //Pegando os campos da tabela
        $tabelaOriginal = $tabela;
        $tabela = $this->nometabela($tabela);
        $dataBase = $this->pegaDataBase($tabelaOriginal);
        $campos = $this->campostabela($tabela);

        //Pegando o campo chave
        $campo_chave = $this->campochavetabela($tabelaOriginal);
        $campo_chavem = strtolower($campo_chave);

        //Iniciando o sql
        $sql = "UPDATE $tabela SET ";
        //Verificando se os campos do formulário coincidem com os campos da tabela

        foreach ($campos as $key => $valores) {
            $tipo = $valores['tipo'];
            $campoT = $valores['campo'];
            $campoD = strtolower($campoT);

//            //Vendo se o campo do formulário existe na tabela, se nao existe eu o removo
            if (array_key_exists($campoD, $dados) && (!is_array($dados[$campoD]) || $tipo == 'json')) {

                $valor = $dados[$campoD];
                if (!is_array($valor)) {
                    if ($campoT == $campo_chave) {
                        $sql .= "$campoT = $valor";
                    } else {
                        if ($valor === 'chave_usuario_logado' || $valor === 'chave_usuario') {
                            $valor = $this->pegaChaveUsuario();
                        } else if ($tipo === 'int' && $valor === 0) {
                            $qtd = $this->echaveestrangeira($tabela, $campoT);
                            if ((int)$qtd > 0) {
                                $valor = 'null';
                            }
                        } else {
                            $valor = $this->retornavalorparasql($tipo, $valor, 'alteracao');
                        }
                        $sql .= ", $campoT = $valor";
                    }
                } else if ($tipo == 'json') {
                    $valor = $this->retornavalorparasql($tipo, $valor, 'alteracao');                    
                    $sql .= ", $campoT = $valor";
                }

            } else {
                //Retirando o campo nao existente no array dados
                unset($dados[$campoD]);
                unset($campos[$campoT]);
                //fazer rotina para inserçao em log pois nao existe na tabela o campo do formulário
                
            }
        }

        $sql .= " WHERE $campo_chave = $dados[$campo_chavem]";

        if ($mostrarsql) {
            echo $sql;
        }

        $sR['tabela'] = $tabelaOriginal;
        $sR['comparacao'][] = ['int', $campo_chave, '=', $dados[$campo_chavem]];
        
        $oldValue = $this->retornosqldireto($sR, 'montar', $tabelaOriginal, false, false)[0] ?? [];

        $res = $this->executasql($sql, $dataBase);

        if ($res) {
            if (!in_array($tabela, $this->tabelasSemLog) && $inserirLog && count($oldValue) > 0) {
                $sN['tabela'] = $tabelaOriginal;
                $sN['comparacao'][] = ['int', $campo_chave, '=', $dados[$campo_chavem]];
                $dadosLog = $this->retornosqldireto($sN, 'montar', $tabelaOriginal)[0];

                $acao = (isset($oldValue['disponivel']) && $oldValue['disponivel'] != 'E') && (isset($dadosLog['disponivel']) && $dadosLog['disponivel'] == 'E') ?
                    'Exclusão A' : 'Alteração';

                $this->incluirLog($tabela, $dados[$campo_chavem], $acao, $oldValue, $dadosLog);
            }
            return $dados[$campo_chavem];
        } else {
            return 0;
        }
    }

    /**
     * Verifica se um campo é chave estrangeira em outra tabela.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @param string $campo Nome do campo a ser verificado.
     * @return int Retorna 1 se o campo é chave estrangeira, caso contrário, 0.
     */
    public function echaveestrangeira($tabela, $campo)
    {
        $sql = "select count(*) as qtd from view_relacionamentos where tabela_secundaria = '$tabela' AND campo_secundario = '$campo'";

        $temp = $this->retornosqldireto($sql, '', 'view_relacionamentos')[0];
        $retorno = $temp['qtd'] > 0;
        return (int)$retorno;
    }

    /**
     * Exclui um registro de uma tabela e, opcionalmente, de tabelas relacionadas.
     *
     * @param string $tabela Nome da tabela de onde o registro será excluído.
     * @param string $campo_chave Nome do campo chave da tabela.
     * @param mixed $chave Valor da chave do registro a ser excluído.
     * @param string $tabela_relacionada (Opcional) Tabela relacionada da qual também será feita a exclusão.
     * @param bool $exibirsql (Opcional) Se deve ou não exibir a query SQL gerada.
     * @return mixed Retorna 0 em caso de sucesso ou o valor da chave em caso de falha.
     */
    public function exclui($tabela, $campo_chave, $chave, $tabela_relacionada = 'nenhuma', $exibirsql = false)
    {
        ini_set("display_errors", 0);
        $dataBase = $this->pegaDataBase($tabela);
        $dataBaseTR = $this->pegaDataBase($tabela_relacionada);
        $tabela = $this->nometabela($tabela);

        $campo_chave = strtolower($campo_chave);

        //Comparando se ha tabela relacionada, que na maioria das vezes sera de imagens
        //tendo, excluo os itens referentes a tabela principal

        if ($tabela_relacionada != '' && $tabela_relacionada != 'nenhuma') {
            $tabela_relacionada = strtolower($tabela_relacionada);
            $campo_chave_relacionada = $this->campochavetabela($tabela_relacionada);
            $sqli = "DELETE FROM $tabela_relacionada WHERE $campo_chave = $chave" .
                " AND $campo_chave_relacionada > 0";
            $oldValor = json_encode($this->retornosqldireto("select * from $tabela_relacionada where $campo_chave = $chave", '', $tabela_relacionada));
            $resi = $this->executasql($sqli, $dataBaseTR);
            if ($resi && !in_array($tabela_relacionada, $this->tabelasSemLog)) {
                $this->incluirLog($tabela_relacionada, 0, 'Exclusão', $oldValor);
            }
        }

        $sql = "DELETE FROM $tabela WHERE $campo_chave = $chave AND $campo_chave > 0"; //nao tirar esta linha

        if ($exibirsql) {
            echo $sql;
        }

        $tempValor = $this->retornosqldireto("select * from $tabela where $campo_chave = $chave", '', $tabela);
        $valor = json_encode($tempValor[0] ?? null, JSON_UNESCAPED_UNICODE);

        $res = $this->executasql($sql, $dataBase);

        if ($res) {
            if (!in_array($tabela, $this->tabelasSemLog)) {
                $this->incluirLog($tabela, $chave, 'Exclusão', $valor);
            }

            if ($tabela_relacionada != 'nenhuma') {
                //Rotina para ver se o registro possui imagens se sim excluo-as
                $caminho = $this->caminhopastausada() . 'imagens/' . strtolower($tabela) . '/' . $chave . '/';
                if (is_dir($caminho)) {
                    require_once '../funcoes.class.php';
                    $dir = new gerenciaDiretorios();
                    $dir->apagadiretorio($caminho);
                }
            }
            return 0;
        } else {
            return $chave;
        }
        ini_set("display_errors", 1);
    }

    /**
     * Insere um registro em uma tabela e, opcionalmente, em tabelas relacionadas.
     *
     * @param string $tabela Nome da tabela onde o registro será inserido.
     * @param array $dados Dados a serem inseridos.
     * @param mixed $chave_primaria (Opcional) Chave primária a ser atribuída ao registro.
     * @param bool $mostrarsql (Opcional) Se deve ou não mostrar a query SQL executada.
     * @param bool $inserirLog (Opcional) Se deve ou não inserir um log da operação.
     * @return mixed Retorna a chave do registro inserido ou 0 em caso de falha.
     */
    public function inclui($tabela, $dados, $chave_primaria = 0, $mostrarsql = false, $inserirLog = true, $formatar = true)
    {
        $tabelasIgnorarChavUsuario = ['acessos', 'usuarios_perfil', 'usuarios_empresas', 'usuarios_empresas_grupos', 'usuarios', 'eventos_sistema'];

        // Fiz esta rotina pois o key do array pode estar em maiúsculo
        $dados = array_change_key_case($dados, CASE_LOWER);

        $tabelaOriginal = $tabela;
        $tabela = $this->nometabela($tabela);
        $dataBase = $this->pegaDataBase($tabelaOriginal);

        //Pegando os campos da tabela
        $campos = $this->campostabela($tabela);

        //Pegando o campo chave
        $campo_chave = $this->campochavetabela($tabelaOriginal, $dataBase);

        //Pegando a chave_primaria
        $nova_chave = $chave_primaria == 0 || $chave_primaria == '' ? $this->proximachave($tabela) : $chave_primaria;
        //Iniciando o sql
        $sql = "insert into $tabela(";

        $temdatacadastro = false;
        $temCampoUsuario = false;

        //Verificando se os campos do formulário coincidem com os campos da tabela
        foreach ($campos as $key => $valores) {
            $tipo = $valores['tipo'];
            $campoT = $valores['campo'];
            $campoD = strtolower($campoT);

            //Vendo se existe o campo DATA_CADASTRO, se existir, eu o incluo no sql

            if ($valores['campo'] == 'data_cadastro') {
                $temdatacadastro = true;
            } else if ($valores['campo'] == 'chave_usuario' && !in_array($tabela, $tabelasIgnorarChavUsuario)) {
                $temCampoUsuario = true;
                unset($campos['chave_usuario']);
                unset($dados['chave_usuario']);
            }

            if ($campoT == $campo_chave) {
                $dados[$campoD] = $nova_chave;
            }

            //Vendo se o campo do formulário existe na tabela
            if (array_key_exists($campoD, $dados)) {
                $sql .= " $campoT,";
            } else {
                //Retirando o campo nao existente no array dados
                unset($dados[$campoD]);
                unset($campos[$key]);
                //fazer rotina para inserçao em log pois nao existe na tabela o campo do formulário
            }
        }

        //tirando a última ','
        $sql = substr($sql, 0, strlen($sql) - 1);

        if ($temdatacadastro) {
            $sql .= ', data_cadastro';
        }

        if ($temCampoUsuario) {
            $sql .= ', chave_usuario';
            @session_start();
            $dados['chave_usuario'] = $this->pegaChaveUsuario();
        }

        $sql .= ')VALUES(';

        //Pondo os valores do insert no sql
        foreach ($campos as $key => $valores) {
            $tipo = trim($valores['tipo']);
            $campoT = $valores['campo'];
            $campoD = strtolower($campoT);

            $valor = $dados[$campoD];

            //Vendo o tipo de dado para fazer o tratamento
            if ($campoT == $campo_chave) {
                $sql .= "$valor";
            } else {
                $einteiro = false;
                $ezero = false;

                     if ($valor === 'chave_usuario_logado') {
                    $valor = $this->pegaChaveUsuario();
                } else {
                    $einteiro = trim($tipo) == 'int';
                    $ezero = (int)$valor === 0;
                    if ($tipo == 'int') {
                        //  echo $tabela . ' -- ' . $key . ' -- ' . $valor . ' -- ' . $tipo . ' --' . $this->retornavalorparasql($tipo, $valor, 'inclusao') . $this->q;
                    }
                    $valor = $formatar ? $this->retornavalorparasql($tipo, $valor, 'inclusao') : $valor;
                }

                if ($einteiro && $ezero) {
                    //Vendo se é inteiro e se é chave_estrangeira se for e o valor for 0 converto-o em null
                    $qtd = $this->echaveestrangeira($tabela, $campoT);

                    if ($qtd > 0) {
                        $valor = 'null';
                    }
                } else if (($tipo == 'varchar' or $tipo == 'longtext') && $valor == "''") {
                    $valor = 'null';
                } else if ($tipo == 'date' && $valor != 'null' && substr($valor, 0, 1) != "'") {
                    $valor = "'$valor'";
                }
                $sql .= ", $valor";
            }
        }

        //Vendo se existe o campo DATA_CADASTRO, se existir, ponho seu valor no sql
        if ($temdatacadastro) {
            $sql .= ', NOW()';
        }

        if ($temCampoUsuario) {
            $sql .= ', ' . $dados['chave_usuario'];
        }

        $sql .= ')';

        if ($mostrarsql) {
            echo $sql;
        }


        if ($this->executasql($sql, $dataBase)) {
            if (!in_array($tabela, $this->tabelasSemLog) && $inserirLog) {
                $dadosLog = $this->retornosqldireto("select * from $tabelaOriginal where $campo_chave = $nova_chave", '', $tabela);
                $dadosLog = $dadosLog[0];
                $this->incluirLog($tabela, $nova_chave, 'Inclusão', '', $dadosLog);
            }
            return $nova_chave;
        } else {
        }

    }

    /**
     * Retorna o próximo valor disponível para uma chave, considerando a tabela e a sequência.
     *
     * @param string $tabela Nome da tabela a ser considerada.
     * @param bool $atualizarSequencia (Opcional) Se deve ou não atualizar a sequência.
     * @return int Próximo valor disponível para a chave.
     */
    public function proximachave($tabela, $atualizarSequencia = false)
    {
        $proxima_chave = 0;
        $tabelaOriginal = $tabela;
        $tabela = $this->nometabela($tabela);

        //A sequencia esta na base principal
        $sql1 = "select chave as chave from sequencias where tabela = '$tabela'";
        $chave = $this->retornosqldireto($sql1, '', 'sequencias');
        
        $proxima_chave_sequencia = sizeof($chave) == 1 ? $chave[0]['chave'] : 1;

        $proxima_chave_tabela = $this->maiorchavetabela($tabelaOriginal) + 1;

        if ($proxima_chave_sequencia == 1 && !($proxima_chave_tabela > $proxima_chave_sequencia) && count($chave) == 0) {
            $sqli = "insert into sequencias(tabela, chave)values('$tabela', $proxima_chave_sequencia)";
            $this->executasql($sqli);
        }

        //Se na tabela é maior que na sequencia recebe o valor da tabela, senao recebe o da sequencia
        if ($proxima_chave_tabela > $proxima_chave_sequencia) {
            //Atualizo a sequencia de acordo com a tabela
            $sql2 = "update sequencias set chave = $proxima_chave_tabela where tabela = '$tabela'";
            $res2 = $this->executasql($sql2);
            $proxima_chave = $proxima_chave_tabela;
        } else if ($proxima_chave_sequencia >= $proxima_chave_tabela) {
                    if ($atualizarSequencia)
                $proxima_chave_sequencia++;
            $sql2 = "update sequencias set chave = $proxima_chave_sequencia where tabela = '$tabela'";
            $res2 = $this->executasql($sql2);
            $proxima_chave = $proxima_chave_sequencia;
        }

        return $proxima_chave;
        //*/
    }

    /**
     * Retorna o maior valor da chave de uma tabela.
     *
     * @param string $tabela Nome da tabela a ser consultada.
     * @return int Maior valor da chave da tabela.
     */
    private function maiorchavetabela($tabela)
    {
        $campo_chave = $this->campochavetabela($tabela);
        $sql = "SELECT MAX($campo_chave) AS ULTIMA_CHAVE FROM $tabela";
        $retorno = $this->retornosqldireto($sql, '', $tabela);
        return isset($retorno[0]['ultima_chave']) ? $retorno[0]['ultima_chave'] : 0;
    }

    /**
     * Retorna o caminho da pasta usada na sessão atual.
     *
     * @return string Caminho da pasta usada.
     */
    static function caminhopastausada()
    {
        @session_start();

        return isset($_SESSION[session_id()]['caminhopastausada']) ?
            $_SESSION[session_id()]['caminhopastausada'] : '';
    }

    /**
     * Ordena o resultado de uma consulta com base em um campo específico.
     *
     * @param string $campoordenar Nome do campo a ser utilizado na ordenação.
     */
    public function ordenaresultadoconsulta($campoordenar)
    {
        require_once '../funcoes.class.php';
        $sessao = new manipulaSessao;
        if ($sessao->pegar('consulta,resultado') != '') {
            //Crio um novo array tendo como chave o campo a ordenar e a key
            $array = $sessao->pegar('consulta,resultado');
            foreach ($array as $key => $val) {
                $novo[$val[$campoordenar] . '---' . $key] = $key;
            }
            //Ordeno o novo array
            ksort($novo);
            foreach ($novo as $valor => $key) {
                $resultado[] = $key;
                $novoresultado[$key] = $array[$key];
            }

            $sessao->setar('consulta,resultado', $novoresultado);
            $json = json_encode($resultado);
            echo $json;
        }
    }

    /**
     * Popula um select com valores de uma tabela.
     *
     * @param array $parametros Parâmetros para a consulta e formatação dos dados do select.
     * @return json Retorna os valores em formato JSON.
     */
    public function jsonpopulaselect($parametros)
    {
        $p = array();
        if (is_string($parametros)) {
            parse_str($parametros, $p);
        } else if (is_array($parametros)) {
            $p = $parametros;
        }
        $array = array();
        $tabela = strtolower($p['tabela']);
        $campo_chave = strtolower($p['campo_chave']);

        //Estes são os campos que serão buscados, podendo ser um ou mais
        $campos_buscar = strtolower($p['campo_valor']);

        $ordem = $p['ordem'] != '' ? strtolower($p['ordem']) : strtolower($p['campo_valor']);

        $campo_chave_tabela_primaria = strtolower($p['campo_chave_tabela_primaria']);
        $valor_chave_tabela_primaria = strtolower($p['valor_chave_tabela_primaria']);

        $sql = "SELECT $campo_chave, $campos_buscar FROM $tabela WHERE $campo_chave > 0 ";
        if ($campo_chave_tabela_primaria != '' && $valor_chave_tabela_primaria > 0) {
            $sql .= " AND $campo_chave_tabela_primaria = $valor_chave_tabela_primaria";
        }

        $sql .= " ORDER BY $ordem";
        //echo $sql;
        $res = $this->executasql($sql);
        while ($lin = $this->retornosql($res)) {
            $valor = '';
            //Populando select com mais de um campo
            $temp = explode(',', $p['campo_valor']);
            if (sizeof($temp) <= 1) {
                //$valor = utf8_decode(htmlentities($lin[$campos_buscar]));
                $valor = $this->formatavalorexibir($lin[$campos_buscar], 'varchar');
            } else if (sizeof($temp) > 1) {

                foreach ($temp as $key => $val) {
                    $val = trim(strtolower($val));
                    $valor .= utf8_decode(htmlentities($lin[$val]));
                    if ($key < sizeof($temp) - 1) {
                        $valor .= ' -- ';
                    }
                }
            }

            $chave = $lin[$campo_chave];
            $array[] = array('chave' => $chave, 'valor' => $valor);
        }
        $json = json_encode($array);
        echo $json;
    }

    /**
     * Completa um campo com sugestões baseadas em um texto informado.
     *
     * @param array $p Parâmetros para a consulta de sugestões.
     * @return void
     */
    public function completacampo($p)
    {
        $mostrarSQL = false;
        $texto = $p['term'];// $_GET['term']; //$p;

        $tabela = strtolower($p['tabela']);
        $campos_tabela = $this->campostabela($tabela);

        $campo_chave = strtolower($p['campo_chave']);
        $campo_valor = strtolower($p['campo_valor']);
        $complemento_valor = isset($p['complemento_valor']) && $p['complemento_valor'] != '' ? strtolower($p['complemento_valor']) : "";

        $campo_valor2 = isset($p['campo_valor2']) ? strtolower($p['campo_valor2']) : '';
        $campo_valor3 = isset($p['campo_valor3']) ? strtolower($p['campo_valor3']) : '';
        $campo_valor4 = isset($p['campo_valor4']) ? strtolower($p['campo_valor4']) : '';

        $campo_imagem = $p['campoImagem'] ?? '';

        $campo_chave2 = isset($p['campo_chave2']) && $p['campo_chave'] != 'undefided' ? strtolower($p['campo_chave2']) : '';
        $chave2 = $p['chave2'] ?? 0;

        $campo_chave3 = isset($p['campo_chave3']) ? strtolower($p['campo_chave3']) : '';
        $chave3 = $p['chave3'] ?? 0;

        $campo_chave4 = isset($p['campo_chave4']) && $p['campo_chave4'] != '' ? strtolower($p['campo_chave4']) : '';
        $chave4 = $p['chave4'] ?? 0;

        $repetirvalores = $p['repetirvalores'] ?? 'N';

        $usarIniciais = $p['usarIniciais'] == 'true' ? $p['usarIniciais'] : false;

        $sql = "SELECT TP.$campo_chave,  TP.$campo_valor";
        $sql .= isset($campos_tabela['nome_apresentar']) ? ', TP.nome_apresentar' : '';

        $sql .= $campo_imagem != '' ? ', TP.' . strtolower($campo_imagem) : '';

        $sql .= $complemento_valor != '' ? " , TP.$complemento_valor" : '';

        $sql .= $campo_valor2 != '' ? " , TP.$campo_valor2" : '';
        $sql .= $campo_valor3 != '' ? " , TP.$campo_valor3" : '';
        $sql .= $campo_valor4 != '' ? " , TP.$campo_valor4" : '';


        $sql .= " FROM $tabela TP WHERE TP.$campo_chave > 0";

        $textoIniciais = $usarIniciais ? '' : '%';

        $sql .= $texto != '' ? " AND LOWER(TP.$campo_valor) like '$textoIniciais" . strtolower($texto) . "%' COLLATE utf8_unicode_ci " : '';
        $chave2 = is_integer($chave2) && $chave2 > 0 ? $chave2 : '"' . $chave2 . '"';

        if ($campo_chave2 != '') {
            $sql .= " AND TP.$campo_chave2 = $chave2";
        }

        if ($campo_chave3 != '') {
            $sql .= " AND TP.$campo_chave3 = $chave3";
        }

        if ($campo_chave4 != '') {
            $sql .= " AND TP.$campo_chave4 = $chave4";
        }

        if (isset($campos_tabela['arquivado'])) {
            $sql .= ' AND TP.arquivado = "N"';
        }
        if (isset($campos_tabela['disponivel'])) {
            $sql .= ' AND TP.disponivel = "S"';
        }

        //Esses campos sao comparados exclusivamente para a Central, depois verei isso.
        if (isset($campos_tabela['ativo']) && $campos_tabela['ativo']['tipo'] != 'char' && $campos_tabela['ativo']['tipo'] != 'varchar') {
            $sql .= ' and TP.ativo = 1 ';
        }

        $ignorarPublicar = isset($p['ignorarPublicar']) && $p['ignorarPublicar'];
        if (isset($campos_tabela['publicar']) && !$ignorarPublicar && $campos_tabela['publicar']['tipo'] != 'char') {
            $sql .= ' and TP.publicar = 1 ';
        }

        $verEmpUsu = isset($p['verificarEmpresaUsuario']) && $p['verificarEmpresaUsuario'] == 'true';

        $usuarioLogado = $this->buscaUsuarioLogado();
        $temEmpUsu = isset($usuarioLogado['empresas']) && count($usuarioLogado['empresas']) > 0 ||
            (isset($usuarioLogado['chave_empresa']) && $usuarioLogado['chave_empresa'] > 0);

        if ($verEmpUsu && sizeof($_SESSION[session_id()]['usuario']['empresas']) > 0) {
            $chave_usuario = $_SESSION[session_id()]['usuario']['chave_usuario'];
            $sql .= " AND TP.chave_empresa IN(SELECT chave_empresa FROM usuarios_empresas WHERE chave_usuario= $chave_usuario)";
        }

        @session_start();
        $caminhoAPILocal = $_SESSION[session_id()]['caminhoApiLocal'];

        $configuracoesTabela = [];
        if (is_file($caminhoAPILocal . '/api/backLocal/classes/configuracoesTabelas.class.php')) {
            require_once $caminhoAPILocal . '/api/backLocal/classes/configuracoesTabelas.class.php';
            $configuracoesTabelaTemp = new ('\\configuracoesTabelas')();

            if (method_exists($configuracoesTabelaTemp, $tabela)) {
                $configuracoesTabela = $configuracoesTabelaTemp->$tabela();
            }
        }

        if (isset($configuracoesTabela['comparacao'])) {
            foreach ($configuracoesTabela['comparacao'] as $comparacao) {
                $sql .= $comparacao[1];
            }
        }

        if ($p['ordenar'] == 'S' || $p['ordenar']) {
            $campoOrdem = isset($p['campoOrdem']) && $p['campoOrdem'] != 'null' ? $p['campoOrdem'] : $campo_valor;
            $sql .= " ORDER BY TP.$campoOrdem";
        } else {
            $sql .= " ORDER BY TP.$campo_chave";
        }

        if ($mostrarSQL)
            echo $sql;

        $dados = $this->retornosqldireto($sql, '', $tabela, false, false);

        $temp = []; //Variável para não deixar repetir valores
        $retorno = [];

        foreach ($dados as $key => $item) {
            $valor = $item[strtolower($campo_valor)];

            //Comparando se o valor já foi lançado, se sim não lanço novamente
            ////Tirei esta comparacao pois podem haver dois nomes iguais
            //e tambem pus o complemento_valor para identificar quando isso acontecer
            if (!array_key_exists($valor, $temp) || $repetirvalores == 'S') {
                $temp[$valor] = $valor;
                $imagem =
                $retorno[] = [
                    'chave' => $item[strtolower($campo_chave)],
                    'valor' => $item[strtolower($campo_valor)],
                    'complemento_valor' => $item[strtolower($complemento_valor)] ?? '',
                    'valor2' => $item[strtolower($campo_valor2)] ?? '',
                    'valor3' => $item[strtolower($campo_valor3)] ?? '',
                    'valor4' => $item[strtolower($campo_valor4)] ?? '',
                    'imagem' => $item[strtolower($campo_imagem)] ?? '',
                ];
            }
        }
        return json_encode($retorno);
        //*/
    }

    /**
     * Completa um campo com sugestões baseadas em um texto informado.
     *
     * @param array $p Parâmetros para a consulta de sugestões.
     * @return void
     */
    public function completacampopornomedecampo($campo)
    {
        $campo = strtolower($campo);
        $texto = $_GET['term'];

        $retorno = array();
        $tabelas = $this->listatabelaspornomedecampo($campo);

        //Iniciando a montagem do sql para buscar os valores
        if (sizeof($tabelas) == 1) {
            $sql = "SELECT DISTINCT($tabelas[0].$campo) AS VALOR FROM $tabelas[0]";
            $sql .= " WHERE $tabelas[0].$campo IS NOT NULL";
            $sql .= " AND LOWER($tabelas[0].$campo) like '" . strtolower($texto) . "%'";
        } else if (sizeof($tabelas) > 1) {
            $sql = "SELECT DISTINCT($tabelas[0].$campo) AS VALOR FROM $tabelas[0]";
            $sql .= " WHERE $tabelas[0].$campo IS NOT NULL";
            $sql .= " AND LOWER($tabelas[0].$campo) like '" . strtolower($texto) . "%'";
            foreach ($tabelas as $key => $tabela) {
                if ($key > 0) {
                    $sql .= " UNION ";
                    $sql .= " SELECT $tabela.$campo AS VALOR FROM $tabela";
                    $sql .= " WHERE $tabela.$campo IS NOT NULL";
                    $sql .= " AND LOWER($tabela.$campo) like '" . strtolower($texto) . "%'";
                }
            }
        }
        $sql .= " ORDER BY VALOR";

        //echo $sql;

        $valor = array();

        $res = $this->executasql($sql);
        if ($this->linhasafetadas() > 0) {
            while ($lin = $this->retornosql($res)) {
                $valor[] = array('valor' => $this->formatavalorexibir($lin['VALOR'], 'varchar', true), 'chave' => 0);
            }
        }

        $json = json_encode($valor);
        echo $json;
    }

    /**
     * Retorna as tabelas que possuem um determinado campo.
     *
     * @param string $campo Nome do campo a ser pesquisado.
     * @return array Tabelas que possuem o campo.
     */
    public function listatabelaspornomedecampo($campo)
    {
        $campo = strtolower($campo);
        $retorno = array();

        $tabelas = array();

        $this->conecta();
        $base = $this->MyBase;
        //Selecionando as tabelas da base que contem o campo passado para a funçao
        $sql1 = "SELECT TABLE_NAME FROM `INFORMATION_SCHEMA`.`COLUMNS`";
        $sql1 .= " WHERE COLUMN_NAME = '$campo' AND SUBSTRING(TABLE_NAME FROM 1 FOR 4) != 'VIEW'";
        $sql1 .= " AND TABLE_NAME != 'LISTAS' AND TABLE_NAME != 'USUARIOS'";
        $sql1 .= " AND `TABLE_SCHEMA` = '$base' ORDER BY ORDINAL_POSITION";


        $res1 = $this->executasql($sql1);
        //Varrendo o resultado e passando os nomes de tabelas para o array tabelas
        while ($lin1 = $this->retornosql($res1)) {
            $tabelas[] = $lin1['TABLE_NAME'];
        }
        return $tabelas;
    }

    /**
     * Converte um texto separado por um elemento em um array.
     *
     * @param string $separador Elemento que separa os valores no texto.
     * @param string $texto Texto a ser convertido.
     * @return array Valores convertidos em um array.
     */
    public function textoparaarray($separador, $texto)
    {
        $retorno = array();
        
        $temp = explode($separador, (string)$texto);
        /* @var $val type string */
        foreach ($temp as $val) {
            if ($val > 0) {
                $retorno[] = $val;
            }
        }
        return $retorno;
    }

    /*
     * Função que pega um texto separado por um elemento e retorna um array
     * @param string $separador é o elemento que separa um texto, pode ser ',' '-' ou outro caracter
     * @param string $texto é o texto a ser convertido, exemplo 1,2,3,4
     * @return array $retorno é o texto convertido em um array
     */

    /**
     * Realiza a soma de dois valores em formato de texto.
     *
     * @param string $valor1 Primeiro valor a ser somado.
     * @param string $valor2 Segundo valor a ser somado.
     * @return string Resultado da soma.
     */
    public function somarTexto($valor1, $valor2)
    {
        $v1 = $this->retornavalorparasql('float', $valor1);
        $v2 = $this->retornavalorparasql('float', $valor2);
        return $this->formatavalorexibir($v1 + $v2, 'float');
    }

    /**
     * Realiza a subtração entre dois valores em formato de texto.
     *
     * @param string $valor1 Valor de onde será subtraído.
     * @param string $valor2 Valor a ser subtraído.
     * @return string Resultado da subtração.
     */
    public function subtrairTexto($valor1, $valor2)
    {
        $v1 = $this->retornavalorparasql('float', $valor1);
        $v2 = $this->retornavalorparasql('float', $valor2);
        return $this->formatavalorexibir($v1 - $v2, 'float');
    }

    /**
     * Realiza a multiplicação de um valor por um fator em formato de texto.
     *
     * @param string $valor Valor a ser multiplicado.
     * @param string $multiplicador Fator multiplicador.
     * @return string Resultado da multiplicação.
     */
    public function multiplicarTexto($valor, $multiplicador)
    {
        $v1 = $this->retornavalorparasql('float', $valor);
        $mult = $this->retornavalorparasql('float', $multiplicador);
        return $this->formatavalorexibir($v1 * $mult, 'float');
    }

    /**
     * Realiza a divisão de um valor por outro em formato de texto.
     *
     * @param string $valor Valor a ser dividido.
     * @param string $divisor Divisor.
     * @return string Resultado da divisão.
     */
    public function dividirTexto($valor, $divisor)
    {
        $v1 = $this->retornavalorparasql('float', $valor);
        $mult = $this->retornavalorparasql('float', $divisor);
        return $this->formatavalorexibir($valor / $divisor, 'float');
    }

    /**
     * Agrupa um array de dados com base em um campo de agrupamento.
     *
     * @param array $array Array a ser agrupado.
     * @param string $campoAgrupamento Campo a ser utilizado para o agrupamento.
     * @param bool $compararQuantidade (Opcional) Se deve ou não comparar a quantidade de itens agrupados.
     * @return array Array agrupado.
     */
    public function agruparArray($array, $campoAgrupamento, $compararQuantidade = true)
    {
        $retornoTemp = array();
        foreach ($array as $item) {
            if (isset($item[$campoAgrupamento])) {
                if (sizeof($item) == 1) {
                    $retornoTemp[$item[$campoAgrupamento]] = $item;
                } else {
                    $retornoTemp[$item[$campoAgrupamento]][] = $item;
                }
            }
        }

        $retorno = array();
        foreach ($retornoTemp as $campoAgr => $item) {
            if (sizeof($item) == 1 && $compararQuantidade) {
                $retorno[$campoAgr] = $item[0];
            } else {
                $retorno[$campoAgr] = $item;
            }
        }
        return $retorno;
    }

    /**
     * Aplica medidas de segurança contra SQL Injection em um texto.
     *
     * @param string $texto Texto a ser protegido.
     * @return string Texto protegido.
     */
    public function antiInjection($texto)
    {
        $retorno = preg_replace("/( from | alter table | select | insert | delete | update | where | drop table | show tables |\*|--|\\\\)/i", "", $texto);

        $retorno = trim($retorno);
        $retorno = strip_tags($retorno);
        $retorno = (htmlspecialchars($retorno)) ? $retorno : addslashes($retorno);
        return $retorno;
    }

    /**
     * Retorna a data e hora atual formatada.
     *
     * @return array Array contendo a data e a hora atual.
     */
    public function dataHora()
    {
        return [
            'data' => date('d/m/Y'),
            'hora' => date('H:m:s')
        ];
    }

    /**
     * Adiciona um valor em um array associativo após uma chave específica.
     *
     * @param array $array Array original.
     * @param string $chaveInserirApos Chave após a qual o novo valor será inserido.
     * @param string $nomeNovaKey Nome da nova chave a ser inserida.
     * @param mixed $novoValor Valor a ser inserido.
     * @return array Novo array com o valor adicionado.
     */
    public function incluirEmArray($array, $chaveInserirApos, $nomeNovaKey, $novoValor)
    {
        $novo = [];
        foreach ($array as $key => $valores) {
            $novo[$key] = $valores;
            if ($key == $chaveInserirApos) {
                $novo[$nomeNovaKey] = $novoValor;
            }
        }
        return $novo;
    }

    /**
     * Cria uma instância de uma classe a partir do nome da classe.
     *
     * @param string $classe Nome da classe a ser instanciada.
     * @return mixed Instância da classe ou false em caso de falha.
     */
    public function criaClasseTabela($classe)
    {
        $caminhoApiLocal = $this->pegaCaminhoApi();
        $arquivo = $caminhoApiLocal . 'api/backLocal/classes/' . $classe . '.class.php';
        if (is_file($arquivo)) {
            require_once $arquivo;
            return new $classe();
        } else {
            return false;
        }
    }

    /**
     * Verifica se uma função existe em uma classe e, se necessário, inclui o arquivo da classe.
     *
     * @param mixed $classe Classe ou nome da classe a ser verificada.
     * @param string $funcao Nome da função a ser verificada.
     * @return bool Retorna true se a função existe, caso contrário, false.
     */
    public function criaFuncaoClasse($classe, $funcao)
    {
        if (gettype($classe) == 'string' && !class_exists($classe)) {
            $arquivoClasse = $this->pegaCaminhoApi() . 'api/backLocal/classes/' . $classe . '.class.php';
            if (file_exists($arquivoClasse)) {
                require_once $arquivoClasse;
                $classe = new $classe();
            }
        }

        if ($classe != '' && method_exists($classe, $funcao)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Retorna a URL base do sistema.
     *
     * @return string URL base do sistema.
     */
    public function pegaUrlBase()
    {
        $var = $_SERVER;
        if (isset($var['HTTPS']) && $var['HTTPS'] == 'on') {
            return 'https://' . $var['HTTP_HOST'] . '/';
        } else {
            return 'http://' . $var['HTTP_HOST'] . '/';
        }
    }

    /**
     * Gera uma chave aleatória em formato de string.
     *
     * @param int $tamanho (Opcional) Tamanho da chave a ser gerada.
     * @param bool $criptografar (Opcional) Se deve ou não criptografar a chave gerada.
     * @return string Chave gerada.
     */
    function gerarKeyString($tamanho = 10, $criptografar = true)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $tamanho; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        $retorno = $criptografar ? base64_encode($randomString) : $randomString;

        return $retorno;
    }

    /**
     * Adiciona um AND a uma cláusula SQL, se necessário.
     *
     * @param string $sql Cláusula SQL original.
     * @return string Cláusula SQL com o AND adicionado, se necessário.
     */
    private function adicionaAND($sql)
    {
        $sqlLocal = trim($sql);
        $inicioCopia = strlen($sqlLocal) - 3;
        $tamanho = strlen($sqlLocal);


        $copiaSQLFinal = substr($sqlLocal, $inicioCopia, 3);
        $substituir = $copiaSQLFinal == 'AND' || trim(substr($sqlLocal, $tamanho - 5, 5)) == 'AND (' || trim(substr($sqlLocal, $tamanho - 2, 2) == 'OR');
        return $substituir ? '' : ' AND ';
    }

    public function incluirLog($tabela, $chave_tabela, $acao, $valorAnterior = [], $valorNovo = '')
    {
        $incluirLog = in_array($acao, ['Inclusão', 'Exclusão', 'Exclusão A']);
        $chave = 0;
        $chaveUsuario = $this->pegaChaveUsuario();
        $chaveUsuario = $chaveUsuario > 0 ? $chaveUsuario : 'null';

        if ($acao == 'Alteração') {
            foreach ($valorAnterior as $campo => $valor) {
                if ($valor != $valorNovo[$campo]) {                    
                    $incluirLog = true;
                }
            }
        }

        if ($incluirLog) {
            $valorNovoInserirLog = [];
            $valorAnteriorInserirLog = [];
            foreach (isset($valorNovo) && is_array($valorNovo) ? $valorNovo : [] as $campo => $valor) {
                if (!isset($valorAnterior[$campo]) || $valor != $valorAnterior[$campo]) {
                    $valorNovoInserirLog[$campo] = $valor;
                    $valorAnteriorInserirLog[$campo] = isset($valorAnterior[$campo]) ? $valorAnterior[$campo] : null;
                }
            }

            $dados = [
                'tabela' => $tabela,
                'chave_tabela' => $chave_tabela,
                'acao' => $acao,
                'chave_usuario' => $chaveUsuario,
                'chave_acesso' => $this->pegaChaveAcesso(),
                'valor_anterior' => json_encode($valorAnteriorInserirLog),  //json_encode($valorAnterior),
                'valor_novo' => json_encode($valorNovoInserirLog), // $valorNovo, //json_encode($valorNovo),
                'data_log' => date('Y-m-d H:i:s')
            ];

            $chave = $this->inclui('eventos_sistema', $dados, 0, false);
        }        
        return $chave;
    }

    public function pegaChaveAcesso()
    {        
        return isset($_SESSION[session_id()]['usuario']['chave_acesso']) ? $_SESSION[session_id()]['usuario']['chave_acesso'] : null;
    }
}