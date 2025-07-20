<?php

namespace ClasseGeral;

require_once __DIR__ . '/conClasseGeral.php';

/**
 * Classe principal para operações gerais do sistema.
 * 
 * Esta classe herda de ConClasseGeral e provê métodos utilitários para manipulação de dados,
 * paginação, seleção de itens, formatação de URLs, entre outros.
 */
class ClasseGeral extends ConClasseGeral
{
    /**
     * Caminho para funções utilitárias.
     * @var string
     */
    private $funcoes = "";

    /**
     * Indica se deve mostrar o SQL da consulta.
     * @var bool
     */
    private $mostrarSQLConsulta = false;

    /**
     * Configuração padrão de paginação.
     * @var array
     */
    private $paginacao = array(
        'paginasMostrar' => 5,
        'limitePaginaAtiva' => 5,
        'qtdItensRetornados' => 0,
        'pagina' => 1,
        'qtdPaginas' => 1,
        'primeiraPagina' => 1,
        'ultimaPagina' => 10,
        'itensPagina' => 25,
        'itensUltimaPagina' => 0,
    );

    /**
     * Construtor da ClasseGeral.
     * Inicializa timezone e define o caminho das funções utilitárias.
     */
    public function __construct()
    {
        clearstatcache();
        date_default_timezone_set('America/Sao_Paulo');
        $this->funcoes = $_SESSION[session_id()]['caminhoApiLocal'] . 'api/BaseArcabouco/funcoes.class.php';
    }

    /**
     * Formata uma URL de vídeo do YouTube para o formato embed.
     *
     * @param string $url URL do vídeo.
     * @return string URL formatada para embed.
     */
    public function formataUrlVideo($url): string
    {
        $retorno = '';
        if (strpos($url, 'watch?v=') > 0)
            $retorno = str_replace('watch?v=', 'embed/', $url);
        else if (strpos($url, 'shorts/'))
            $retorno = str_replace('shorts/', 'embed/', $url);
        return $retorno;
    }

    /**
     * Marca ou desmarca um item como selecionado em uma consulta na sessão.
     *
     * @param array $parametros Parâmetros contendo 'tela', 'key', 'selecionado', 'campo_chave', 'chave'.
     * Exemplo:
     *   [
     *     'tela' => 'usuarios',
     *     'key' => 1,
     *     'selecionado' => 'true',
     *     'campo_chave' => 'id',
     *     'chave' => 123
     *   ]
     */
    public function selecionarItemConsulta($parametros)
    {
        if (!is_array($parametros)) {
            throw new \InvalidArgumentException('Esperado array em $parametros');
        }
        $tela = $parametros['tela'];
        $key = $parametros['key'];
        $selecionado = $parametros['selecionado'];

        @session_start();

        // Percorre a lista de itens da consulta e marca/desmarca conforme o parâmetro
        foreach ($_SESSION[session_id()]['consultas'][$tela]['lista'] as $key => $item) {
            if ($item[$parametros['campo_chave']] == $parametros['chave']) {
                if ($selecionado == 'false') {
                    $_SESSION[session_id()]['consultas'][$tela]['parametrosConsulta']['todosItensSelecionados'] = $selecionado;
                }
                $_SESSION[session_id()]['consultas'][$tela]['lista'][$key]['selecionado'] = $selecionado;
            }
        }
    }

    /**
     * Seleciona ou desseleciona todos os itens de uma consulta.
     *
     * @param array $parametros Parâmetros contendo 'tela' e 'selecionado'.
     * Exemplo: [ 'tela' => 'usuarios', 'selecionado' => 'true' ]
     * @return string JSON de sucesso.
     */
    public function selecionarTodosItensConsulta($parametros)
    {
        if (!is_array($parametros)) {
            throw new \InvalidArgumentException('Esperado array em $parametros');
        }
        $tela = $parametros['tela'];
        $selecionado = $parametros['selecionado'];

        $sessao = new \ClasseGeral\ManipulaSessao();
        $variavelSessao = 'consultas,' . $tela;
        $lista = $sessao->pegar($variavelSessao);

        $lista['parametrosConsulta']['todosItensSelecionados'] = $selecionado;

        foreach ($lista['lista'] as $key => $item) {
            $lista['lista'][$key]['selecionado'] = $selecionado;
        }

        $sessao->setar($variavelSessao, $lista);
        return json_encode(['sucesso' => 'sucesso']);
    }

    /**
     * Monta os itens de um relatório a partir dos itens selecionados ou de todos os itens.
     *
     * @param array $parametros Parâmetros contendo 'parametrosConsulta' e 'lista'.
     * Exemplo: [ 'parametrosConsulta' => [...], 'lista' => [...]]
     * @return array Lista de itens selecionados.
     */
    public function montaItensRelatorio($parametros)
    {
        if (!is_array($parametros)) {
            throw new \InvalidArgumentException('Esperado array em $parametros');
        }
        $p = $parametros;
        $retorno = array();
        if ($p['parametrosConsulta']['todosItensSelecionados']) {
            $p['parametrosConsulta']['itensPagina'] = 'todos';
            $retorno = $this->consulta($p['parametrosConsulta'], 'array')['lista'];
        } else {
            foreach ($p['lista'] as $item) {
                if (isset($item['selecionado']) && $item['selecionado'] == 'true') {
                    $retorno[] = $item;
                }
            }
        }
        return $retorno;
    }

    /**
     * Realiza uma consulta na tabela especificada nos parâmetros.
     *
     * @param array $parametros Parâmetros da consulta, incluindo tabela, campos, filtros, etc.
     * @param string $tipoRetorno Tipo de retorno desejado ('json' ou 'array').
     * Exemplo: [ 'tabela' => 'usuarios', 'campos' => ['id', 'nome'], ... ]
     * @return mixed Resultado da consulta no formato desejado.
     */
    public function consulta($parametros, $tipoRetorno = 'json')
    {
        if (!is_array($parametros)) {
            throw new \InvalidArgumentException('Esperado array em $parametros');
        }
        ini_set('memory_limit', '-1');
        $p = isset($parametros['parametros']) ? json_decode($parametros['parametros'], true) : $parametros;

        $p['itensPagina'] = 50;
        $limite = isset($p['limite']) && $p['limite'] > 0 ? $p['limite'] : 0;

        $tabela = $p['tabela'];
        $p['campo_chave'] = $p['campo_chave'] ?? strtolower($this->campochavetabela($p['tabela']));

        $tabelaConsulta = $p['tabelaConsulta'] ?? $tabela;
        $temCampoDisponivelNoFiltro = false;

        $sessao = new \ClasseGeral\ManipulaSessao();

        $configuracoesTabela = $this->buscaConfiguracoesTabela($tabelaConsulta);
        $valoresConsiderarDisponivel = array_merge(['S'], $configuracoesTabela['valoresConsiderarDisponivel'] ?? []);

        $s['tabela'] = $tabela;
        $s['tabelaConsulta'] = $tabelaConsulta;
        $s['dataBase'] = $configuracoesTabela['dataBase'] ?? '';

        //Acrescentando o campo chave
        $tirarCampoChaveConsulta = $p['tirarCampoChaveConsulta'] ?? false;

        if (isset($p['campos']) && sizeof($p['campos']) > 0 && $tirarCampoChaveConsulta == false) {
            $p['campos'][] = $p['campo_chave'];
        } else if (!isset($p['campos'])) {
            $p['campos'] = '*';
        }

        $s['campos'] = $p['campos'];
        if ($s['campos'] != '*') {
            $s['campos'][] = $p['campo_chave'];
        }

        $campos_tabela = $this->campostabela($tabelaConsulta);

        if (isset($p['filtros']) && is_array($p['filtros'])) {
            foreach ($p['filtros'] as $key => $val) {
                $campo = strtolower($val['campo']);
                $temCampoDisponivelNoFiltro = strtolower($campo) == 'disponivel' ? true : $temCampoDisponivelNoFiltro;

                if (array_key_exists($campo, $campos_tabela)) {
                    if (isset($val['campo_chave']) && (isset($val['chave']))) {
                        $operadorTemp = in_array($val['operador'], ['=', 'like']) ? '=' : '<>';
                        $s["comparacao"][] = array('inteiro', $val['campo_chave'], $operadorTemp, $val['chave']);
                    } else {
                        $s["comparacao"][] = array($campos_tabela[$campo]['tipo'], $campo, $val['operador'], $val['valor']);
                    }
                }
            }
        }

        //Por enquanto farei apenas dois niveis, relacionada e subrelacionada
        //Esta variavel vai definir se busco os dados relacionados e incluo no retorno
        $incluirRelacionados = false;

        if (isset($p['tabelasRelacionadas']) && is_array($p['tabelasRelacionadas'])) {
            foreach ($p['tabelasRelacionadas'] as $tabelaRelacionada => $dadosTabelaRelacionada) {
                if (isset($dadosTabelaRelacionada['incluirNaConsulta']) && $dadosTabelaRelacionada['incluirNaConsulta']) {
                    $incluirRelacionados = true;
                }

                if (!isset($dadosTabelaRelacionada['usarNaPesquisa']) || $dadosTabelaRelacionada['usarNaPesquisa'] == 'true') {
                    $camposTabelaRelacionada = $this->campostabela($tabelaRelacionada);

                    $camposIgnorarFiltro = isset($dadosTabelaRelacionada['camposIgnorarFiltro']) ? $dadosTabelaRelacionada['camposIgnorarFiltro'] : [];

                    foreach ($p['filtros'] as $keyF => $filtro) {
                        if (array_key_exists(strtolower($filtro['campo']), $camposTabelaRelacionada) && !in_array($filtro['campo'], $camposIgnorarFiltro)) {

                            $campoRelacionamento = $dadosTabelaRelacionada['campo_relacionamento'] ?? $dadosTabelaRelacionada['campoRelacionamento'];
                            //Comentei a linha abaixo pois nao entendi o seu funcionamento em 09/10/2017                            

                            if (isset($filtro['campo_chave']) && in_array($filtro['campo_chave'], array_keys($camposTabelaRelacionada)) &&
                                isset($filtro['chave']) && $filtro['chave'] > 0) {
                                $campoTR = $filtro['campo_chave'];
                                $valorTR = $filtro['chave'];
                                $operadorTR = '=';
                            } else {
                                $campoTR = $filtro['campo'];
                                $valorTR = $filtro['valor'];
                                $operadorTR = $filtro['operador'];
                            }
                            $s['comparacao'][] = array('in', $campoRelacionamento, $tabelaRelacionada, $campoTR, $operadorTR, $valorTR);
                        }
                    }
                }
            }
        }

        if (isset($campos_tabela['arquivado'])) {
            $s['comparacao'][] = array('varchar', 'arquivado', '!=', 'E');
        }

        if (isset($campos_tabela['disponivel']) && !$temCampoDisponivelNoFiltro) {
            $s['comparacao'][] = array('inArray', 'disponivel', '=', $valoresConsiderarDisponivel);
        }

        //No caso o limite esta funcionando apenas na primeira consulta quando e automatica
        if (isset($p['resumoConsulta']) && sizeof($p['resumoConsulta']) > 0 && $limite == 0) {
            $retorno['resumoConsulta'] = $this->resumoConsulta($p, $campos_tabela);
        }

        if (isset($configuracoesTabela['comparacao'])) {
            foreach ($configuracoesTabela['comparacao'] as $comparacao) {
                $s['comparacao'][] = $comparacao;
            }
        }

        $s['ordem'] = isset($p['ordemFiltro']) ? $p['ordemFiltro'] : '';

        if (isset($p['limite']) && $p['limite'] > 0)
            $s['limite'] = $p['limite'];

        $dispositivoMovel = isset($p['dispositivoMovel']) && $p['dispositivoMovel'];
        $retorno['paginacao'] = $this->paginacao;
        $retorno['paginacao']['paginasMostrar'] = $dispositivoMovel ? 5 : 10;

        $retornoTemp = $this->retornosqldireto($s, 'montar', $tabelaConsulta, (isset($p['origem']) && $p['origem'] == 'site'), $this->mostrarSQLConsulta);

        $qtdItensRetornados = sizeof($retornoTemp);
        $itensPagina = 50;// isset($p['itensPagina']) ? $p['itensPagina'] : 50;
        $qtdNaPagina = 1;
        $pagina = 1;

        $retorno['lista'] = $retornoTemp;

        if (isset($p['itensPagina']) && $p['itensPagina'] > 0) {
            //Fazendo a paginacao
            $pag = $this->paginacao;
            $pag['paginasMostrar'] = $dispositivoMovel ? 5 : 10;
            //Quantidade de itens retornados pelo filtro
            $pag['qtdItensRetornados'] = $qtdItensRetornados;

            //Pagina que vem no filtro ou padrao 1
            $pag['pagina'] = isset($p["pagina"]) ? $p['pagina'] : 1;
            //Quantos Itens por pagina
            $pag['itensPagina'] = $itensPagina;
            //Inicio para o sql
            $inicioLimite = $pag['pagina'] > 1 ? ($pag['pagina'] - 1) * $itensPagina : 0;

            $pag['itensUltimaPagina'] = $qtdItensRetornados % $itensPagina;
            $qtdPaginas = $pag['itensUltimaPagina'] > 0 ? (int)($qtdItensRetornados / $itensPagina) + 1 : (int)($qtdItensRetornados / $itensPagina);
            $pag['qtdPaginas'] = $qtdPaginas;

            //Definindo a primeira Pagina
            if ($pag['pagina'] > $pag['limitePaginaAtiva'] && $qtdPaginas > $pag['paginasMostrar'] && $pag['pagina'] + $pag['paginasMostrar'] <= $qtdPaginas) {
                $pag['primeiraPagina'] = $pag['pagina'] - $pag['limitePaginaAtiva'];
            } else if ($pag['pagina'] + $pag['paginasMostrar'] > $pag['qtdPaginas']) {
                $pag['primeiraPagina'] = $qtdPaginas - $pag['paginasMostrar'] > 0 ? $qtdPaginas - $pag['paginasMostrar'] : 1;
            }

            //Definindo o Ultimo numero
            if ($qtdPaginas <= $pag['paginasMostrar']) {
                //Tem menos que 10 paginas
                $pag['ultimaPagina'] = $qtdPaginas;
            } else if ($pag['pagina'] <= $pag['limitePaginaAtiva']) {
                //Tem mais que 10 paginas e esta antes da pagina $limitePaginaAtiva
                $pag['ultimaPagina'] = $pag['paginasMostrar'];
            } else if ($pag['pagina'] > $pag['limitePaginaAtiva'] && $pag['pagina'] <= $qtdPaginas - $pag['limitePaginaAtiva']) {
                $pag['ultimaPagina'] = $pag['primeiraPagina'] + $pag['paginasMostrar'];
            } else if ($pag['pagina'] == $qtdPaginas) {
                $pag['ultimaPagina'] = $qtdPaginas;
            }

            //Passando os parametros da paginacao para o sql
            $s["limite"] = array($inicioLimite, $itensPagina);
            $retorno['paginacao'] = $pag;
        } else if (isset($p['itensPagina']) && $p['itensPagina'] == 0) {
            $retorno['paginacao']['pagina'] = 1;
            $retorno['paginacao']['qtdPaginas'] = 1;
            $retorno['paginacao']['limitePaginaAtiva'] = 0;
        }

        //Testando a rotina de incluir as informacoes de tabelas relacionadas ja na consulta
        if ($incluirRelacionados) {
            $chaves = array();
            foreach ($retorno['lista'] as $key => $item) {
                $chaves[] = $item[$p['campo_chave']];
            }
            $chavesSQL = join(',', $chaves);

            foreach ($p['tabelasRelacionadas'] as $tabelaRelacionada => $dadosTabelaRelacionada) {
                if (isset($dadosTabelaRelacionada['incluirNaConsulta']) && $dadosTabelaRelacionada['incluirNaConsulta']) {
                    $camposBuscar = isset($dadosTabelaRelacionada['campos']) ? join(',', $dadosTabelaRelacionada['campos']) : '*';

                    if ($chavesSQL != '') {
                        $sqlTabRel = "SELECT $camposBuscar FROM $tabelaRelacionada WHERE $p[campo_chave] IN ($chavesSQL)";
                        $sqlTabRel .= isset($this->campostabela($tabelaRelacionada)['disponivel']) ? " and disponivel = 'S' " : '';

                        $dadosTabRel = $this->agruparArray($this->retornosqldireto(strtolower($sqlTabRel), '', $tabelaRelacionada), $p['campo_chave'], false);
                    }
                }

                foreach ($retorno['lista'] as $keyLista => $itemLista) {
                    if (isset($dadosTabRel[$itemLista[$p['campo_chave']]])) {
                        $retorno['lista'][$keyLista][$tabelaRelacionada] = $dadosTabRel[$itemLista[$p['campo_chave']]];
                    }
                }
            }
        }

        $classeTabela = isset($configuracoesTabela['classe']) ? $configuracoesTabela['classe'] : $this->nomeClase($tabela);

        $classeAposFiltrar = $this->criaClasseTabela($classeTabela);
        $funcaoAposFiltrar = isset($parametros['acaoAposFiltrar']) && $parametros['acaoAposFiltrar'] != 'undefined' ? $parametros['acaoAposFiltrar'] : 'aposFiltrar';
        $temFuncaoAposFiltrar = $this->criaFuncaoClasse($classeAposFiltrar, $funcaoAposFiltrar);

        if ($temFuncaoAposFiltrar) {
            $retorno = $classeAposFiltrar->$funcaoAposFiltrar($retorno);
        }

        $tela = $p['tela'] ?? $p['tabela'];

        $retornoSessao['parametrosConsulta'] = $parametros;
        $retornoSessao['filtros'] = isset($p['filtros']) ? $p['filtros'] : array();
        $retornoSessao['ordem'] = isset($p['ordemFiltro']) ? $p['ordemFiltro'] : '';
        $retornoSessao['paginacao'] = $retorno['paginacao'];
        $retornoSessao['lista'] = $retorno['lista'];
        $retornoSessao['parametrosSQL'] = $s;

        $sessao->setar('consultas,' . $tela, $retornoSessao);

        $this->desconecta($s['dataBase']);
        if ($tipoRetorno == 'json') {
            return json_encode($retorno);
        } else if ($tipoRetorno == 'array') {
            return $retorno;
        }
    }

    private function resumoConsulta($parametros, $campos_tabela = array())
    {
        $p = $parametros;
        $limite = isset($p['limite']) && $p['limite'] > 0 ? $p['limite'] : 0;
        $tabela = $p['tabela'];
        $campoChave = $p['campo_chave'];

        $sql = 'SELECT ';
        foreach ($p['resumoConsulta'] as $keyR => $valR) {
            if ($valR['operacao'] == 'soma') {
                $sql .= 'COALESCE(SUM(' . $valR['campo'] . '), 0) AS ' . $valR['campo'];
            }
            $sql .= $keyR + 1 < sizeof($p['resumoConsulta']) ? ', ' : '';
        }

        $sql .= ' FROM ' . $tabela . ' WHERE ' . $campoChave . ' > 0';

        if (is_array($p['filtros'])) {
            foreach ($p['filtros'] as $key => $val) {
                $campo = strtolower($val['campo']);
                if ($val['operador'] == 'between') {
                    $sql .= ' AND ' . $this->montaSQLBetween($campo, $val['valor']);

                } else if (array_key_exists($campo, $campos_tabela) && $val['valor'] != '') {
                    $sql .= ' AND ' . $campo . ' ' . $val['operador'] . $this->retornavalorparasql($campos_tabela[$campo]['tipo'], $val["valor"]);
                }
            }
        }

        if (isset($campos_tabela['arquivado'])) {
            $sql .= ' AND arquivado != "E" ';
        }

        if (isset($campos_tabela['disponivel'])) {
            $sql .= ' AND disponivel = "S" ';
        }
        $temp = $this->retornosqldireto(strtolower($sql), '', $p['tabela']);

        $resumo = $this->retornosqldireto(strtolower($sql), '', $p['tabela'])[0];
        return $resumo;
    }

    /**
     * Busca um registro para alteração com base nos parâmetros fornecidos.
     *
     * @param array $parametros Parâmetros da busca, incluindo tabela, chaves e campos desejados.
     * @param string $tipoRetorno Tipo de retorno desejado ('json' ou 'array').
     * Exemplo: [ 'tabela' => 'usuarios', 'campo_chave' => 'id', 'chave' => 123 ]
     * @return mixed Registro encontrado no formato desejado.
     */
    public function buscarParaAlterar($parametros, $tipoRetorno = 'json')
    {
        if (!is_array($parametros)) {
            throw new \InvalidArgumentException('Esperado array em $parametros');
        }
        $p = isset($parametros['filtros']) ? json_decode($parametros['filtros'], true) : $parametros;

        @session_start();
        $caminhoApiLocal = $_SESSION[session_id()]['caminhoApiLocal'];

        //Acrescentando a chave na variavel de camposddddddd
        $s['tabela'] = $p['tabela'];
        $s['tabelaConsulta'] = isset($p['tabelaConsulta']) && $p['tabelaConsulta'] != 'undefined' ? $p['tabelaConsulta'] : $p['tabela'];
        $s['comparacao'][] = array('int', $p['campo_chave'], '=', $p['chave']);

        $s['campos'] = isset($p['campos']) && $p['campos'] != '*' && count($p['campos']) > 0 ? array_merge([$p['campo_chave']], $p['campos']) : '*';

        if (isset($p['campoChaveSecundaria']) && isset($p['valorChaveSecundaria'])) {
            $s['comparacao'][] = ['varchar', $p['campoChaveSecundaria'], '=', $p['valorChaveSecundaria']];
        }

        $tempD = $this->retornosqldireto($s, 'montar', $s['tabelaConsulta'], false, false);

        $retorno = sizeof($tempD) == 1 ? $tempD[0] : array();

        //Buscando tabelas relacionadas
        if (isset($p['tabelasRelacionadas'])) {
            foreach ($p['tabelasRelacionadas'] as $keyTR => $valTR) {
                $camposTabelaRelacionada = $this->campostabela($keyTR);

                $campoRelacionamentoTR = isset($valTR['campo_relacionamento']) ? $valTR['campo_relacionamento'] : $valTR['campoRelacionamento'];
                $r = array();
                $r['tabela'] = $keyTR;
                $r['comparacao'][] = array('int', $campoRelacionamentoTR, '=', $p['chave']);

                if (array_key_exists('disponivel', $camposTabelaRelacionada)) {
                    $r['comparacao'][] = array('varchar', 'disponivel', '=', 'S');
                }
                $r['ordem'] = isset($valTR['ordem']) ? $valTR['ordem'] : '';
                $r['ordem'] .= isset($valTR['sentidoOrdem']) ? ' ' . $valTR['sentidoOrdem'] : '';

                $nomeArrayRelacionado = isset($valTR['raizModelo']) ? $valTR['raizModelo'] : strtolower($this->nometabela($keyTR));

                if (isset($valTR['verificarEmpresaUsuario']) && $valTR['verificarEmpresaUsuario']) {
                    $r['verificarEmpresaUsuario'] = true;
                }

                if (!isset($valTR['ordem']) || $valTR['ordem'] == '') {
                    if (array_key_exists('posicao', $camposTabelaRelacionada)) {
                        $r['ordem'] = 'posicao';
                    } else if (isset($valTR['campo_valor'])) {
                        $r['ordem'] = $valTR['campo_valor'];
                    }
                }

                $retornoR = $this->retornosqldireto($r, 'montar', $keyTR, false, false);

                if (isset($valTR['tabelasSubRelacionadas'])) {
                    foreach ($retornoR as $keyR => $valR) {
                        $sR = array();
                        foreach ($valTR['tabelasSubRelacionadas'] as $keyS => $valS) {

                            $camposTabelaSubRelacionada = $this->campostabela($keyS);
                            $sR['tabela'] = $keyS;
                            $sR['comparacao'][] = array('int', $campoRelacionamentoTR, '=', $p['chave']);
                            $campoRelacionamentoTSR = isset($valS['campo_relacionamento']) ? $valS['campo_relacionamento'] : $valS['campoRelacionamento'];

                            $sR['comparacao'][] = array('int', $campoRelacionamentoTSR, '=', $valR[$campoRelacionamentoTSR]);
                            if (array_key_exists('disponivel', $camposTabelaSubRelacionada)) {
                                $sR['comparacao'][] = array('varchar', 'disponivel', '=', 'S');
                            }
                            $sR['ordem'] = isset($valS['campo_valor']) ? $valS['campo_valor'] : '';
                            $retornoSr = $this->retornosqldireto($sR, 'montar', $keyS, false, false);

                            if (sizeof($retornoSr) > 0) {
                                if (isset($valS['temAnexos']) && $valS['temAnexos']) {
                                    $tempSR = $this->agruparArray($retornoSr, $valS['campo_chave']);
                                    $anexosSR = $this->agruparArray($this->buscarAnexos(['tabela' => $keyS, 'chave' => array_keys($tempSR)], 'array'), 'chave_tabela', false);

                                    foreach ($retornoSr as $keySr => $dadosSr) {
                                        $retornoSr[$keySr]['arquivosAnexados'] = isset($anexosSR[$dadosSr[$valS['campo_chave']]]) ? $anexosSR[$dadosSr[$valS['campo_chave']]] : [];
                                    }
                                }

                                $nomeArraySubRelacionado = isset($valS['raizModelo']) ? $valS['raizModelo'] : strtolower($this->nometabela($keyS));
                                $retornoR[$keyR][$nomeArraySubRelacionado] = $retornoSr;
                            }
                        }
                    }
                }

                $retorno[$nomeArrayRelacionado] = $retornoR;
            }
        }

        $arqConTab = $caminhoApiLocal . 'apiLocal/classes/configuracoesTabelas.class.php';
        if (is_file($arqConTab)) {
            require_once $arqConTab;
            $classeConTab = '\\configuracoesTabelas';

            $config = new $classeConTab();
            $tabela = strtolower($this->nometabela($s['tabela']));

            if (method_exists($config, $tabela)) {
                $configuracoesTabela = $config->$tabela();

                if (isset($configuracoesTabela['classe']) && file_exists($caminhoApiLocal . 'apiLocal/classes/' . $configuracoesTabela['classe'] . '.class.php')) {
                    require_once $caminhoApiLocal . 'apiLocal/classes/' . $configuracoesTabela['classe'] . '.class.php';
                    if (isset($configuracoesTabela['aoBuscarParaAlterar'])) {
                        $classeABA = new ('\\' . $configuracoesTabela['aoBuscarParaAlterar']['classe'])();
                        if (method_exists($classeABA, $configuracoesTabela['aoBuscarParaAlterar']['funcaoExecutar'])) {
                            $fucnaoABA = $configuracoesTabela['aoBuscarParaAlterar']['funcaoExecutar'];
                            $retorno = $classeABA->$fucnaoABA($retorno, $p);
                        }
                    }
                }
            }
        }
        //Vendo se ha arquivos anexados
        $retorno['arquivosAnexados'] = $this->buscarAnexos(array('tabela' => $p['tabela'], 'chave' => $p['chave']), 'array');

        return $tipoRetorno == 'json' ? json_encode($retorno) : $retorno;
    }

    /**
     * Busca anexos relacionados a um registro.
     *
     * @param array $parametros Parâmetros da busca, incluindo tabela e chaves.
     * @param string $tipoRetorno Tipo de retorno desejado ('json' ou 'array').
     * Exemplo: [ 'tabela' => 'usuarios', 'chave' => 123 ]
     * @return mixed Anexos encontrados no formato desejado.
     */
    public function buscarAnexos($parametros, $tipoRetorno = 'json')
    {
        if (!is_array($parametros)) {
            throw new \InvalidArgumentException('Esperado array em $parametros');
        }
        $p = $parametros;

        $tabela = strtolower($this->nometabela($p['tabela']));
        $tabelaConsulta = $p['tabela'];

        $chave = is_array($p['chave']) ? join(',', $p['chave']) : $p['chave'];

        $config = $this->buscaConfiguracoesTabela($tabelaConsulta);

        $usarAnexosPersonalizados = isset($config['anexos']);

        if ($usarAnexosPersonalizados) {
            $configAnexos = $config['anexos'];
            $usarChaveNoCaminho = isset($configAnexos['usarChaveNoCaminho']) && $configAnexos['usarChaveNoCaminho'];
            $txt = new \ClasseGeral\ManipulaStrings();
        }

        $tabelaAnexos = $usarAnexosPersonalizados ? $configAnexos['tabela'] : 'arquivos_anexos';
        $campoChaveAnexos = $usarAnexosPersonalizados ? $configAnexos['campoRelacionamento'] : 'chave_tabela';

        $caminhoImagens = $usarAnexosPersonalizados ? $configAnexos['diretorioSalvar'] : '';

        $sql = "SELECT * FROM $tabelaAnexos ";

        $sql .= $tabelaAnexos == 'arquivos_anexos' ? " where tabela = '$tabela'" : " where $campoChaveAnexos > 0 ";

        $sql .= is_array($p['chave']) ? " and $campoChaveAnexos in($chave) " : " AND $campoChaveAnexos = $chave ";
        $sql .= isset($p['agrupamento']) ? " group by $p[agrupamento] " : '';
        $sql .= isset($p['limite']) ? " limit $p[limite] " : '';
        $sql .= ' order by posicao';
        $arquivosBase = $this->retornosqldireto($sql, '', $tabelaAnexos);

        $anexosRelacionados = [];
        if (isset($config['anexosRelacionados'])) {
            foreach ($config['anexosRelacionados'] as $tabelaRelacionada => $dadosRel) {
                $campoChaveTabelaPrincipal = $this->campochavetabela($tabela);
                $campoChaveRelacionamento = $this->campochavetabela($tabelaRelacionada);

                $campoRelacionamento = $dadosRel['campoRelacionamento'];

                $sqlBuscaRel = "select $campoChaveRelacionamento from $tabelaConsulta where $campoChaveTabelaPrincipal = $chave and disponivel = 'S'";

                $campoChaveBuscarAnexosTemp = $this->retornosqldireto($sqlBuscaRel, '', $tabela);
                $campoChaveBuscarAnexos = sizeof($campoChaveBuscarAnexosTemp) == 1 ? $campoChaveBuscarAnexosTemp[0][$campoChaveRelacionamento] : null;

                if ($campoChaveBuscarAnexos > 0) {
                    $sqlAnexos = "select * from $tabelaAnexos where tabela = '$tabelaRelacionada' and $campoChaveAnexos = $campoChaveBuscarAnexos";
                    $anexosTemp = $this->retornosqldireto($sqlAnexos, '', $tabelaAnexos);
                    foreach ($anexosTemp as $item) {
                        $item['tipoAnexo'] = 'Relacionado';
                        $anexosRelacionados[] = $item;
                    }
                }
            }
        }

        $arquivosBase = array_merge($arquivosBase, $anexosRelacionados);

        $caminho = $_SESSION[session_id()]['caminhoApiLocal'];

        $arquivos = array();
        $arquivosVerificados = [];
        if (sizeof($arquivosBase) > 0) {
            foreach ($arquivosBase as $key => $val) {
                if ($usarAnexosPersonalizados) {
                    $nomeArquivo = $configAnexos['campoNomeArquivo'] == 'campo_chave' ?
                        $txt->adicionaCarecateres($val[$configAnexos['campoChave']], $configAnexos['tamanhoNomeArquivo'], '0', 'direito') . '.' . $val['extencao'] :
                        $val[$configAnexos['campoNomeArquivo']];

                    $caminhoArquivo = $configAnexos['diretorioSalvar'];
                    $caminhoArquivo .= $usarChaveNoCaminho ? $val[$campoChaveAnexos] . '/' . $nomeArquivo : $nomeArquivo;
                } else {
                    $caminhoArquivo = $caminho . $val['arquivo'];
                }

                if (is_file($caminhoArquivo) && !array_key_exists($caminhoArquivo, $arquivosVerificados)) {

                    $arquivosVerificados[$caminhoArquivo] = $caminhoArquivo;

                    $extensao = pathinfo($caminhoArquivo, PATHINFO_EXTENSION);
                    $diretorio = pathinfo($val['arquivo'], PATHINFO_DIRNAME);
                    $arquivo = pathinfo($caminhoArquivo, PATHINFO_FILENAME) . '.' . $extensao;

                    $arquivos[$key]['chave_anexo'] = $val['chave_anexo'];
                    $arquivos[$key]['chave_tabela'] = $val['chave_tabela'];

                    $arquivos[$key]['nome'] = $val['nome'];
                    $arquivos[$key]['chave_anexo'] = $val['chave_anexo'];
                    $arquivos[$key]['tabela'] = strtolower($val['tabela']);
                    $arquivos[$key]['extensao'] = $extensao;
                    $arquivos[$key]['posicao'] = $val['posicao'];
                    $arquivos[$key]['tipoAnexo'] = isset($val['tipoAnexo']) ? $val['tipoAnexo'] : 'Original';
                    if (in_array(strtolower($extensao), $this->extensoes_imagem)) {
                        $arquivos[$key]["mini"] = $diretorio . '/mini/' . $arquivo;
                        $arquivos[$key]['tipo'] = 'imagem';
                        $arquivos[$key]['titulo'] = 'Arquivo de Imagem';
                    } else {
                        $arquivos[$key]['tipo'] = 'arquivo';
                        if ($extensao === 'pdf') {
                            $arquivos[$key]['titulo'] = 'Arquivo PDF';
                        } else if ($extensao == 'doc' || $extensao == 'docx') {
                            $arquivos[$key]['titulo'] = 'Arquivo do Word';
                        } else if ($extensao == 'xls' || $extensao == 'xlsx') {
                            $arquivos[$key]['titulo'] = 'Arquivo do Execl';
                        } else if ($extensao == 'txt') {
                            $arquivos[$key]['titulo'] = 'Arquivo de Texto';
                        } else if ($extensao == 'rar') {
                            $arquivos[$key]['titulo'] = 'Arquivo Compactado';
                        }
                    }
                    $arquivos[$key]["grande"] = $val['arquivo'];
                } else {
                    //$this->exclui('arquivos_anexos', 'chave_anexo', $val['chave_anexo']);
                }

            }
        }
        if ($tipoRetorno == 'json') {
            return json_encode($arquivos);
        } else if ($tipoRetorno == 'array') {
            return $arquivos;
        }

    }

    /**
     * Detalha um registro, incluindo dados de tabelas relacionadas e anexos.
     *
     * @param array $parametros Parâmetros da busca, incluindo tabela, chaves e tabelas relacionadas.
     * Exemplo: [ 'tabela' => 'usuarios', 'campo_chave' => 'id', 'chave' => 123 ]
     * @return string JSON com os dados detalhados do registro.
     */
    public function detalhar($parametros)
    {
        if (!is_array($parametros)) {
            throw new \InvalidArgumentException('Esperado array em $parametros');
        }
        $p = $parametros;

        $config = $this->buscaConfiguracoesTabela($p['tabela']);

        //Acrescentando a chave na variavel de campos
        $s['tabela'] = $p['tabela'];

        $s['comparacao'][] = array('int', $p['campo_chave'], '=', $p['chave']);

        $tempD = $this->retornosqldireto($s, 'montar', $p['tabela']);

        $retorno = sizeof($tempD) == 1 ? $tempD[0] : array();


        //Buscando tabelas relacionadas
        if (isset($p['tabelasRelacionadas'])) {
            foreach ($p['tabelasRelacionadas'] as $keyTR => $valTR) {
                //print_r($valTR);
                $camposTabelaRelacionada = $this->campostabela($keyTR);
                $campoRelacionamentoTR = isset($valTR['campo_relacionamento']) ? $valTR['campo_relacionamento'] : $valTR['campoRelacionamento'];
                $r = array();
                $r['tabela'] = $keyTR;

                $r['comparacao'][] = array('int', $campoRelacionamentoTR, '=', $p['chave']);
                if (array_key_exists('disponivel', $camposTabelaRelacionada)) {
                    $r['comparacao'][] = array('varchar', 'disponivel', '=', 'S');
                }
                $nomeArrayRelacionado = isset($valTR['raizModelo']) ? $valTR['raizModelo'] : strtolower($this->nometabela($keyTR));
                $r['ordem'] = isset($valTR['campo_valor']) ? $valTR['campo_valor'] : '';


                $retornoR = $this->retornosqldireto($r, 'montar', $keyTR, false, false);

                if (isset($valTR['tabelasSubRelacionadas'])) {
                    foreach ($retornoR as $keyR => $valR) {
                        $sR = array();

                        foreach ($valTR['tabelasSubRelacionadas'] as $keyS => $valS) {

                            $camposTabelaSubRelacionada = $this->campostabela($keyS);
                            $sR['tabela'] = $keyS;
                            $sR['comparacao'][] = array('int', $valTR['campo_relacionamento'], '=', $p['chave']);
                            $sR['comparacao'][] = array('int', $valS['campo_relacionamento'], '=', $valR[$valS['campo_relacionamento']]);
                            if (array_key_exists('disponivel', $camposTabelaSubRelacionada)) {
                                $sR['comparacao'][] = array('varchar', 'disponivel', '=', 'S');
                            }
                            $sR['ordem'] = isset($valS['campo_valor']) ? $valS['campo_valor'] : '';
                            $retornoSr = $this->retornosqldireto($sR, 'montar', $keyS, false, false);
                            if (sizeof($retornoSr) > 0) {
                                $nomeArraySubRelacionado = isset($valS['raizModelo']) ? $valS['raizModelo'] : strtolower($this->nometabela($keyS));
                                $retornoR[$keyR][$nomeArraySubRelacionado] = $retornoSr;
                            }
                        }
                    }
                }

                $retorno[$nomeArrayRelacionado] = $retornoR;
            }
        }

        //Vendo se ha arquivos anexados
        $retorno['arquivosAnexados'] = $this->buscarAnexos(array('tabela' => $p['tabela'], 'chave' => $p['chave']), 'array');

        return json_encode($retorno);
    }

    /**
     * Manipula dados de inclusão/edição, incluindo arquivos.
     *
     * @param array $parametros Parâmetros de manipulação.
     * @param array $arquivos Arquivos enviados.
     * Exemplo: [ 'dados' => [...], 'configuracoes' => [...] ], [ 'campoArquivo' => $_FILES['campoArquivo'] ]
     */
    public function manipula($parametros, $arquivos = [])
    {
        if (!is_array($parametros)) {
            throw new \InvalidArgumentException('Esperado array em $parametros');
        }
        $chave = 0;
        $p = $parametros;

        @session_start();
        $caminhoApiLocal = $_SESSION[session_id()]['caminhoApiLocal'];

        $dados = is_array($p['dados']) ? $p['dados'] : json_decode($p['dados'], true);

        $conf = is_array($p['configuracoes']) ? $p['configuracoes'] : json_decode($p['configuracoes'], true);

        if (isset($conf['relacionamentosVerificar']) && is_array($conf['relacionamentosVerificar'])) {
            $this->verificaRelacionamentos($parametros);
        }

        $a = [];
        if (is_array($arquivos) && sizeof($arquivos) > 0) {
            foreach ($arquivos as $campoArquivo => $arqTemp) {
                $arqTemp['tipo'] = 'files';
                $arqTemp['nomeAnexo'] = $campoArquivo;
                $a[$campoArquivo] = $arqTemp;
            }
        }

        if (isset($dados['arquivosAnexosEnviarCopiarcolar']) && sizeof($dados['arquivosAnexosEnviarCopiarcolar']) > 0) {
            foreach ($dados['arquivosAnexosEnviarCopiarcolar'] as $arqTemp) {
                $novoArq['tipo'] = 'base64';
                $novoArq['arquivo'] = $arqTemp;
                $a[] = $novoArq;
            }
        }

        $tabelaOriginal = $conf['tabela'];
        $tabela = $this->nometabela($conf['tabela']);

        //Esta variavel entrou para poder usar uma classe diferente do nome da tabela
        $classeTabela = isset($conf['classe']) ? $conf['classe'] : $tabela;

        $parametrosBuscaEstrutura = isset($conf['funcaoEstrutura']) && $conf['funcaoEstrutura'] != 'undefined' ?
            ['classe' => $classeTabela, 'funcaoEstrutura' => $conf['funcaoEstrutura']] : $classeTabela;

        //confLocal = estrutura
        $confLocal = $this->buscarEstrutura($parametrosBuscaEstrutura, 'array');

        $anexoObrigatorio = isset($confLocal['anexos']) && isset($confLocal['anexos']['obrigatorio']);
        $temArquivos = is_array($a) && sizeof($a) > 0;

        if ($anexoObrigatorio && !$temArquivos) {
            return json_encode(['erro' => 'Não Há Anexos']);
        }

        $camposObrigatoriosVazios = $this->validarCamposObrigatorios($confLocal, $dados);

        if (sizeof($camposObrigatoriosVazios) > 0) {
            return json_encode(['camposObrigatoriosVazios' => $camposObrigatoriosVazios]);
        }

        $classe = $this->criaClasseTabela($classeTabela);
        if ($this->criaFuncaoClasse($classe, 'antesSalvar')) {
            $dados = $classe->antesSalvar($dados);

            if (isset($dados['erro']))
                return json_encode(['erro' => $dados['erro']]);
        }

        if (isset($confLocal['camposNaoDuplicar']) || isset($confLocal['camposNaoDuplicarJuntos'])) {
            $camposDuplicados = $this->buscarDuplicidadeCadastro($confLocal, $dados);

            if ($camposDuplicados) {
                return json_encode(['camposDuplicados' => true]);
            }
        }

        $campoChave = $this->campochavetabela($tabelaOriginal, $conf);
        $acao = '';

        //Vendo se existem as funcoes antesSalvar e antesAlterar na classe, caso exista chamo
        if (isset($dados[$campoChave]) && $dados[$campoChave] > 0) {
            $acao = 'editar';
        } else if (!isset($dados[$campoChave]) || $dados[$campoChave] == 0) {
            $acao = 'inserir';
        }


        //Fazendo uma rotina para verificar se na configuracao da tabela ha alguma verificacao extra ao
        //incluir ou alterar dados
        //Parametro principal sao os dados da tela
        //Essa funcao deve retornar sempre a mensagem sucesso ou erro
        if ($acao == 'inserir' && isset($confLocal['funcaoVerificacaoAoIncluir'])) {

            $funcaoExiste = $this->criaFuncaoClasse($classe, $confLocal['funcaoVerificacaoAoIncluir']);

            if ($classe && $funcaoExiste) {
                $funcaoExecutarVI = $confLocal['funcaoVerificacaoAoIncluir'];
                $validacao = $classe->$funcaoExecutarVI($dados);
                if (isset($validacao['erro'])) {
                    return json_encode($validacao);
                } else {
                    $dados = $validacao;
                }
            }
        } else if ($acao == 'editar' && isset($confLocal['funcaoVerificacaoAoAlterar'])) {
            $funcaoExiste = $this->criaFuncaoClasse($classe, $confLocal['funcaoVerificacaoAoAlterar']);

            if ($classe && $funcaoExiste) {
                $funcaoExecutarVA = $confLocal['funcaoVerificacaoAoAlterar'];
                $validacao = $classe->$funcaoExecutarVA($dados);

                if (isset($validacao['erro'])) {
                    return json_encode($validacao);
                } else {
                    $dados = $validacao;
                }
            }
        }

        //Apos fazer as verificacoes de obrigatoriedade e validacoes
        //verifico se ha uma funcao de manipulacao personalizada
        if (isset($conf['funcaoManipula']) && $conf['funcaoManipula'] != 'undefined') {
            if (file_exists($caminhoApiLocal . 'apiLocal/classes/' . $conf['classe'] . '.class.php')) {
                require_once $caminhoApiLocal . 'apiLocal/classes/' . $conf['classe'] . '.class.php';
                $classeManipula = new ('//' . $conf['classe'])();
                $funcaoExecutar = $conf['funcaoManipula'];
                $dados['acaoManipula'] = $acao;
                return $classeManipula->$funcaoExecutar($dados, $a);
            } else {
                return 'nao tem';
            }
        }

        if ($acao == 'editar') {
            $chave = $dados[$campoChave];
        } else if ($acao == 'inserir') {
            $chave = $this->proximachave($tabelaOriginal);
        }

        if ($chave == null || $chave == 'null') {
            return 'Erro ao Incluir';
        }


        //funcoes para os anexos
        if ($temArquivos) {
            //Por enquanto nao posso usar anexos nos campos e na diretiva ao mesmo tempo.
            //Posteriormente terei que corrigir isso.

            foreach ($a as $key => $arq) {
                if (is_int($key)) { //Neste caso, a key e int pois sao da diretiva Arquivos Anexos
                    $arquivosAnexos[] = $arq;
                } else if (isset($dados[$key])) { // Neste caso sao arquivos de tela, um arquivo para cada campo
                    $arquivosTela[$key] = $arq;
                }
            }

            if (isset($arquivosTela)) {
                if (sizeof($conf['arquivosAnexar']) > 0) { //Neste caso e campo da tela


                    $up = new \ClasseGeral\UploadSimples();
                    $dir = new \ClasseGeral\GerenciaDiretorios();
                    $sessao = new \ClasseGeral\ManipulaSessao();
                    $raiz = $sessao->pegar('caminhoApiLocal');

                    $arqConf = $this->agruparArray($conf['arquivosAnexar'], 'campo');

                    foreach ($arqConf as $key => $arq) {
                        if (isset($a[$arq['campo']])) { //Vendo se existe o $_Files
//                            //Se tem destino nos atributos da imagem salvo no destino estipulado, senao em arquivos anexos
                            $caminhoBase = isset($arq['destino']) && $arq['destino'] != '' ? $arq['destino'] . '/' : 'arquivos_anexos/' . strtolower($tabela) . '/';


//                            //Vendo se e para criar um diretorio com a chave ou salvar direto no destino
                            $caminhoBase .= isset($arq['salvarEmDiretorio']) && $arq['salvarEmDiretorio'] == 'true' ? $chave . '/' : '';
//
                            $caminhoUpload = $raiz . $caminhoBase;

                            $dir->criadiretorio($caminhoUpload);

                            $ext = strtolower(pathinfo($a[$arq['campo']]["name"], PATHINFO_EXTENSION));

                            $novo_nome = isset($arq['nomeAnexo']) && $arq['nomeAnexo'] != ''
                                ? $arq['nomeAnexo'] . '.' . $ext : strtolower($this->nometabela($tabela)) . '_' . $arq['campo'] . '_' . $chave . '.' . $ext;

                            $dados[$arq['campo']] = $caminhoBase . $novo_nome;

                            if (in_array($ext, $this->extensoes_imagem)) {
                                $up->upload($a[$arq['campo']], $novo_nome, $caminhoUpload, $arq['largura'], $arq['altura']);
                            } else if (in_array($ext, $this->extensoes_arquivos)) {
                                $up->upload($a[$arq['campo']], $novo_nome, $caminhoUpload, 0, 0);
                            }
                            $dados[$key] = $caminhoBase . '/' . $novo_nome;
                            //Removo o campo do array de arquivos
                            unset($a[$arq['campo']]);
                        }
                    }
                }
            }

            if (isset($arquivosAnexos)) {
                $configAnexos = array(
                    'tabela' => $tabela,
                    'campo_chave' => $campoChave,
                    'chave' => $chave,
                    'origem' => 'inclusao'
                );
                $this->anexarArquivos($configAnexos, $arquivosAnexos);
            }
        }


        if ($acao == 'inserir') {
            $chave = $this->inclui($tabelaOriginal, $dados, $chave, false);

        } else if ($acao == 'editar') {
            $dados['disponivel'] = !isset($dados['disponivel']) ? 'S' : $dados['disponivel'];
            $chave = $this->altera($tabelaOriginal, $dados, $chave, false);
        }

        //Sao 2 tipo de aposSalvar um pode sar padrao se so tiver uma estrutura na classe ou declarada na estrutura caso hajam mais de uma na classe
        $aposSalvarPadrao = $this->criaFuncaoClasse($classe, 'aposSalvar');
        $aposSalvarPersonalizado = isset($confLocal['funcaoAposSalvar']) && $this->criaFuncaoClasse($classe, $confLocal['funcaoAposSalvar']);

        if ($aposSalvarPadrao || $aposSalvarPersonalizado) {
            $nomeFuncao = $aposSalvarPadrao ? 'aposSalvar' : $confLocal['funcaoAposSalvar'];
            $dados['acao'] = $acao;
            $dados[$campoChave] = $chave;

            $dados = $classe->$nomeFuncao($dados);

            if (isset($dados['erro']))
                return json_encode(['erro' => $dados['erro']]);
        }

        if (($acao == 'inserir' or $acao == 'editar') && $chave > 0) {
            //Vendo se ha tabelas relacionadas

            if (isset($conf['tabelasRelacionadas']) && is_array($conf['tabelasRelacionadas'])) {
                //Varrendo as tabelas relacionadas
                foreach ($conf['tabelasRelacionadas'] as $tabelaR => $infoTabelaR) {
                    //Pegando a variavel que contem os dados, vendo se e um array ou se esta direto na raiz
                    $variavelTabRel = isset($infoTabelaR['raizModelo']) ? $infoTabelaR['raizModelo'] : 'raiz';

                    //Pegando o campo chave da tabela
                    $campoChaveTabRel = $this->campochavetabela($tabelaR, $infoTabelaR);
                    $campoRelacionamentoTabRel = isset($infoTabelaR['campo_relacionamento']) ? $infoTabelaR['campo_relacionamento'] : $infoTabelaR['campoRelacionamento'];

                    //Vendo se existem dados da tabela relacionada, e se e um array
                    if (isset($dados[$variavelTabRel])) {
                        //Varrendo os dados da tabela relacionada
                        foreach ($dados[$variavelTabRel] as $keyR => $dadosR) {
                            //Pondo na tabela relacionada a chave da tabela principal

                            $dadosR[$campoChave] = $chave;
                            //Vendo se e para alterar ou par incluir
                            if (isset($dadosR[$campoChaveTabRel]) && $dadosR[$campoChaveTabRel] > 0) {
                                $novaChaveTabRel = $this->altera($tabelaR, $dadosR, $dadosR[$campoChaveTabRel], false);
                            } else {
                                $novaChaveTabRel = $this->inclui($tabelaR, $dadosR, 0, false);
                            }
                            $dadosR[$campoChaveTabRel] = $novaChaveTabRel;

                            //Tratando as SubRelacionadas
                            if (isset($infoTabelaR['tabelasSubRelacionadas'])) {
                                //Varrendo as tabelas Sub Relacionadas
                                foreach ($infoTabelaR['tabelasSubRelacionadas'] as $tabelaSR => $infoTabelaSR) {
                                    $variavelTabSubRel = $infoTabelaSR['raizModelo'];
                                    //Pegando o campo chave
                                    $campoChaveTabSubRel = $this->campochavetabela($tabelaSR, $infoTabelaSR);

                                    if (isset($infoTabelaSR['campo_relacionamento']))
                                        $campoRelacionamentoTabSubRel = $infoTabelaSR['campo_relacionamento'];
                                    else if (isset($infoTabelaSR['campoRelacionamento']))
                                        $campoRelacionamentoTabSubRel = $infoTabelaSR['campoRelacionamento'];

                                    if (isset($dadosR[$variavelTabSubRel])) {
                                        foreach ($dadosR[$variavelTabSubRel] as $keySR => $dadosSR) {
                                            //Pondo os campos de relacionamentos com as tabelas superiores
                                            $dadosSR[$campoChave] = $chave;
                                            $dadosSR[$campoRelacionamentoTabSubRel] = $dadosR[$campoRelacionamentoTabSubRel];
                                            // print_r($dadosSR);
                                            if (isset($dadosSR[$campoChaveTabSubRel]) && $dadosSR[$campoChaveTabSubRel] > 0) {
                                                $this->altera($tabelaSR, $dadosSR, $dadosSR[$campoChaveTabSubRel], false);
                                            } else {
                                                $this->inclui($tabelaSR, $dadosSR);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } else if (isset($infoTabelaR['campo_chave_origem']) && isset($dados[$infoTabelaR['campo_chave_origem']])) {
                        //Neste caso os dados estao diretos na raiz, pode ser o caso de cadastro em botao novo de autocompleta

                        $campoChaveOrigem = $infoTabelaR['campo_chave_origem'];

                        $sqlR =
                            "SELECT $campoChaveTabRel FROM $tabelaR WHERE  $campoChaveOrigem = $dados[$campoChaveOrigem] AND $campoRelacionamentoTabRel = $dados[$campoRelacionamentoTabRel]";

                        $relacionamentoTemp = $this->retornosqldireto($sqlR);

                        $chaveRelacionamento = sizeof($relacionamentoTemp) == 1 ? $relacionamentoTemp[0][$campoChaveTabRel] : 0;

                        if ($chaveRelacionamento == 0) {
                            $dadosR[$campoChave] = $chave;
                            $dadosR[$campoRelacionamentoTabRel] = $dados[$campoRelacionamentoTabRel];
                            $chaveRelacionamento = $this->inclui($tabelaR, $dadosR);
                        }

                        if ($chaveRelacionamento > 0) {
                            if (isset($infoTabelaR['tabelasSubRelacionadas'])) {

                                foreach ($infoTabelaR['tabelasSubRelacionadas'] as $tabelaSR => $infoTabelaSR) {
                                    $campoChaveOrigemSR = $infoTabelaSR['campo_chave_origem'];
                                    if (isset($dados[$campoChaveOrigemSR])) {
                                        $campoChaveTabSubRel = $this->campochavetabela($tabelaSR, $infoTabelaSR);

                                        $sqlSR = "SELECT $campoChaveTabSubRel FROM $tabelaSR where $campoChaveOrigem = $dados[$campoChaveOrigem]";
                                        $sqlSR .= " AND $campoRelacionamentoTabRel = $dados[$campoRelacionamentoTabRel]";
                                        $sqlSR .= $dados[$campoChaveOrigemSR] != '' && $dados[$campoChaveOrigemSR] != 'undefined' ?
                                            " AND $campoChaveOrigemSR = $dados[$campoChaveOrigemSR]" : '';

                                        $subRelacionamentoTemp = $this->retornosqldireto($sqlSR);
                                        $chaveSubRelacionamento = sizeof($subRelacionamentoTemp) == 1 ? $subRelacionamentoTemp[0][$campoChaveTabSubRel] : 0;

                                        if ($chaveSubRelacionamento == 0) {
                                            $dadosSR[$campoChaveOrigem] = $dados[$campoChaveOrigem];
                                            $dadosSR[$campoChaveOrigemSR] = $chave;
                                            $dadosSR[$campoRelacionamentoTabRel] = $dados[$campoRelacionamentoTabRel];
                                            //print_r($dadosSR);
                                            $this->inclui($tabelaSR, $dadosSR, 0, false);
                                        }
                                    }

                                }

                            }
                        }
                    }
                }
            }
        }

        if (is_file($caminhoApiLocal . 'apiLocal/classes/configuracoesTabelas.class.php')) {
            require_once $caminhoApiLocal . 'apiLocal/classes/configuracoesTabelas.class.php';
            $config = new ('\\configuracoesTabelas')();
            $tabela = strtolower($tabela);

            if (method_exists($config, $tabela)) {
                $configuracoesTabela = $config->$tabela();

                if (isset($configuracoesTabela['classe']) && file_exists($caminhoApiLocal . 'apiLocal/classes/' . $configuracoesTabela['classe'] . '.class.php')) {
                    require_once $caminhoApiLocal . 'apiLocal/classes/' . $configuracoesTabela['classe'] . '.class.php';
                    if ($acao == 'inserir' && isset($configuracoesTabela['aoIncluir'])) {
                        $classeAI = new ('\\' . $configuracoesTabela['aoIncluir']['classe'])();
                        if (method_exists($classeAI, $configuracoesTabela['aoIncluir']['funcaoExecutar'])) {
                            $fucnaoAI = $configuracoesTabela['aoIncluir']['funcaoExecutar'];
                            $dados[$campoChave] = $chave;
                            $classeAI->$fucnaoAI($dados, $acao);
                        }
                    } else if ($acao == 'editar' && isset($configuracoesTabela['aoAlterar'])) {

                        $classeAI = new ('\\' . $configuracoesTabela['aoAlterar']['classe'])();
                        if (method_exists($classeAI, $configuracoesTabela['aoAlterar']['funcaoExecutar'])) {
                            $fucnaoAI = $configuracoesTabela['aoAlterar']['funcaoExecutar'];
                            $dados[$campoChave] = $chave;
                            $classeAI->$fucnaoAI($dados, $acao);
                        }
                    }
                }
            }
        }
        return json_encode(array('chave' => $chave));

    }

    /**
     * @param $parametros
     * #tabelaRelacionamento
     * #camposRelacionados
     * Funcao criada para quando inserir em alguma tabela, ver se os campos tem de ver verificados em algum relacionamento
     * Ex. Na SegMed, ao salvar o colaborador, verifica se os setor esta na tabela empresas setores, se o setor e a secao
     * estao na tabela empresas_setores_secoes ou se empresa, setor e funcao estao em empresas_setores_funcoes
     *
     *
     */
    public
    function verificaRelacionamentos($parametros)
    {
        $dados = is_array($parametros['dados']) ? $parametros['dados'] : json_decode($parametros['dados'], true);
        $confi = is_array($parametros['configuracoes']) ? $parametros['configuracoes'] : json_decode($parametros['configuracoes'], true);

        foreach ($confi['relacionamentosVerificar'] as $relacionamento) {

            $campoChave = $this->campochavetabela($relacionamento['tabelaRelacionamento']);

            $sql = "SELECT $campoChave FROM  $relacionamento[tabelaRelacionamento] WHERE $campoChave > 0";
            foreach ($relacionamento['camposRelacionados'] as $camposRelacionado) {
                $sql .= isset($dados[$camposRelacionado]) ? " AND $camposRelacionado = $dados[$camposRelacionado]" : '';
            }
            $sql = strtolower($sql);

            $relacionamentoExiste = sizeof($this->retornosqldireto($sql)) > 0;

            if (!$relacionamentoExiste) {

                $dadosInserir = array();
                foreach ($relacionamento['camposRelacionados'] as $campo) {
                    if (isset($dados[$campo])) {
                        $dadosInserir[$campo] = $dados[$campo];
                    }
                }

                $this->inclui($relacionamento['tabelaRelacionamento'], $dadosInserir); //echo "\n";
            }
        }
    }

    public
    function anexarArquivos($parametros, $arquivosPost = [])
    {
        $p = $parametros;

        $caminho = $_SESSION[session_id()]['caminhoApiLocal'];
        $tabela = $this->nometabela($p['tabela']);

        ini_set('display_errors', 1);

        $arquivosCopiarColar = [];

        //Neste caso esta na consulta e enviando anexos por copiar e colar
        if (isset($parametros['arquivosAnexosEnviarCopiarColar'])) {
            $arquivosCopiarColar = json_decode($parametros['arquivosAnexosEnviarCopiarColar'], true);
        }
        if (sizeof($arquivosPost) == 0) {
            //Nesse caso nao sei exatamente de onde vem, rssss
            $arquivosPost = $_FILES ?? array();
        }

        $arquivos = array_merge($arquivosCopiarColar, $arquivosPost);


        if (sizeof($arquivos) > 0) {
            $chave = $p['chave'];

            $caminho = $_SESSION[session_id()]['caminhoApiLocal'];
            $destinoBase = 'arquivos_anexos/' . $tabela . '/' . $chave . '/';
            $destino = $caminho . 'arquivos_anexos/' . $tabela . '/' . $chave . '/';
            $destinom = $caminho . 'arquivos_anexos/' . $tabela . '/' . $chave . '/mini/';

            $up = new \ClasseGeral\UploadSimples();
            $func = new \ClasseGeral\ManipulaStrings();
            $dir = new \ClasseGeral\GerenciaDiretorios();

            if (!is_dir($destino)) {
                $dir->criadiretorio($destino);
            }
            if (!is_dir($destinom)) { //Depois tenho que comparar se é imagem, se não for, não precisa criar esta pasta
                $dir->criadiretorio($destinom);
            }

            foreach ($arquivos as $key => $a) {
                $tipoArquivos = $a['tipo'] ?? 'files';

                $ext = $tipoArquivos == 'files' ? strtolower(pathinfo($a["name"], PATHINFO_EXTENSION)) : 'png';

                $nome = $tipoArquivos == 'files' ? mb_convert_encoding(pathinfo($func->limparacentos($a["name"], true), PATHINFO_FILENAME), 'utf8') :
                    $tabela . '_' . $chave . '_' . $this->proximachave('arquivos_anexos');
                $p['extensao'] = $ext;
                $p['nome'] = $nome;
                $novo_nome = $nome;

                $largura = $parametros['largura'] ?? 1024;
                $altura = $parametros['altura'] ?? 768;

                $nomeComExtencao = $novo_nome . '.' . $ext;

                if (in_array($ext, $this->extensoes_imagem)) {
                    if ($tipoArquivos == 'files') {
                        $dimensoes = $this->defineTamanhoImagem($a, 'files', $largura, $altura);
                        //Upload da miniatura
                        $up->upload($a, $nomeComExtencao, $destinom, $dimensoes['larguraThumb'], $dimensoes['alturaThumb']);
                        //Upload Padrao
                        $up->upload($a, $nomeComExtencao, $destino, $dimensoes['largura'], $dimensoes['altura']);
                    } else if ($tipoArquivos == 'base64') {
                        $arquivoUp = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $a['arquivo']));
                        $novoNomeBase64Temp = $destino . 'temp_' . $nomeComExtencao;
                        $novoNomeBase64 = $destino . $nomeComExtencao;
                        file_put_contents($novoNomeBase64Temp, $arquivoUp);
                        $dimensoes = $this->defineTamanhoImagem($novoNomeBase64Temp, 'base64', $largura, $altura);

                        //Criando a imagem grande
                        $imagem = imagecreatetruecolor($dimensoes['largura'], $dimensoes['altura']);
                        $imagemSource = imagecreatefrompng($novoNomeBase64Temp);
                        imagecopyresized($imagem, $imagemSource, 0, 0, 0, 0, $dimensoes['largura'], $dimensoes['altura'],
                            $dimensoes['larguraOriginal'], $dimensoes['alturaOriginal']);
                        imagepng($imagem, $novoNomeBase64);

                        //Criando o Thumb
                        $thumb = imagecreatetruecolor($dimensoes['larguraThumb'], $dimensoes['alturaThumb']);
                        $thumbSource = imagecreatefrompng($novoNomeBase64Temp);
                        imagecopyresized($thumb, $thumbSource, 0, 0, 0, 0, $dimensoes['larguraThumb'], $dimensoes['alturaThumb'],
                            $dimensoes['larguraOriginal'], $dimensoes['alturaOriginal']);
                        imagepng($thumb, $destinom . $nomeComExtencao);
                        unlink($novoNomeBase64Temp);

                    }
                } else if (in_array($ext, $this->extensoes_arquivos)) {
                    $up->upload($a, $nomeComExtencao, $destino, $largura, $altura);
                }

                if (is_file($destino . $nomeComExtencao)) {
                    $tabelaP = strtolower($tabela);
                    $sqlP = "SELECT COUNT(chave_anexo) AS ultima_posicao FROM arquivos_anexos  WHERE tabela = '$tabelaP' AND chave_tabela = $chave";
                    $tempP = $this->retornosqldireto($sqlP, '', '', false, false);
                    $p['posicao'] = sizeof($tempP) == 1 ? intval($tempP[0]['ultima_posicao']) + 1 : 1;

                    $p['arquivo'] = $destinoBase . $nomeComExtencao;
                    $p['chave_tabela'] = $p['chave'];
                    $p['tabela'] = $tabela;

                    $chaveRetorno = $this->inclui('arquivos_anexos', $p, 0, false, false);
                } else {
                    $chaveRetorno = 0;
                }
            }

            //Nova funcao que vai verificar se tem na classe alguma funcao chamada aoAnexar
            $nomeClasse = $this->nomeClase($tabela);
            $arquivoClasse = $caminho . 'apiLocal/classes/' . $nomeClasse . '.class.php';

            if (file_exists($arquivoClasse)) {
                require_once $arquivoClasse;
                $classe = new ('\\' . $nomeClase)();
                if (method_exists($classe, 'aoIncluirAnexos')) {
                    $classe->aoIncluirAnexos($p);
                }
            }

            return json_encode(array('chave' => $chaveRetorno));
        }
    }

    /**
     * Define as dimensões da imagem com base em restrições de largura e altura.
     *
     * @param mixed $arquivo Arquivo da imagem (array para upload ou string para base64).
     * @param string $tipo Tipo de entrada ('files' ou 'base64').
     * @param int|string $largEnt Largura desejada ou 'original' para manter original.
     * @param int|string $altEnt Altura desejada ou 'original' para manter original.
     * Exemplo: defineTamanhoImagem($_FILES['imagem'], 'files', 800, 600)
     * @return array Array contendo as dimensões original e nova (largura e altura).
     */
    public function defineTamanhoImagem($arquivo, $tipo, $largEnt = 1024, $altEnt = 768): array
    {
        // Garantir que $largEnt e $altEnt sejam numéricos se não forem 'original' ou 'undefined'
        if ($largEnt !== 'original' && $largEnt !== 'undefined' && !is_numeric($largEnt)) {
            $largEnt = 1024;
        }
        if ($altEnt !== 'original' && $altEnt !== 'undefined' && !is_numeric($altEnt)) {
            $altEnt = 768;
        }

        $larguraOriginal = 0;
        $alturaOriginal = 0;

        if ($tipo == 'files') {
            list($larguraOriginal, $alturaOriginal) = getimagesize($arquivo['tmp_name']);
        } else if ($tipo = 'base64') {
            list($larguraOriginal, $alturaOriginal) = getimagesize($arquivo);
        }

        $largMax = 1024;
        $altMax = 768;

        if ($largEnt == 'original') {
            $largMax = $larguraOriginal;
        } else if ($largEnt != 'undefined') {
            $largMax = $largEnt;
        }

        if ($altEnt == 'original') {
            $altMax = $alturaOriginal;
        } else if ($altEnt != 'undefined') {
            $altMax = $altEnt;
        }

        $orientacao = $larguraOriginal > $alturaOriginal ? 'paisagem' : 'retrato';

        $valorProporcionar = $orientacao == 'paisagem' ? $largMax / $larguraOriginal : $altMax / $alturaOriginal;

        $novaLargura = (int)($larguraOriginal * $valorProporcionar);
        $novaLargura = min($novaLargura, $larguraOriginal);

        $novaAltura = (int)($alturaOriginal * $valorProporcionar);
        $novaAltura = min($novaAltura, $alturaOriginal);

        return [
            'alturaOriginal' => $alturaOriginal,
            'larguraOriginal' => $larguraOriginal,
            'altura' => $novaAltura,
            'largura' => $novaLargura,
            'alturaThumb' => (int)(($novaAltura * $valorProporcionar) * 0.33),
            'larguraThumb' => (int)(($novaLargura * $valorProporcionar) * 0.33)
        ];
    }

    /**
     * Exclui um registro, realizando exclusão lógica ou física, dependendo da configuração.
     *
     * @param array $parametros Parâmetros para exclusão.
     * Exemplo: [ 'tabela' => 'usuarios', 'campo_chave' => 'id', 'chave' => 123 ]
     * @return false|string
     */
    public function excluir($parametros)
    {
        if (!is_array($parametros)) {
            throw new \InvalidArgumentException('Esperado array em $parametros');
        }
        $p = $parametros;

        $campos_tabela = $this->campostabela($p['tabela']);
        $campoChave = isset($p['campo_chave']) ? $p['campo_chave'] : $this->campochavetabela($p['tabela']);

        //Variavel que define se sera excluido ou atualizado para arquivado = 'E'
        $tabelaOriginal = strtolower($p['tabela']);
        $nomeTabela = $this->nometabela($p['tabela']);

        $aoExcluir = isset($p['aoExcluir']) ? $p['aoExcluir'] : 'A';

        $sql = "select campo_principal, tabela_secundaria, campo_secundario from view_relacionamentos where tabela_principal = '$nomeTabela'";

        $relacionamentos = $this->retornosqldireto($sql, '', $nomeTabela, false);

        //20240131
        //Comentei as exclusoes de tabelas relacionadas, depois preciso ver melhor isso
        if ($aoExcluir == 'A') { //Atualizando os campos arquivado = E
            $sqlE = "select $campoChave from $tabelaOriginal where $campoChave = $p[chave]";
            $dados = $this->retornosqldireto($sqlE, '', $tabelaOriginal)[0];

            $dados['disponivel'] = 'E';

            if (isset($campos_tabela['arquivado'])) {
                $dados['arquivado'] = 'E';
            }

            if (isset($campos_tabela['ativo'])) {
                $dados['ativo'] = '0';
            }
            if (isset($campos_tabela['publicar'])) {
                $dados['publicar'] = '0';
            }

            $nova_chave = $this->altera($tabelaOriginal, $dados, $p['chave'], false);

        } else { //Excluindo os campos
            //Tenho que fazer o log para essa situação.
            $nova_chave = $this->exclui($p['tabela'], $campoChave, $p['chave'], 'nenhuma', false);
        }

        $this->excluirAnexosTabela($tabelaOriginal, $p['chave']);

        $caminhoApiLocal = $this->pegaCaminhoApi();

        if (is_file($caminhoApiLocal . 'apiLocal/classes/configuracoesTabelas.class.php')) {

            require_once $caminhoApiLocal . 'apiLocal/classes/configuracoesTabelas.class.php';
            $config = new ('\\configuracoesTabelas')();
            if (method_exists($config, $nomeTabela)) {
                $configuracoesTabela = $config->$nomeTabela();

                if (isset($configuracoesTabela['classe']) && file_exists($caminhoApiLocal . 'apiLocal/classes/' . $configuracoesTabela['classe'] . '.class.php')) {
                    require_once $caminhoApiLocal . 'apiLocal/classes/' . $configuracoesTabela['classe'] . '.class.php';
                    if (isset($configuracoesTabela['aoExcluir']['classe'])) {
                        $classeAE = new ('\\' . $configuracoesTabela['aoExcluir']['classe'])();
                        if (method_exists($classeAE, $configuracoesTabela['aoExcluir']['funcaoExecutar'])) {
                            $fucnaoAE = $configuracoesTabela['aoExcluir']['funcaoExecutar'];
                            $classeAE->$fucnaoAE($p, $aoExcluir);
                        }
                    }
                }
            }
        }

        return json_encode(array('chave' => $nova_chave));
    }

    /**
     * Exclui anexos relacionados a um registro em uma tabela.
     *
     * @param string $tabela Nome da tabela.
     * @param mixed $chave Chave do registro.
     * Exemplo: excluirAnexosTabela('usuarios', 123)
     */
    public function excluirAnexosTabela($tabela, $chave)
    {
        if (!is_string($tabela)) {
            throw new \InvalidArgumentException('Esperado string em $tabela');
        }        

        $arquivos = $this->buscarAnexos(['tabela' => $tabela, 'chave' => $chave], 'array');

        if (sizeof($arquivos) > 0) {
            foreach ($arquivos as $anexo) {
                $this->excluiranexo($anexo, '');
            }

            $caminho = $_SESSION[session_id()]['caminhoApiLocal'];
            $diretorio = $caminho . '/arquivos_anexos/' . $tabela . '/' . $chave . '/';

            $dir = new \ClasseGeral\GerenciaDiretorios();
            $dir->apagadiretorio($diretorio);
        }
    }

    /**
     * Exclui um anexo específico.
     *
     * @param array $anexo Dados do anexo a ser excluído.
     * @param string $origem Origem da exclusão (padrão ou personalizada).
     * Exemplo: excluiranexo(['tabela'=>'usuarios','chave_anexo'=>1,'mini'=>'/mini/1.png','grande'=>'/1.png'], 'padrao')
     * @return string JSON com o resultado da exclusão.
     */
    public function excluiranexo($anexo, $origem = 'padrao')
    {
        if (!is_array($anexo)) {
            throw new \InvalidArgumentException('Esperado array em $anexo');
        }
        $caminho = $this->pegaCaminhoApi();
        $tabela = $this->nometabela($anexo['tabela']);

        @session_start();
        $caminho = $_SESSION[session_id()]['caminhoApiLocal'];
        if (isset($anexo['mini']) && is_file($caminho . $anexo["mini"])) {
            unlink($caminho . $anexo["mini"]);
        }

        if (is_file($caminho . $anexo["grande"])) {
            unlink($caminho . $anexo["grande"]);
        }


        $this->exclui('arquivos_anexos', 'chave_anexo', $anexo['chave_anexo']);

        $nomeClasse = '\\' . $this->nomeClase($tabela);
        $arquivoClasse = $caminho . 'apiLocal/classes/' . $nomeClasse . '.class.php';

//        //Nova funcao que vai verificar se tem na classe alguma funcao chamada aoAnexar
        if (file_exists($arquivoClasse)) {
            require_once $arquivoClasse;
            $classe = new  $nomeClasse();

            if (method_exists($classe, 'aoExcluirAnexos')) {
                $classe->aoExcluirAnexos($anexo);
            } else {
                return json_encode(['erro' => 'Erro ao excluir o anexo']);
            }
        }

        if ($origem == 'padrao') {
            return json_encode(['sucesso' => 'Anexo excluído com sucesso']);
        }
        //*/
    }

    /**
     * Altera os dados de um anexo.
     *
     * @param array $parametros Dados do anexo a serem alterados.
     * Exemplo: alterarAnexo(['chave_anexo'=>1,'nome'=>'novo_nome.png'])
     */
    public function alterarAnexo($parametros)
    {
        if (!is_array($parametros)) {
            throw new \InvalidArgumentException('Esperado array em $parametros');
        }
        $chave = $this->altera('arquivos_anexos', $parametros, $parametros['chave_anexo'], false);
        echo json_encode(array('chave' => $chave));
    }

    /**
     * Rotaciona uma imagem anexada.
     *
     * @param int $chave_imagem Chave da imagem a ser rotacionada.
     * Exemplo: rotacionarImagem(123)
     * @return int Indicador de sucesso.
     */
    public function rotacionarImagem($chave_imagem)
    {
        if (!is_numeric($chave_imagem)) {
            throw new \InvalidArgumentException('Esperado inteiro em $chave_imagem');
        }
        @session_start();
        $caminho = $_SESSION[session_id()]['caminhoApiLocal'];
        $arquivo = $this->retornosqldireto("SELECT * FROM arquivos_anexos WHERE chave_anexo = $chave_imagem")[0];
        $ext = pathinfo($arquivo["arquivo"], PATHINFO_EXTENSION);

        $arquivoCompleto = $caminho . $arquivo['arquivo'];
        $diretorio = pathinfo($arquivoCompleto, PATHINFO_DIRNAME);
        $nomeArquivo = pathinfo($arquivoCompleto, PATHINFO_FILENAME) . '.' . pathinfo($arquivoCompleto, PATHINFO_EXTENSION);

        if ($ext == 'jpg' || $ext == 'jpeg') {
            $imagem = imagecreatefromjpeg($arquivoCompleto);
            $imagem = imagerotate($imagem, 90, 0);
            imagejpeg($imagem, $arquivoCompleto);

            if (is_file($diretorio . '/mini/' . $nomeArquivo)) {
                $imagemM = imagecreatefromjpeg($diretorio . '/mini/' . $nomeArquivo);
                $imagemM = imagerotate($imagemM, 90, 0);
                imagejpeg($imagemM, $diretorio . '/mini/' . $nomeArquivo);
            }
        } else if ($ext == 'png') {
            $imagem = imagecreatefrompng($arquivoCompleto);
            $imagem = imagerotate($imagem, 90, 0);
            imagepng($imagem, $arquivoCompleto);

            if (is_file($diretorio . '/mini/' . $nomeArquivo)) {
                $imagemM = imagecreatefrompng($diretorio . '/mini/' . $nomeArquivo);
                $imagemM = imagerotate($imagemM, 90, 0);
                imagepng($imagemM, $diretorio . '/mini/' . $nomeArquivo);
            }
        }
        return 1;
        //*/
    }

    /**
     * Altera a posição de anexos trocando as posições entre dois registros.
     *
     * @param array $parametros Parâmetros contendo as chaves e novas posições dos anexos.
     */
    public
    function alterarPosicaoAnexo($parametros)
    {

        $p = $parametros;
        $sql = "UPDATE arquivos_anexos SET posicao = $p[posicao1] WHERE chave_anexo = $p[chave1];";
        $this->executasql($sql, $this->pegaDataBase('arquivos_anexos'));
        $sql = "UPDATE arquivos_anexos SET posicao = $p[posicao2] WHERE chave_anexo = $p[chave2];";
        $this->executasql($sql, $this->pegaDataBase('arquivos_anexos'));
    }

    /**
     * Busca uma variável da sessão.
     *
     * @param string $var Nome da variável.
     * @param string $tipoRetorno Tipo de retorno desejado ('json' ou 'array').
     * @return mixed Valor da variável da sessão no formato desejado.
     */
    public function buscarSessao($var, $tipoRetorno = 'json')
    {
        $fun = new \ClasseGeral\ManipulaSessao();
        return $tipoRetorno == 'json' ? json_encode($fun->pegar($var)) : $fun->pegar($var);
    }

////////////////////FIM DAS FUNCOES RELACIONADAS A ARQUIVOS RELACIONADOS/////////////

    /**
     * Busca um registro por chave.
     *
     * @param array $parametros Parâmetros da busca, incluindo tabela, campo_chave e chave.
     * @return string JSON com os dados do registro encontrado.
     */
    public function buscarPorChave($parametros)
    {
        $tabela = strtolower($parametros['tabela']);
        $campo_chave = strtolower($parametros['campo_chave']);

        $sql = "SELECT * FROM $tabela WHERE $campo_chave = $parametros[chave]";

        $retorno = $this->retornosqldireto($sql, '', $tabela);
        $retorno = $this->retornosqldireto($sql, '', $tabela);
        $retorno = sizeof($retorno) == 1 ? $retorno[0] : array();

        return json_encode($retorno);
    }

    /**
     * Busca uma chave com base em múltiplos campos.
     *
     * @param array $parametros Parâmetros da busca, incluindo tabela, campos e valores.
     * @return string JSON com a chave encontrada.
     */
    public
    function buscarChavePorCampos($parametros)
    {
        $p = isset($parametros['dados']) ? json_decode($parametros['dados'], true) : $parametros;
        $tabelaOrigem = isset($p['tabelaOrigem']) && $p['tabelaOrigem'] != 'undefined' ? $p['tabelaOrigem'] : '';
        $chave = $this->buscachaveporcampos($p['tabela'], $p['campos'], $p['valores'], $tabelaOrigem, false);
        return json_encode(['chave' => $chave]);
        //*/
    }

    /**
     * Monta um array de campos a partir de um array associativo.
     *
     * @param array $array Array de entrada.
     * @param string $campo Campo a ser extraído de cada item do array.
     * @return array Array contendo os valores do campo especificado.
     */
    public
    function montarArrayDeCampos($array, $campo)
    {
        $retorno = array();
        foreach ($array as $item) {
            $retorno[] = $item[$campo];
        }
        return $retorno;
    }

    /**
     * Mantém o cache, utilizado para debug.
     */
    public
    function manterCache()
    {
        echo date('H:i:s');
    }

    private function totalItensConsulta($parametros, $configuracoesTabela = array())
    {
        $p = $parametros;

        $s['tabela'] = $this->nometabela($p['tabela']);
        $s['tabelaConsulta'] = isset($p['tabelaConsulta']) ? $p['tabelaConsulta'] : $p['tabela'];
        $s['campos'] = ['count(' . $p['campo_chave'] . ')'];

        $dataBase = $this->pegaDataBase($p['tabela']);

        $campos_tabela = $s['tabelaConsulta'] != '' ? $this->campostabela($s['tabelaConsulta']) : $this->campostabela($p['tabela']);

        if (is_array($p['filtros'])) {
            foreach ($p['filtros'] as $key => $val) {
                $campo = strtolower($val['campo']);
                if (array_key_exists($campo, $campos_tabela)) {
                    $s["comparacao"][] = array($campos_tabela[$campo]['tipo'], $campo, $val['operador'], $val["valor"]);
                }
                if (isset($campos_tabela['disponivel'])) {
                    $s['comparacao'][] = array('varchar', 'disponivel', '!=', 'E');
                } elseif (isset($campos_tabela['arquivado'])) {
                    $s['comparacao'][] = array('varchar', 'arquivado', '!=', 'E');
                }
            }
        }

        //Por enquanto farei apenas dois niveis, relacionada e subrelacionada
        //Esta variavel vai definir se busco os dados relacionados e incluo no retorno
        $incluirRelacionados = false;

        if (isset($p['tabelasRelacionadas']) && is_array($p['tabelasRelacionadas'])) {
            foreach ($p['tabelasRelacionadas'] as $tabelaRelacionada => $dadosTabelaRelacionada) {
                if (isset($dadosTabelaRelacionada['incluirNaConsulta']) && $dadosTabelaRelacionada['incluirNaConsulta']) {
                    $incluirRelacionados = true;
                }

                if (!isset($dadosTabelaRelacionada['usarNaPesquisa']) || $dadosTabelaRelacionada['usarNaPesquisa'] == 'true') {

                    $camposTabelaRelacionada = $this->campostabela($tabelaRelacionada, $dataBase);
                    foreach ($p['filtros'] as $keyF => $filtro) {
                        if (array_key_exists(strtolower($filtro['campo']), $camposTabelaRelacionada)) {
                            $campoRelacionamento = isset($dadosTabelaRelacionada['campo_relacionamento']) ? $dadosTabelaRelacionada['campo_relacionamento'] :
                                $dadosTabelaRelacionada['campoRelacionamento'];
                            //Comentei a linha abaixo pois nao entendi o seu funcionamento em 09/10/2017
                            $s['comparacao'][] = array('in', $campoRelacionamento, $tabelaRelacionada, $filtro['campo'], $filtro['operador'], $filtro['valor']);
                        }
                    }
                }
            }
        }

        if (isset($campos_tabela['arquivado'])) {
            $s['comparacao'][] = array('varchar', 'arquivado', '!=', 'E');
        }
        if (isset($campos_tabela['disponivel'])) {
            $s['disponivel'][] = array('varchar', 'disponivel', '!=', 'E');
        }

        if (isset($configuracoesTabela['comparacao'])) {
            foreach ($configuracoesTabela['comparacao'] as $comparacao) {
                $s['comparacao'][] = $comparacao;
            }
        }

        //print_r($s);
        unset($s['tabelaConsulta']);
        $retorno = $this->retornosqldireto($s, 'montar', $s['tabela'], false, false);
        return isset($retorno[0][$p['campo_chave']]) ? $retorno[0][$p['campo_chave']] : 'erro';
        $this->desconecta($dataBase);
    }
}