<?php

namespace ClasseGeral;

class ConsultaDados extends \ClasseGeral\ClasseGeral
{

    /**
     * Indica se deve mostrar o SQL da consulta.
     * @var bool
     */
    private bool $mostrarSQLConsulta = false;


    /**
     * Configuração padrão de paginação.
     * @var array
     */
    private array $paginacao = array(
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
     * Realiza uma consulta na tabela especificada nos parâmetros.
     *
     * @param array $parametros Parâmetros da consulta, incluindo tabela, campos, filtros, etc.
     * @param string $tipoRetorno Tipo de retorno desejado ('json' ou 'array').
     * Exemplo: [ 'tabela' => 'usuarios', 'campos' => ['id', 'nome'], ... ]
     * @return mixed Resultado da consulta no formato desejado.
     */
    public function consulta($parametros, $tipoRetorno = 'json'): string|array
    {
        if (!is_array($parametros)) {
            throw new \InvalidArgumentException('Esperado array em $parametros');
        }

        ini_set('memory_limit', '-1');
        $p = isset($parametros['parametros']) ? json_decode($parametros['parametros'], true) : $parametros;

        $p['itensPagina'] = 50;
        $limite = isset($p['limite']) && $p['limite'] > 0 ? $p['limite'] : 0;

        $tabela = $p['tabela'];
        $tabelaConsulta = $p['tabelaConsulta'] ?? $tabela;
        $infoTab = $this->pegaTabelasInfo();

        $configuracoesTabela = $infoTab->buscaConfiguracoesTabela($tabelaConsulta);
        $tabelaConsulta = $configuracoesTabela['tabelaConsulta'] ?? $tabelaConsulta;

        $p['campo_chave'] = $p['campo_chave'] ?? strtolower($infoTab->campochavetabela($p['tabela']));

        $temCampoDisponivelNoFiltro = false;

        $sessao = $this->pegaManipulaSessao();

        $valoresConsiderarDisponivel = array_merge(['S'], $configuracoesTabela['valoresConsiderarDisponivel'] ?? []);

        $s['tabela'] = $tabela;
        $s['tabelaConsulta'] = $tabelaConsulta;
        $s['dataBase'] = $configuracoesTabela['dataBase'] ?? '';

        //Acrescentando o campo chave
        $tirarCampoChaveConsulta = $p['tirarCampoChaveConsulta'] ?? false;

        if (isset($p['campos']) && sizeof($p['campos']) > 0 && !$tirarCampoChaveConsulta) {
            $p['campos'][] = $p['campo_chave'];
        } else if (!isset($p['campos'])) {
            $p['campos'] = '*';
        }

        $s['campos'] = $p['campos'];
        if ($s['campos'] != '*') {
            $s['campos'][] = $p['campo_chave'];
        }

        $campos_tabela = $infoTab->campostabela($tabelaConsulta);

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
                    $camposTabelaRelacionada = $infoTab->campostabela($tabelaRelacionada);

                    $camposIgnorarFiltro = $dadosTabelaRelacionada['camposIgnorarFiltro'] ?? [];

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

        $s['ordem'] = $p['ordemFiltro'] ?? '';

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
                        $sqlTabRel .= isset($infoTab->campostabela($tabelaRelacionada)['disponivel']) ? " and disponivel = 'S' " : '';

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

        $classeTabela = $configuracoesTabela['classe'] ?? $this->nomeClase($tabela);

        $classeAposFiltrar = $this->criaClasseTabela($classeTabela);
        $funcaoAposFiltrar = isset($parametros['acaoAposFiltrar']) && $parametros['acaoAposFiltrar'] != 'undefined' ? $parametros['acaoAposFiltrar'] : 'aposFiltrar';
        $temFuncaoAposFiltrar = $this->criaFuncaoClasse($classeAposFiltrar, $funcaoAposFiltrar);

        if ($temFuncaoAposFiltrar) {
            $retorno = $classeAposFiltrar->$funcaoAposFiltrar($retorno);
        }

        $tela = $p['tela'] ?? $p['tabela'];

        $retornoSessao['parametrosConsulta'] = $parametros;
        $retornoSessao['filtros'] = $p['filtros'] ?? array();
        $retornoSessao['ordem'] = $p['ordemFiltro'] ?? '';
        $retornoSessao['paginacao'] = $retorno['paginacao'];
        $retornoSessao['lista'] = $retorno['lista'];
        $retornoSessao['parametrosSQL'] = $s;

        $sessao->setar('consultas,' . $tela, $retornoSessao);

        $this->desconecta($s['dataBase']);

        return $tipoRetorno == 'json' ? json_encode($retorno) : $retorno;
    }

    private function resumoConsulta($parametros, $campos_tabela = array())
    {
        $formata = $this->pegaFormatacoes();

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
                    $sql .= ' AND ' . $campo . ' ' . $val['operador'] . $formata->retornavalorparasql($campos_tabela[$campo]['tipo'], $val["valor"]);
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

        return $this->retornosqldireto(strtolower($sql), '', $p['tabela'])[0];
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
    public function retornosqldireto(string|array $sql, $acao = '', $tabela = '', $dataBase = '', $mostrarsql = false, $formatar = true): array
    {
        $retorno = [];
        $tabInfo = $this->pegaTabelasInfo();// new \ClasseGeral\TabelasInfo();
        $formata = $this->pegaFormatacoes(); // new \ClasseGeral\Formatacoes();

        $dataBase = $this->pegaDataBase($tabela, $dataBase);

        $campos = $tabela != '' ? array_change_key_case($tabInfo->campostabela($tabela), CASE_LOWER) : '';

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
                        $valor = $formatar ? $formata->formatavalorexibir($valor, $tipo, false) : $valor;
                        $retorno[$key][$campo] = $valor;
                    }
                }
            }
        }

        $this->desconecta($dataBase);
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
    public function selecionarItemConsulta(array $parametros): string
    {
        $tela = $parametros['tela'];
        $selecionado = $parametros['selecionado'];

        // Percorre a lista de itens da consulta e marca/desmarca conforme o parâmetro
        foreach ($_SESSION[session_id()]['consultas'][$tela]['lista'] as $key => $item) {
            if ($item[$parametros['campo_chave']] == $parametros['chave']) {
                if ($selecionado == 'false') {
                    $_SESSION[session_id()]['consultas'][$tela]['parametrosConsulta']['todosItensSelecionados'] = $selecionado;
                }
                $_SESSION[session_id()]['consultas'][$tela]['lista'][$key]['selecionado'] = $selecionado;
            }
        }
        return json_encode(['sucesso' => 'Sucesso']);
    }

    /**
     * Seleciona ou desseleciona todos os itens de uma consulta.
     *
     * @param array $parametros Parâmetros contendo 'tela' e 'selecionado'.
     * Exemplo: [ 'tela' => 'usuarios', 'selecionado' => 'true' ]
     * @return string JSON de sucesso.
     */
    public function selecionarTodosItensConsulta(array $parametros): string
    {
        if (!is_array($parametros)) {
            throw new \InvalidArgumentException('Esperado array em $parametros');
        }
        $tela = $parametros['tela'];
        $selecionado = $parametros['selecionado'];

        $sessao = $this->pegaManipulaSessao();
        $variavelSessao = 'consultas,' . $tela;
        $lista = $sessao->pegar($variavelSessao);

        $lista['parametrosConsulta']['todosItensSelecionados'] = $selecionado;

        foreach ($lista['lista'] as $key => $item) {
            $lista['lista'][$key]['selecionado'] = $selecionado;
        }

        $sessao->setar($variavelSessao, $lista);
        return json_encode(['sucesso' => 'sucesso']);
    }
}
