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

    public function consulta($parametros, $tipoRetorno = 'json')
    {
        return $this->pegaConsultaDados()->consulta($parametros, $tipoRetorno);
    }

    public function selecionarTodosItensConsulta(array $parametros)
    {
        $con = new \ClasseGeral\ConsultaDados();
        return $con->selecionarTodosItensConsulta($parametros);
    }

    public function selecionarItemConsulta(array $parametros)
    {
        $con = new \ClasseGeral\ConsultaDados();
        return $con->selecionarItemConsulta($parametros);
    }

    public function excluir(array $parametros): bool|string
    {
        $con = new \ClasseGeral\ManipulaDados();
        return $con->excluir($parametros);
    }


    protected function validarPermissaoUsuario($classe, $acao): array|string
    {
        $usuario = $this->buscaUsuarioLogado();
        $temp = $_SESSION[session_id()];

        $adm = $usuario['administrador_sistema'] == 'S' || $usuario['administrador_sistema'] == 1;
        $ms = new \ClasseGeral\ManipulaSessao();
        $menus = $ms->pegar('menu');

        if ($adm || isset($menus['acoes'][$classe][$acao]))
            return ['sucesso' => 'Permissão Concedida'];
        else
            return ['aviso' => 'Usuário Sem Permissões'];
        //*/
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
     * Busca um registro para alteração com base nos parâmetros fornecidos.
     *
     * @param array $parametros Parâmetros da busca, incluindo tabela, chaves e campos desejados.
     * @param string $tipoRetorno Tipo de retorno desejado ('json' ou 'array').
     * Exemplo: [ 'tabela' => 'usuarios', 'campo_chave' => 'id', 'chave' => 123 ]
     * @return mixed Registro encontrado no formato desejado.
     */
    public function buscarParaAlterar(array $parametros, string $tipoRetorno = 'json'): mixed
    {
        $tbInfo = new \ClasseGeral\TabelasInfo();

        $p = isset($parametros['filtros']) ? json_decode($parametros['filtros'], true) : $parametros;

        $configTP = $tbInfo->buscaConfiguracoesTabela($p['tabela']);

        $classe = $configTP['classe'] ?? $this->nomeClase($p['tabela']);
        $nomeMenuPermissoes = $configTP['nomeMenuPermissoes'] ?? $classe;
        $permissao = $this->validarPermissaoUsuario($nomeMenuPermissoes, 'Alterar');

        if (isset($permissao['aviso']))
            return json_encode($permissao);


        $caminhoApiLocal = $this->pegaCaminhoApi(); // $_SESSION[session_id()]['caminhoApiLocal'];

        //Acrescentando a chave na variavel de camposddddddd
        $s['tabela'] = $p['tabela'];
        $s['tabelaConsulta'] = isset($p['tabelaConsulta']) && $p['tabelaConsulta'] != 'undefined' ? $p['tabelaConsulta'] : $p['tabela'];
        $s['comparacao'][] = array('int', $p['campo_chave'], '=', $p['chave']);

        $s['campos'] = isset($p['campos']) && $p['campos'] != '*' && count($p['campos']) > 0 ? array_merge([$p['campo_chave']], $p['campos']) : '*';

        if (isset($p['campoChaveSecundaria']) && isset($p['valorChaveSecundaria'])) {
            $s['comparacao'][] = ['varchar', $p['campoChaveSecundaria'], '=', $p['valorChaveSecundaria']];
        }

        if (isset($configTP['comparacao']))
            foreach ($configTP['comparacao'] as $comp)
                $s['comparacao'][] = $comp;

        $tempD = $this->retornosqldireto($s, 'montar', $s['tabelaConsulta'], false, false);

        $retorno = sizeof($tempD) == 1 ? $tempD[0] : array();

        //Buscando tabelas relacionadas
        if (isset($p['tabelasRelacionadas'])) {
            foreach ($p['tabelasRelacionadas'] as $keyTR => $valTR) {
                $camposTabelaRelacionada = $tbInfo->campostabela($keyTR);

                $campoRelacionamentoTR = $valTR['campo_relacionamento'] ?? $valTR['campoRelacionamento'];
                $r = array();
                $r['tabela'] = $keyTR;
                $r['comparacao'][] = array('int', $campoRelacionamentoTR, '=', $p['chave']);

                if (array_key_exists('disponivel', $camposTabelaRelacionada)) {
                    $r['comparacao'][] = array('varchar', 'disponivel', '=', 'S');
                }
                $r['ordem'] = $valTR['ordem'] ?? '';
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

                $configTabR = $tbInfo->buscaConfiguracoesTabela($keyTR, 'relacionada');
                if (isset($configTabR['comparacao'])) {
                    foreach ($configTabR['comparacao'] as $comparacao) {
                        $r['comparacao'][] = $comparacao;
                    }
                }

                $retornoR = $this->retornosqldireto($r, 'montar', $keyTR, false, false);

                if (isset($valTR['tabelasSubRelacionadas'])) {
                    foreach ($retornoR as $keyR => $valR) {
                        $sR = array();
                        foreach ($valTR['tabelasSubRelacionadas'] as $keyS => $valS) {

                            $camposTabelaSubRelacionada = $tbInfo->campostabela($keyS);
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
    public function buscarAnexos(array $parametros, string $tipoRetorno = 'json'): mixed
    {
        $cam = $this->pegaCaminhoApi();

        $classeGeralLocal = $cam . 'api/backLocal/classes/classeGeralLocal.class.php';
        if (is_file($classeGeralLocal)) {
            require_once $classeGeralLocal;
            $con = new \ClasseGeral\classeGeralLocal();
            if (method_exists($con, 'buscarAnexos'))
                return $con->buscarAnexos($parametros, $tipoRetorno);
        }

        $tabInfo = new \ClasseGeral\TabelasInfo();


        $p = $parametros;

        $tabela = strtolower($tabInfo->nometabela($p['tabela']));
        $tabelaConsulta = $p['tabela'];

        $chave = is_array($p['chave']) ? join(',', $p['chave']) : $p['chave'];

        $config = $tabInfo->buscaConfiguracoesTabela($tabelaConsulta);

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
                $campoChaveTabelaPrincipal = $tabInfo->campochavetabela($tabela);
                $campoChaveRelacionamento = $tabInfo->campochavetabela($tabelaRelacionada);

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
                    $arquivos[$key]['tipoAnexo'] = $val['tipoAnexo'] ?? 'Original';
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
                    //  echo 'nao tem';
                    //    $this->exclui('arquivos_anexos', 'chave_anexo', $val['chave_anexo']);
                }

            }
        }

        $temp = $arquivos;
        $arquivos = [];
        foreach ($temp as $val)
            $arquivos[] = $val;

        return $tipoRetorno == 'json' ? json_encode($arquivos) : $arquivos;
    }

    /**
     * Detalha um registro, incluindo dados de tabelas relacionadas e anexos.
     *
     * @param array $parametros Parâmetros da busca, incluindo tabela, chaves e tabelas relacionadas.
     * Exemplo: [ 'tabela' => 'usuarios', 'campo_chave' => 'id', 'chave' => 123 ]
     * @return string JSON com os dados detalhados do registro.
     */
    public function detalhar(array $parametros): string
    {
        $p = $parametros;

        $tbInfo = new \ClasseGeral\TabelasInfo();
        $formata = new \ClasseGeral\Formatacoes();
        //$config = $tbInfo->buscaConfiguracoesTabela($p['tabela']);

        //Acrescentando a chave na variavel de campos
        $s['tabela'] = $p['tabela'];

        $s['comparacao'][] = array('int', $p['campo_chave'], '=', $p['chave']);

        $tempD = $this->retornosqldireto($s, 'montar', $p['tabela']);

        $retorno = sizeof($tempD) == 1 ? $tempD[0] : array();

        //Buscando tabelas relacionadas
        if (isset($p['tabelasRelacionadas'])) {
            foreach ($p['tabelasRelacionadas'] as $keyTR => $valTR) {
                //print_r($valTR);
                $camposTabelaRelacionada = $tbInfo->campostabela($keyTR);
                $campoRelacionamentoTR = $valTR['campo_relacionamento'] ?? $valTR['campoRelacionamento'];
                $r = array();
                $r['tabela'] = $keyTR;

                $r['comparacao'][] = array('int', $campoRelacionamentoTR, '=', $p['chave']);
                if (array_key_exists('disponivel', $camposTabelaRelacionada)) {
                    $r['comparacao'][] = array('varchar', 'disponivel', '=', 'S');
                }
                $nomeArrayRelacionado = $valTR['raizModelo'] ?? strtolower($tbInfo->nometabela($keyTR));
                $r['ordem'] = $valTR['campo_valor'] ?? '';

                $configTr = $tbInfo->buscaConfiguracoesTabela($keyTR, 'relacionada');
                if (isset($configTr['comparacao']))
                    foreach ($configTr['comparacao'] as $comp)
                        $r['comparacao'][] = $comp;

                $retornoR = $this->retornosqldireto($r, 'montar', $keyTR, false, false);

                if (isset($valTR['tabelasSubRelacionadas'])) {
                    foreach ($retornoR as $keyR => $valR) {
                        $sR = array();

                        foreach ($valTR['tabelasSubRelacionadas'] as $keyS => $valS) {

                            $camposTabelaSubRelacionada = $tbInfo->campostabela($keyS);
                            $sR['tabela'] = $keyS;
                            $sR['comparacao'][] = array('int', $valTR['campo_relacionamento'], '=', $p['chave']);
                            $sR['comparacao'][] = array('int', $valS['campo_relacionamento'], '=', $valR[$valS['campo_relacionamento']]);
                            if (array_key_exists('disponivel', $camposTabelaSubRelacionada)) {
                                $sR['comparacao'][] = array('varchar', 'disponivel', '=', 'S');
                            }
                            $sR['ordem'] = $valS['campo_valor'] ?? '';
                            $retornoSr = $this->retornosqldireto($sR, 'montar', $keyS, false, false);
                            if (sizeof($retornoSr) > 0) {
                                $nomeArraySubRelacionado = $valS['raizModelo'] ?? strtolower($tbInfo->nometabela($keyS));
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
    public function manipula(array $parametros, array $arquivos = [])
    {
        $con = new \ClasseGeral\ManipulaDados();
        return $con->manipula($parametros, $arquivos);
    }


    public
    function anexarArquivos($parametros, $arquivosPost = [])
    {
        $p = $parametros;

        $caminho = $this->pegaCaminhoApi();// $_SESSION[session_id()]['caminhoApiLocal'];
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

            $destinoBase = 'arquivos_anexos/' . $tabela . '/' . $chave . '/';
            $destino = $caminho . 'arquivos_anexos/' . $tabela . '/' . $chave . '/';
            $destinom = $caminho . 'arquivos_anexos/' . $tabela . '/' . $chave . '/mini/';

            $up = $this->pegaUploadSimples();
            $func = $this->pegaManipulaStrings();
            $dir = $this->pegaGerenciaDiretorios();

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
                $classe = new ('\\' . $nomeClasse)();
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

    public
    function valorExiste($parametros)
    {
        $p = $parametros;
        //print_r($parametros);

        $campos_tabela = $this->pegaTabelasInfo()->campostabela($p['tabela']);
        $s['tabela'] = $p['tabela'];
        $s['campos'] = isset($p['retornar_completo']) && $p['retornar_completo'] ? array_keys($campos_tabela) : array($p['campo']);

        if (isset($p['campo_valor'])) {
            $s['campos'][] = $p['campo_valor'];
        }

        if (isset($p['chave']) && $p['chave'] > 0) {
            $s['comparacao'][] = array('int', $p['campo_chave'], '!=', $p['chave']);
        }
        $s['comparacao'][] = array('varchar', $p['campo'], '=', $p['valor']);

        if (isset($campos_tabela['disponivel'])) {
            $s['comparacao'][] = array('varchar', 'disponivel', '!=', 'E');
        } elseif (isset($campos_tabela['arquivado'])) {
            $s['comparacao'][] = array('varchar', 'arquivado', '!=', 'E');
        }

        return json_encode($this->retornosqldireto($s, 'montar', $p['tabela'], false, false));

    }
}