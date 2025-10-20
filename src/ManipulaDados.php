<?php

namespace ClasseGeral;

class ManipulaDados extends \ClasseGeral\ClasseGeral
{
    public function manipula(array $parametros, array $arquivos = [])
    {
        $tabInfo = new \ClasseGeral\TabelasInfo();

        $chave = 0;
        $p = $parametros;

        $caminhoApiLocal = $this->pegaCaminhoApi();// $_SESSION[session_id()]['caminhoApiLocal'];

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
        $confTabela = $tabInfo->buscaConfiguracoesTabela($tabelaOriginal);
        $tabelaOriginal = $confTabela['tabelaOrigem'] ?? $tabelaOriginal;

        $tabela = $tabInfo->nometabela($conf['tabela']);

        //Esta variavel entrou para poder usar uma classe diferente do nome da tabela
        $classeTabela = isset($conf['classe']) ? $conf['classe'] : $tabela;

        $parametrosBuscaEstrutura = isset($conf['funcaoEstrutura']) && $conf['funcaoEstrutura'] != 'undefined' ?
            ['classe' => $classeTabela, 'funcaoEstrutura' => $conf['funcaoEstrutura']] : $classeTabela;

        //confLocal = estrutura
        $confLocal = $this->buscarEstrutura($parametrosBuscaEstrutura, 'array');

        $anexoObrigatorio = isset($confLocal['anexos']['obrigatorio']);
        $temArquivos = is_array($a) && sizeof($a) > 0;

        if ($anexoObrigatorio && !$temArquivos) {
            return json_encode(['erro' => 'Não Há Anexos']);
        }

        foreach ($confLocal['camposIgnorarEdicao'] ?? [] as $campo)
            if (isset($dados[$campo]))
                unset($dados[$campo]);

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

        $campoChave = $tabInfo->campochavetabela($tabelaOriginal, $conf);
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
            $arqClasse = $caminhoApiLocal . 'api/backLocal/classes/' . $conf['classe'] . '.class.php';
            if (file_exists($arqClasse)) {
                require_once $arqClasse;

                $classeManipula = new $conf['classe']();// new ('//' . $conf['classe'])();

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
            return json_encode(['erro' => 'Erro ao Incluir']);
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
                                ? $arq['nomeAnexo'] . '.' . $ext : strtolower($tabInfo->nometabela($tabela)) . '_' . $arq['campo'] . '_' . $chave . '.' . $ext;

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

                    $variavelTabRel = '';
                    if (isset($dados[$tabelaR]))
                        $variavelTabRel = $tabelaR;
                    else
                        $variavelTabRel = $infoTabelaR['raizModelo'] ?? 'raiz';

                    //Pegando o campo chave da tabela
                    $campoChaveTabRel = $tabInfo->campochavetabela($tabelaR, $infoTabelaR);
                    $campoRelacionamentoTabRel = $infoTabelaR['campo_relacionamento'] ?? $infoTabelaR['campoRelacionamento'];

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
                                    $campoChaveTabSubRel = $tabInfo->campochavetabela($tabelaSR, $infoTabelaSR);

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
                                        $campoChaveTabSubRel = $tabInfo->campochavetabela($tabelaSR, $infoTabelaSR);

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
    function verificaRelacionamentos($parametros): void
    {
        $tabInfo = new \ClasseGeral\TabelasInfo();

        $dados = is_array($parametros['dados']) ? $parametros['dados'] : json_decode($parametros['dados'], true);
        $confi = is_array($parametros['configuracoes']) ? $parametros['configuracoes'] : json_decode($parametros['configuracoes'], true);

        foreach ($confi['relacionamentosVerificar'] as $relacionamento) {

            $campoChave = $tabInfo->campochavetabela($relacionamento['tabelaRelacionamento']);

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
    public function inclui(string $tabela, array $dados, null|int $chave_primaria = 0, bool $mostrarsql = false, bool $inserirLog = true, bool $formatar = true): mixed
    {
        $tabInfo = new \ClasseGeral\TabelasInfo();
        $formata = new \ClasseGeral\Formatacoes();

        $nova_chave = 0;

        $tabelasIgnorarChavUsuario = ['acessos', 'usuarios_perfil', 'usuarios_empresas', 'usuarios_empresas_grupos', 'usuarios', 'eventos_sistema'];

        // Fiz esta rotina pois o key do array pode estar em maiúsculo
        $dados = array_change_key_case($dados, CASE_LOWER);

        $tabelaOriginal = $tabela;
        $confTabela = $tabInfo->buscaConfiguracoesTabela($tabela);
        $tabela = isset($confTabela['tabelaOrigem']) ? $tabInfo->nometabela($confTabela['tabelaOrigem']) : $tabInfo->nometabela($tabela);
        $dataBase = $this->pegaDataBase($tabelaOriginal);

        //Pegando os campos da tabela
        $campos = $tabInfo->campostabela($tabela);

        //Pegando o campo chave
        $campo_chave = $tabInfo->campochavetabela($tabelaOriginal, $dataBase);

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

                    $valor = $formatar ? $formata->retornavalorparasql($tipo, $valor, 'inclusao') : $valor;
                }

                if ($einteiro && $ezero) {
                    //Vendo se é inteiro e se é chave_estrangeira se for e o valor for 0 converto-o em null
                    $qtd = $this->echaveestrangeira($tabela, $campoT);

                    if ($qtd > 0) {
                        $valor = 'null';
                    }
                } else if (($tipo == 'varchar' or $tipo == 'longtext') && $valor == "''") {
                    $valor = 'null';
                } else if ($tipo == 'date' && $valor != 'null' && !str_starts_with($valor, "'")) {
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
        }
        return $nova_chave;
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

    /**
     * Valida campos obrigatórios de acordo com a configuração informada.
     *
     * @param array $configuracao Configuração dos campos obrigatórios.
     * @param array $dados Dados a serem validados.
     * @param array $retorno (Opcional) Array de retorno para campos inválidos.
     * @return array Lista de campos obrigatórios não preenchidos.
     */
    public function validarCamposObrigatorios(array $configuracao, array $dados, array $retorno = []): array
    {
        if (!is_array($configuracao['camposObrigatorios']))
            return [];

        $camposIgnorar = [];
        $camposObrigatorios = $configuracao['camposObrigatorios'];

        $compara = new \ClasseGeral\ManipulaValores();

        if (isset($camposObrigatorios['ignorarObrigatorio'])) {
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

                            $tipoIgnorar = $val['tipoIgnorar'] ?? 'e';

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

                foreach ($dados[$campo] ?? [] as $keyBloco => $dadosValidarBloco) {
                    foreach ($dadosValidarBloco as $campoValidarBloco => $valorValidar) {
                        //Vendo se o campo e obrigatorio
                        if (isset($camposObrigatorios[$campo][$campoValidarBloco])) {
                            //Caso o campo seja obribatorio valido ele
                            if (!$this->validarCampo($valorValidar)) {
                                $ignorarCampo = $camposIgnorar[$campoValidarBloco] ?? [];

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

                                        $tipoIgnorar = $val['tipoIgnorar'] ?? 'e';

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
    public function validarCampo(string $valor): bool
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
    public function buscarDuplicidadeCadastro(array $configuracoes, array $dados): bool
    {
        $tabInfo = new \ClasseGeral\TabelasInfo();
        $formata = new \ClasseGeral\Formatacoes();

        $camposSeparados = $configuracoes['camposNaoDuplicar'] ?? [];
        $camposJuntos = $configuracoes['camposNaoDuplicarJuntos'] ?? [];

        $tabela = $configuracoes['tabela'];
        $camposTabela = $tabInfo->campostabela($tabela);

        $campoChave = $configuracoes['campoChave'] ?? $tabInfo->campochavetabela($tabela);
        $chave = isset($dados[$campoChave]) && $dados[$campoChave] > 0 ? $dados[$campoChave] : 0;
        $retorno = false;

        //Verificando Campos Separados
        foreach ($camposSeparados as $campo) {

            if (!is_array($campo) && isset($dados[$campo])) {
                $sqlS = "select $campoChave from $tabela where $campoChave <> $chave";
                $valor = $formata->retornavalorparasql($camposTabela[$campo]['tipo'], $dados[$campo]);
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
                        $chaveRelacionada = $valorCampo[$campo['campoChave']] ?? 0;
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
                    $valor = $formata->retornavalorparasql($camposTabela[$campo]['tipo'], $dados[$campo]);
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
     * Atualiza um registro no banco de dados.
     *
     * @param string $tabela Nome da tabela onde o registro será atualizado.
     * @param array $dados Dados a serem atualizados.
     * @param null|int $chave (Opcional) Chave do registro a ser atualizado.
     * @param bool $mostrarsql (Opcional) Se deve ou não mostrar a query SQL executada.
     * @param bool $inserirLog (Opcional) Se deve ou não inserir um log da operação.
     * @return mixed Retorna a chave do registro atualizado ou 0 em caso de falha.
     */
    public function altera(string $tabela, array $dados, null|int $chave = 0, bool $mostrarsql = false, bool $inserirLog = true): mixed
    {
        $tabInfo = new \ClasseGeral\TabelasInfo();
        $formata = new \ClasseGeral\Formatacoes();

        //Pegando os campos da tabela
        $tabelaOriginal = $tabela;
        $tabela = $tabInfo->nometabela($tabela);
        $dataBase = $this->pegaDataBase($tabelaOriginal);
        $campos = $tabInfo->campostabela($tabela);

        //Pegando o campo chave
        $campo_chave = $tabInfo->campochavetabela($tabelaOriginal);
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
                            $valor = $formata->retornavalorparasql($tipo, $valor, 'alteracao');
                        }
                        $sql .= ", $campoT = $valor";
                    }
                } else if ($tipo == 'json') {
                    $valor = $formata->retornavalorparasql($tipo, $valor, 'alteracao');
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
     * Exclui um registro, realizando exclusão lógica ou física, dependendo da configuração.
     *
     * @param array $parametros Parâmetros para exclusão.
     * Exemplo: [ 'tabela' => 'usuarios', 'campo_chave' => 'id', 'chave' => 123, 'aoExcluir' => 'A' ]

     * @return false|string
     */
    public function excluir(array|string $parametros): bool|string
    {
        $tbInfo = new \ClasseGeral\TabelasInfo();


        $p = $parametros;
        $configTb = $tbInfo->buscaConfiguracoesTabela($p['tabela']);

        $refazerConsulta = $parametros['refazerConsulta'] ?? false;

        $nomeMenuPermissoes = $configTb['nomeMenuPermissoes'] ?? $this->nomeClase($p['tabela']);

        $permissao = $this->validarPermissaoUsuario($nomeMenuPermissoes, 'Excluir');
        if (isset($permissao['aviso']))
            return json_encode($permissao);

        $campos_tabela = $tbInfo->campostabela($p['tabela']);
        $campoChave = $p['campo_chave'] ?? $tbInfo->campochavetabela($p['tabela']);

        //Variavel que define se sera excluido ou atualizado para arquivado = 'E'
        $tabelaOriginal = strtolower($p['tabela']);
        $nomeTabela = $tbInfo->nometabela($p['tabela']);

        $aoExcluir = $p['aoExcluir'] ?? 'A';

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
            $nova_chave = $this->excluiDefinitivo($p['tabela'], $campoChave, $p['chave'], 'nenhuma', false);
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

        $novaConsulta = [];
        if ($refazerConsulta) {
            if ($nova_chave > 0) {
                $consulta = new \ClasseGeral\ConsultaDados();
                $novaConsulta = $consulta->consulta($_SESSION[session_id()]['consultas'][$nomeMenuPermissoes]['parametrosConsulta'], 'array');
            }
        }

        return json_encode([
            'chave' => $nova_chave,
            'novaConsulta' => $novaConsulta
        ]);
    //*/
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
    private function excluiDefinitivo(string $tabela, string $campo_chave, int $chave, string $tabela_relacionada = 'nenhuma', bool $exibirsql = false): mixed
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
    }
}
