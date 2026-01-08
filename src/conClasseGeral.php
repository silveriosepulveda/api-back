<?php

namespace ClasseGeral;

use PharIo\Manifest\Manifest;

/**
 * Classe base para operações de conexão e manipulação de dados gerais.
 *
 * Responsável por fornecer métodos utilitários para conexão com banco de dados,
 * manipulação de sessões, validação de campos obrigatórios, entre outros.
 */
$temp = __DIR__;
require_once __DIR__ . '/ClassesCache.php';

if (isset($_SESSION[session_id()]['caminhoApiLocal'])) {
    $arq = $_SESSION[session_id()]['caminhoApiLocal'] . 'api/backLocal/classes/dadosConexao.class.php';
    if (is_file($arq))
        require_once $arq;
} else
    include $_SERVER['DOCUMENT_ROOT'] . '/api/backLocal/classes/dadosConexao.class.php';


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
    public array $sexoporextenso = array('M' => 'Masculino', 'F' => 'Feminino');

    /**
     * Mapeamento de tipo de pessoa.
     * @var array
     */
    public array $tipoPessoa = array('F' => 'Física', 'J' => 'Jurídica');

    /**
     * Extensões de arquivos de imagem suportadas.
     * @var array
     */
    public array $extensoes_imagem = array('jpg', 'jpeg', 'gif', 'png');

    /**
     * Extensões de arquivos suportadas.
     * @var array
     */
    public array $extensoes_arquivos = array('doc', 'docx', 'pdf', 'xls', 'xlsx', 'txt', 'rar');

    /**
     * Quebra de linha padrão.
     * @var string
     */
    public string $q = "\n";

    /**
     * Array de conexões abertas.
     * @var array
     */
    public array $Conexoes;

    /**
     * Gerenciador de cache de classes
     * @var ClassesCache|null
     */
    private ?ClassesCache $classesCache = null;

    public function __construct()
    {
        parent::__construct();
        $teste = $this->bases;
        $this->classesCache = new ClassesCache();
    }

    /**
     * Limpa o cache de instâncias (útil para testes ou liberação de memória)
     * @param string|null $className Se especificado, limpa apenas essa classe do cache
     * @return void
     */
    public function clearInstanceCache(?string $className = null): void
    {
        if ($this->classesCache !== null) {
            $this->classesCache->clearInstanceCache($className);
        }
    }

    /**
     * Limpa apenas o cache de classes de tabela
     * @param string|null $classe Se especificado, limpa apenas essa classe de tabela do cache
     * @return void
     */
    public function clearTabelaCache(?string $classe = null): void
    {
        if ($this->classesCache !== null) {
            $this->classesCache->clearTabelaCache($classe);
        }
    }

    /**
     * Retorna a data e hora atual formatada.
     *
     * @return string Data e hora atual.
     */
    public function pegaDataHora(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Converte um nome de tabela para o formato de classe em PHP.
     *
     * @param string $tabela Nome da tabela a ser convertida.
     * @return string Nome da classe gerada a partir da tabela.
     */
    public function nomeClase(string $tabela): string
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

    public function retornavalorparasql(string $tipo, mixed $valor, string $origem = 'consulta', string $campo = ''): mixed
    {
        return $this->pegaFormatacoes()->retornavalorparasql($tipo, $valor, $origem, $campo);
    }

    /**
     * Retorna uma instância cached da classe Formatacoes
     * @return Formatacoes
     */
    protected function pegaFormatacoes(): Formatacoes
    {
        return $this->pegaClassesCache()->pegaFormatacoes();
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
        // $TipoBase = isset($this->TipoBase) ? $this->TipoBase : 'MySQL';
        $TipoBase = 'MySQL';

        $dataBase = $dataBase == '' ? $this->pegaDataBase('', $dataBase) : $dataBase;

        $this->conecta($dataBase);
        $retorno = '';

        if ($this->bases[$dataBase]['tipo_base'] === 'MySQL') {
            $con = $this->Conexoes[$dataBase];
            ini_set('error_reporting', '~E_DEPRECATED');
            $con->query('set sql_mode=""');
            $retorno = $con->query($sql);
            if (!$retorno) {
                $this->desconecta($dataBase);
            }
        } //elseif ($TipoBase === 'SQLite') {
//            //$retorno = $this->ConexaoBase->query($sql);
//        }
        return $retorno;
    }

    /**
     * Retorna o nome da base de dados a ser utilizada para uma tabela específica.
     *
     * @param string $tabela Nome da tabela.
     * @param string $dataBase (Opcional) Nome da base de dados.
     * @return string Nome da base de dados.
     */
    public function pegaDataBase(string $tabela = '', mixed $dataBase = ''): string
    {
        $configuracoesTabela = [];
        $tabInfo = new \ClasseGeral\TabelasInfo();

        if ($tabela != '') {
            $configuracoesTabela = $tabInfo->buscaConfiguracoesTabela($tabela);
        }

        if ($dataBase != '' && gettype($dataBase) == 'string' && isset($this->bases[$dataBase]))
            $retorno = $dataBase;
        else
            if ($tabela != '' && isset($configuracoesTabela['dataBase']) && isset($this->bases[$configuracoesTabela['dataBase']]))
                $retorno = $configuracoesTabela['dataBase'];
            else
                //$retorno = $this->conexaoPadrao;
                $retorno = $this->pegaConexaoPadrao();

        return $retorno;
    }

    public function buscaConfiguracoesTabela(string $tabela, string $tipoTabela = 'Principal'): array
    {
        return $this->pegaTabelasInfo()->buscaConfiguracoesTabela($tabela, $tipoTabela);
    }

    /**
     * Retorna uma instância cached da classe TabelasInfo
     * @return TabelasInfo
     */
    protected function pegaTabelasInfo(): TabelasInfo
    {
        return $this->pegaClassesCache()->pegaTabelasInfo();
    }

    /**
     * Retorna o próximo registro de um resultado de query.
     *
     * @param mixed $resultado Resultado da query.
     * @return array|null Retorna os dados do próximo registro ou null se não houver mais registros.
     */
    public function retornosql($resultado)
    {
        //$TipoBase = isset($this->TipoBase) ? $this->TipoBase : 'MySQL';
        $TipoBase = 'MySQL';
        if ($TipoBase === 'MySQL') {
            if ($resultado) {
                return $resultado->fetch_assoc();
            }
        } else if ($TipoBase === 'SQLite') {
            return $resultado->fetchArray(SQLITE3_ASSOC);
        }
    }

    public function inclui(string $tabela, array $dados, null|int $chave_primaria = 0, bool $mostrarsql = false, bool $inserirLog = true, bool $formatar = true): mixed
    {
        return $this->pegaManipulaDados()->inclui($tabela, $dados, $chave_primaria, $mostrarsql, $inserirLog, $formatar);
    }

    /**
     * Retorna uma instância cached da classe ManipulaDados
     * @return ManipulaDados
     */
    protected function pegaManipulaDados(): ManipulaDados
    {
        return $this->pegaClassesCache()->pegaManipulaDados();
    }

    public function validarCamposObrigatorios(array $configuracao, array $dados, array $retorno = []): array
    {
        return $this->pegaManipulaDados()->validarCamposObrigatorios($configuracao, $dados, $retorno);
    }

    public function buscarDuplicidadeCadastro(array $configuracoes, array $dados): bool
    {
        return $this->pegaManipulaDados()->buscarDuplicidadeCadastro($configuracoes, $dados);
    }

    public function exclui(string $tabela, string $campo_chave, string $chave, string $tabela_relacionada = 'nenhuma', bool $exibirsql = false): bool|string
    {
        $parametros = [
            'tabela' => $tabela,
            'campo_chave' => $campo_chave,
            'chave' => $chave,
            'exibirsql' => $exibirsql
        ];
        $retorno = json_decode($this->pegaManipulaDados()->excluir($parametros), true);
        return json_encode(['chave' => $retorno['chave']]);
    }

    public function buscarEstrutura($parametros, $tipoRetorno = 'json'): bool|array|string
    {
        return $this->pegaClassesCache()->pegaEstruturas()->buscarEstrutura($parametros, $tipoRetorno);
    }

    public function altera(string $tabela, array $dados, null|int $chave = 0, bool $mostrarsql = false, bool $inserirLog = true): mixed
    {
        return $this->pegaManipulaDados()->altera($tabela, $dados, $chave, $mostrarsql, $inserirLog);
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

        $tbInfo = new \ClasseGeral\TabelasInfo();
        $formata = new \ClasseGeral\Formatacoes();


        $campos_tabela = $tbInfo->campostabela($tabela);

        $sql = ' FROM ' . $tabela;
        $campo_chave = $tbInfo->campochavetabela($tabela_buscar_chave_primaria);

        $sql .= ' WHERE ' . $campo_chave . ' >= 0';

        if (isset($p['comparacao'])) {
            foreach ($p['comparacao'] as $op) {
                if (!is_array($op[0])) { //Neste caso é uma comparação simples

                    $tipo = $op[0];
                    $campo = strtolower($op[1]);

                    $operador = $op[2] ?? '';
                    $valor = isset($op[3]) ? $formata->retornavalorparasql($tipo, $op[3]) : '';

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
                            $valor = $formata->retornavalorparasql($tipo, $valores[$key]);
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

    public function nometabela(string $tabela): string
    {
        return $this->pegaTabelasInfo()->nometabela($tabela);
    }

    /**
     * Retorna o valor a ser exibido em uma consulta, formatando-o de acordo com o tipo do campo.
     *
     * @param string $tabela Nome da tabela do campo.
     * @param string $campo Nome do campo.
     * @param mixed $valor Valor a ser exibido.
     * @return mixed Valor formatado para exibição.
     */
    public function valorexibirconsulta(string $tabela, string $campo, mixed $valor): mixed
    {
        $formata = new \ClasseGeral\Formatacoes();
        $retorno = '';
        $tabela = strtolower((string)$tabela);
        $campo = strtolower($campo);

        $tipo = $this->tipodadocampo($tabela, $campo);
        //Comparando os campos para montar a variável de retorno
        return $formata->formatavalorexibir($valor, $tipo);
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
        $campos = $this->pegaTabelasInfo()->campostabela($tabela);
        foreach ($campos as $key => $valores) {
            if ($valores['campo'] == $campo)
                $retorno = $valores['tipo'];
        }
        return $retorno;
    }

    public function formatavalorexibir(mixed $valor, string $tipo, bool $htmlentitie = true): mixed
    {
        return $this->pegaFormatacoes()->formatavalorexibir($valor, $tipo, $htmlentitie);
    }

    /**
     * Verifica se um objeto existe com base nos parâmetros informados.
     *
     * @param array $parametros Parâmetros para a verificação da existência do objeto.
     * @return string JSON com informações sobre a existência do objeto.
     */
    public function objetoexistesimples(array $parametros): string
    {
        $tbInfo = new \ClasseGeral\TabelasInfo();
        $tabela = strtolower($parametros['tabela']);

        $config = $tbInfo->buscaConfiguracoesTabela($tabela);
        $tabela = $config['tabelaOrigem'] ?? $tabela;

        $campo = strtolower($parametros['campo']);
        $valor = strtolower($parametros['valor']);
        $chave = $parametros['chave'] ?? 0;

        $campoChave = $tbInfo->campochavetabela($tabela);
        $valorinformar = isset($parametros['valorinformar']) ? strtolower($parametros['valorinformar']) : $tbInfo->campochavetabela($tabela);

        if ($valorinformar !== '') {
            $sql = "SELECT $campo, $campoChave, $valorinformar FROM $tabela WHERE $campo = '$valor'";
        } else {
            $sql = "SELECT $campo, $campoChave FROM $tabela WHERE $campo = '$valor'";
        }

        $campo_chave = $tbInfo->campochavetabela($tabela);
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

    public function retornosqldireto(string|array $sql, $acao = '', $tabela = '', $dataBase = '', $mostrarsql = false, $formatar = true): array
    {
        return $this->pegaConsultaDados()->retornosqldireto($sql, $acao, $tabela, $dataBase, $mostrarsql, $formatar);
    }

    /**
     * Retorna uma instância cached da classe ConsultaDados
     * @return ConsultaDados
     */
    protected function pegaConsultaDados(): ConsultaDados
    {
        return $this->pegaClassesCache()->pegaConsultaDados();
    }

    /**
     * Garante que o cache de classes está inicializado
     * @return ClassesCache
     */
    private function pegaClassesCache(): ClassesCache
    {
        if ($this->classesCache === null) {
            $this->classesCache = new ClassesCache();
        }
        return $this->classesCache;
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
            $campo_chave = $this->pegaTabelasInfo()->campochavetabela($tabela);
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
     * Retorna o número de linhas afetadas pela última operação no banco de dados.
     *
     * @param string $dataBase (Opcional) Nome da base de dados.
     * @return int Número de linhas afetadas.
     */
    public function linhasafetadas($dataBase = '')
    {
        //$TipoBase = isset($this->TipoBase) ? $this->TipoBase : 'MySQL';
        $TipoBase = 'MySQL';

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
        $campo_chave = $campo_chave != '' ? strtolower($campo_chave) : $this->pegaTabelasInfo()->campochavetabela($tabela);

        $sql = "SELECT $campo_chave FROM $tabela WHERE $campo_chave > 0";

        foreach ($campos as $key => $val) {
            $sql .= " AND " . strtolower($val) . " = " . $this->retornavalorparasql('varchar', $valores[$val]);
        }

        if ($chave_primaria > 0) {
            $campo_chave = $this->pegaTabelasInfo()->campochavetabela($tabela);
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
        $campo_chave = $this->pegaTabelasInfo()->campochavetabela($tabela);
        $chave = $chave;
        //buscando os campos da tabela
        $campos = $this->pegaTabelasInfo()->campostabela($tabela);

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
    public function buscachaveporcampos(string $tabela, $campos, $valores, $tabelaOrigem = '', bool $mostrarsql = false): int|string
    {
        $tbInfo = new \ClasseGeral\TabelasInfo();
        $campo_chave = $tabelaOrigem != '' ? $tbInfo->campochavetabela($tabelaOrigem) : $tbInfo->campochavetabela($tabela);
        $camposTabela = $tbInfo->campostabela($tabela);

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

    public function montasql(array $p, string $campo_chavee = ''): string
    {
        $tabInfo = new \ClasseGeral\TabelasInfo();
        $formata = new \ClasseGeral\Formatacoes();

        //Vendo se é uma view
        //TP - TABELA PRINCIPAL
        //TS - TABELA RELACIONADA
        $tabela = $p['tabela'];
        $tabelaConsulta = $p['tabelaConsulta'] ?? $tabela;

        $tabelaConsulta = strtolower($tabelaConsulta);
        $campo_chave = '';

        if ($campo_chavee != '') {
            $campo_chave = $campo_chavee;
        } else if ($tabInfo->campochavetabela($tabela) != '') {
            $campo_chave = $tabInfo->campochavetabela($tabela);
        } else if ($tabInfo->campochavetabela($tabelaConsulta) != '') {
            $campo_chave = $tabInfo->campochavetabela($tabelaConsulta);
        }

        $campos_tabela = $tabInfo->campostabela($tabelaConsulta);

        $sql = 'select ';

        $campos = [];

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
                } else if (str_starts_with($campo, 'distinct')) {//Acrescentando o distinct
                    $campoDistinct = substr(trim($campo), 9, strlen($campo) - 10);
                    $sql .= ' distinct(TP.' . $campoDistinct . '),';
                } else if (str_starts_with($campo, 'sum')) {
                    $campoSum = substr(trim($campo), 4, strlen($campo) - 4);
                    $sql .= ' sum(TP.' . $campoSum . ') AS ' . $campoSum . ',';
                } else if (str_starts_with($campo, 'count')) {
                    $campoCount = trim(substr($campo, 6, strlen($campo) - 7));
                    $sql .= " count(TP.$campoCount) as $campoCount ";
                }
            }

            $sql = substr($sql, 0, strlen($sql) - 1);
        } else {
            $sql .= 'TP.*';
        }

        $sql = str_ends_with($sql, ',') ? substr($sql, 0, strlen($sql) - 1) : $sql;
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
                        $valor = $valor === 'chave_usuario_logado' ? $this->pegaChaveUsuario() : $formata->retornavalorparasql($tipo, $valor, 'consulta');
                    } else {
                        $valor = '';
                    }

                    if ($tipo == 'inArray') {
                        $sql .= ' and TP.' . $op[1] . ' in("' . join('","', $op[3]) . '")';
                    } elseif ($tipo == 'in') {
                        $sql .= ' AND TP.' . strtolower($op[1]) . ' IN (SELECT TS.' . strtolower($op[1]) . ' FROM ' . strtolower($op[2]) . ' TS WHERE TS.' . strtolower($op[3]);
                        $camposTabelaIn = $tabInfo->campostabela($op[2]);

                        $tipoComp = $camposTabelaIn[strtolower($op[3])]['tipo'];
                        $valorComparar = $formata->retornavalorparasql($tipoComp, $op[5]);

                        if ($op[4] == 'between') {
                            $op[3] = strtolower($op[3]);
                            $tempB = explode('__', $op[5]);
                            $temDi = $tempB[0] != 'undefined' && $tempB[0] != '';
                            $temDf = $tempB[1] != 'undefined' && $tempB[1] != '';

                            if ($temDi) {
                                $di = $formata->retornavalorparasql('date', $tempB[0]);
                                $sql .= " >= $di ";
                            }
                            if ($temDf) {
                                $df = $formata->retornavalorparasql('date', $tempB[1]);
                                $sql .= $temDi ? " AND TS.$op[3] <= $df" : " <= $df";
                            }
                        } elseif ($tipoComp == 'varchar') {
                            $operadorIn = $op[4] ?? ' like ';
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

                        $di = $temDi ? $formata->retornavalorparasql('date', $tempB[0]) : '';
                        $df = $temDf ? $formata->retornavalorparasql('date', $tempB[1]) : '';

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
                            $valor = $formata->retornavalorparasql($tipos[$key], $valores[$key]);
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
    public function pegaChaveUsuario(): mixed
    {
        return isset($_SESSION[session_id()]['usuario']['chave_usuario']) ? $_SESSION[session_id()]['usuario']['chave_usuario'] : null;
    }

    /**
     * Retorna o nome das tabelas da base de dados.
     *
     * @return array Lista de tabelas da base de dados.
     */
    // public function tabelasbase($dataBase ='' )
    // {
    //     $array = array();
    //     $this->conecta();
    //     $base = $dataBase;
    //     $sql = 'SHOW TABLES FROM ' . $base;
    //     $res = $this->executasql($sql);
    //     while ($lin = $this->retornosql($res)) {
    //         $tabela = $lin['Tables_in_' . $base];
    //         $array[] = $tabela;
    //     }
    //     $array = json_encode($array);
    //     echo $array;
    // }

    /**
     * Funcao que busca um ou mais campos de uma tabela por sua chave
     * @param string $tabela Tabela que sera buscada
     * @param string|array $campos Campos que serao buscados
     * @param string $chave chave do registro a ser buscado
     * @param boolean $mostrarsql Se true retorna o sql da rotina
     * @return array Retorna os dados do registro solicitado em array com os nomes dos campos em
     */
    public function buscacamposporchave(string $tabela, string|array $campos, string $chave, bool $mostrarsql = false): array
    {
        $tabInfo = $this->pegaTabelasInfo();
        $formata = $this->pegaFormatacoes();

        $campo_chave = $tabInfo->campochavetabela($tabela);

        $s['tabela'] = $tabela;
        $s['campos'] = sizeof($campos) > 0 ? $campos : '*';
        $s['comparacao'][] = array('int', $campo_chave, '=', $chave);

        $sql = $this->montasql($s);

        $lin = $formata->retornosqldireto($sql, '', $tabela)[0];

        if ($mostrarsql) {
            echo $sql;
        }
        return $lin;
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
     * Retorna o próximo valor disponível para uma chave, considerando a tabela e a sequência.
     *
     * @param string $tabela Nome da tabela a ser considerada.
     * @param bool $atualizarSequencia (Opcional) Se deve ou não atualizar a sequência.
     * @return int Próximo valor disponível para a chave.
     */
    public function proximachave($tabela, $atualizarSequencia = false)
    {
        $tabInfo = new \ClasseGeral\TabelasInfo();

        $proxima_chave = 0;
        $tabelaOriginal = $tabela;
        $tabela = $tabInfo->nometabela($tabela);

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
        $tabInfo = new \ClasseGeral\TabelasInfo();
        $campo_chave = $tabInfo->campochavetabela($tabela);
        $sql = "SELECT MAX($campo_chave) AS ULTIMA_CHAVE FROM $tabela";
        $retorno = $this->retornosqldireto($sql, '', $tabela);
        return $retorno[0]['ultima_chave'] ?? 0;
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
     * Completa um campo com sugestões baseadas em um texto informado.
     *
     * @param array $p Parâmetros para a consulta de sugestões.
     * @return void
     */
    public function completacampo(array $p): string
    {
        $tbInfo = new \ClasseGeral\TabelasInfo();

        $mostrarSQL = false;
        $texto = $p['term'];// $_GET['term']; //$p;

        $tabela = strtolower($p['tabela']);
        $campos_tabela = $tbInfo->campostabela($tabela);

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

        // @session_start();
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
                if ($comparacao[0] == 'SQL')
                    $sql .= $comparacao[1];
                else
                    $sql .= ' and TP.' . $comparacao[1] . ' ' . $comparacao[2] . ' "' . $comparacao[3] . '"';
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

    public function buscaUsuarioLogado()
    {

        return $_SESSION[session_id()]['usuario'];
        return $this->pegaManipulaSessao()->pegar('usuario');
    }

    /**
     * Retorna uma instância cached da classe ManipulaSessao
     * @return ManipulaSessao
     */
    protected function pegaManipulaSessao(): ManipulaSessao
    {
        return $this->pegaClassesCache()->pegaManipulaSessao();
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
        //$base = $this->MyBase;
        $base = $this->conexaoPadrao;
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
        $formata = new \ClasseGeral\Formatacoes();
        $v1 = $formata->retornavalorparasql('float', $valor);
        $mult = $formata->retornavalorparasql('float', $multiplicador);
        return $formata->formatavalorexibir($v1 * $mult, 'float');
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

    public function camposArrayToString($array, $campo): string
    {
        return join(',', array_keys($this->agruparArray($array, $campo)));
    }

    /**
     * Agrupa um array de dados com base em um campo de agrupamento.
     *
     * @param array $array Array a ser agrupado.
     * @param string $campoAgrupamento Campo a ser utilizado para o agrupamento.
     * @param bool $compararQuantidade (Opcional) Se deve ou não comparar a quantidade de itens agrupados.
     * @return array Array agrupado.
     */
    public function agruparArray(array $array, string $campoAgrupamento, bool $compararQuantidade = true): array
    {
        $retornoTemp = array();
        foreach ($array as $item) {
            $temValor = isset($item[$campoAgrupamento]) && $item[$campoAgrupamento] != '' && $item[$campoAgrupamento] != null;
            if ($temValor) {
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
        if (!is_string($texto)) {
            $texto = (string)$texto;
        }
        // Padrão regex para remover comandos SQL perigosos e caracteres especiais
        $padrao = '/( from | alter table | select | insert | delete | update | where | drop table | show tables |\*|--|\\\\)/i';
        $retorno = preg_replace($padrao, '', $texto);
        if ($retorno === null) {
            $retorno = $texto;
        }

        $retorno = trim($retorno);
        $retorno = strip_tags($retorno);
        $retorno = (htmlspecialchars($retorno)) ? $retorno : addslashes($retorno);
        return $retorno;
    }

    /*
     * Função que pega um texto separado por um elemento e retorna um array
     * @param string $separador é o elemento que separa um texto, pode ser ',' '-' ou outro caracter
     * @param string $texto é o texto a ser convertido, exemplo 1,2,3,4
     * @return array $retorno é o texto convertido em um array
     */

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
     * Cria uma instância de uma classe a partir do nome da classe com cache.
     *
     * @param string $classe Nome da classe a ser instanciada.
     * @return mixed Instância da classe ou false em caso de falha.
     */
    public function criaClasseTabela($classe)
    {
        return $this->pegaClassesCache()->criaClasseTabela($classe, [$this, 'pegaCaminhoApi']);
    }

    /**
     * Retorna o caminho base da API local.
     * @return string Caminho absoluto da API local.
     */
    public function pegaCaminhoApi()
    {
        if (isset($_SESSION[session_id()]['caminhoApiLocal']))
            return $_SESSION[session_id()]['caminhoApiLocal'];
        else {
            $caminho = $_SERVER['DOCUMENT_ROOT'] . '/';
            $_SESSION[session_id()]['caminhoApiLocal'] = $caminho;
            return $caminho;
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
        return $this->pegaClassesCache()->criaFuncaoClasse($classe, $funcao, [$this, 'pegaCaminhoApi']);
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

    public function pegaChaveAcesso()
    {
        return isset($_SESSION[session_id()]['usuario']['chave_acesso']) ? $_SESSION[session_id()]['usuario']['chave_acesso'] : null;
    }

    /**
     * Retorna uma instância cached da classe ManipulaValores
     * @return ManipulaValores
     */
    protected function pegaManipulaValores(): ManipulaValores
    {
        return $this->pegaClassesCache()->pegaManipulaValores();
    }

    /**
     * Retorna uma instância cached de UploadSimples
     * @return UploadSimples
     */
    protected function pegaUploadSimples(): UploadSimples
    {
        return $this->pegaClassesCache()->pegaUploadSimples();
    }

    /**
     * Retorna uma instância cached de GerenciaDiretorios
     * @return GerenciaDiretorios
     */
    protected function pegaGerenciaDiretorios(): GerenciaDiretorios
    {
        return $this->pegaClassesCache()->pegaGerenciaDiretorios();
    }

    /**
     * Retorna uma instância cached de ManipulaStrings
     * @return ManipulaStrings
     */
    protected function pegaManipulaStrings(): ManipulaStrings
    {
        return $this->pegaClassesCache()->pegaManipulaStrings();
    }

    /**
     * Retorna uma instância cached de configuracoesTabelas
     * @return mixed
     */
    protected function pegaConfiguracoesTabelas(): mixed
    {
        return $this->pegaClasseCache('configuracoesTabelas');
    }

    /**
     * Método genérico para cache de instâncias por nome de classe
     * @param string $className Nome da classe
     * @return mixed Instância da classe
     */
    protected function pegaClasseCache(string $className): mixed
    {
        return $this->pegaClassesCache()->pegaClasseCache($className);
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
}