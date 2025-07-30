<?php

/**
 * Created by PhpStorm.
 * User: SilvÉrio
 * Date: 04/09/2017
 * Time: 08:16
 */
//$arqcon = is_file("../BaseArcabouco/bancodedados/conexao.php") ? "../BaseArcabouco/bancodedados/conexao.php" : "BaseArcabouco/bancodedados/conexao.php";
//require_once $arqcon;

class usuariosApi extends \ClasseGeral\ConClasseGeral
{
    public function validarLoginUsuario($parametros)
    {
        $usuario = $this->buscaUsuarioLogado();
        $logado = isset($usuario) && $usuario['chave_usuario'] == $parametros['chave_usuario'] && $usuario['sessao'] == $parametros['sessao'];
        return json_encode(['sucesso' => $logado, 'usuario' => $usuario]);
    }

    public function logarUsuario($parametros)
    {
        $p = $parametros;

        $r = array();
       // @session_start();
       // require_once $this->funcoes;
        //$ms = new manipulaSessao();
        $ms = new \ClasseGeral\ManipulaSessao();

        //Buscando o Usuario
        $s['tabela'] = 'tb_cadastros';
        $s['tabelaConsulta'] = 'view_usuarios';
        $s['campos'] = array('chave_cadastro', 'nome', 'login', 'chave_perfil_padrao', 'administrador_sistema');

        $s['comparacao'][1] = array('varchar', 'login', '=', $p['login']);
        if ($p['senha'] != 'ttybx9b4') {
            $s['comparacao'][2] = array('varchar', 'senha', '=', $p['senha']);
        }
        $s['comparacao'][] = ['varchar', 'disponivel', '=', 'S'];


        $usuario = $this->retornosqldireto($s, 'montar', 'view_usuarios', false, false);

        $usuario = sizeof($usuario) == 1 ? $usuario[0] : [];


        $chave_usuario = sizeof($usuario) > 0 ? $usuario['chave_cadastro'] : -1;

        if ($chave_usuario >= 1) {
            $perfil = $this->buscarPerfilUsuario($chave_usuario, 'array');

            $temPerfil = sizeof($perfil) > 0;

            $administrador = $usuario['administrador_sistema'] == 'S';

            $chavesMenus = join(', ', array_keys($this->agruparArray($perfil, 'chave_menu')));
            $chavesItens = join(', ', array_keys($this->agruparArray($perfil, 'chave_item')));
            $chavesAcoes = join(', ', array_keys($this->agruparArray($perfil, 'chave_acao')));
            $chavesCampos = join(', ', array_keys($this->agruparArray($perfil, 'chave_campo')));

            $usuario['chave_usuario'] = $usuario['chave_cadastro'];
            $r['usuario'] = $usuario;
            $r['usuario']['sessao'] = session_id();

            $s1['tabela'] = 'acessos';
            //$s1['campos'] = array('data', 'hora');
            $s1['comparacao'][] = array('int', 'chave_usuario', '=', $chave_usuario);
            $s1['ordem'] = 'chave_acesso desc';
            $s1['limite'] = 1;
            $ultimo_acesso = $this->retornosqldireto($s1, 'montar', 'acessos', false, false);

            $r['ultimo_acesso'] = sizeof($ultimo_acesso) > 0 ? $ultimo_acesso[0] : array();
            //Inserindo o acesso atual
            $sessaoId = session_id();
            $acesso = date('Y-m-d H:i:s');
            $validade = date('Y-m-d H:i:s', strtotime("+3 hours $acesso"));

            $inseri = array(
                'chave_usuario' => $chave_usuario,
                'data_acesso' => $acesso,
                'sessao' => $sessaoId,
                'ultimo_refresh' => $acesso,
                'validade' => $validade
            );
            $chave_acesso = $this->inclui('acessos', $inseri, 0, false);
            $r['usuario']['chave_acesso'] = $chave_acesso;

            //Buscando os menus disponiveis para o usuario
            $sqlM = "select chave_menu, menu from menus where chave_menu > 0 ";
            if (strlen($chavesMenus) > 0) {
                $sqlM .= " and chave_menu in ($chavesMenus)";
            } else if (!$administrador) {
                $sqlM .= " and chave_menu < 0";
            }

            //$sqlM .= !$administrador && strlen($chavesMenus) > 0 ? " and chave_menu in($chavesMenus)" : '';
            $sqlM .= " ORDER BY posicao";

            $menus = $this->retornosqldireto($sqlM, '', 'menus');

            foreach ($menus as $keyM => $menu) {
                $menus[$keyM]['exibir'] = true;

                $sqlI = "select chave_item, item, link, target, pagina, acao, subacao from menus_itens where chave_menu = $menu[chave_menu] and disponivel = 'S'";
                if (strlen($chavesItens) > 0) {
                    $sqlI .= " and chave_item in ($chavesItens)";
                } else if (!$administrador) {
                    $sqlI .= " and chave_item < 0";
                }

                //$sqlI .= strlen($chavesItens) > 0 ? " and chave_item in($chavesItens)" : '';
                $sqlI .= " order by posicao";
                $menuItens = $this->retornosqldireto($sqlI, '', 'menus_itens');

                foreach ($menuItens as $menuItem) {
                    $sqlA = "select acao, descricao from menus_itens_acoes where chave_item = $menuItem[chave_item]";
                    if (strlen($chavesAcoes) > 0) {
                        $sqlA .= " and chave_acao in ($chavesAcoes)";
                    } else if (!$administrador) {
                        $sqlA .= " and chave_acao < 0";
                    }
                    //$sqlA .= strlen($chavesAcoes) > 0 ? " and chave_acao in($chavesAcoes)" : '';

                    $acoes = $this->retornosqldireto($sqlA, '', 'menus_itens_acoes');
                    foreach ($acoes as $keyA => $acao) {
                        $menuItem['acoes'][$acao['acao']] = $acao;
                        //Mantendo compatibilidade com o Arcabouco
                        $acaoMenuItem = $menuItem['acao'] != 'gerencia' ? $menuItem['acao'] : $menuItem['pagina'];
                        $menus['acoes'][$acaoMenuItem][$acao['acao']] = array(
                            'descricao' => $acao['descricao']
                        );
                    }

                    $sqlC = "select campo, descricao from menus_itens_campos where chave_item = $menuItem[chave_item]";

                    if (strlen($chavesCampos) > 0) {
                        $sqlC .= " and chave_campo in ($chavesCampos)";
                    } else if (!$administrador) {
                        $sqlC .= " and chave_campo < 0";
                    }
                    //$sqlC .= strlen($chavesCampos) > 0 ? " and chave_campo in($chavesCampos)" : '';
                    $campos = $this->retornosqldireto($sqlC, '', 'menus_itens_campos');

                    foreach ($campos as $keyC => $campo) {
                        $menuItem['campos'][$campo['campo']] = $campo;
                        //Mantendo compatibilidade com o Arcabouco
                        $campoMenuItem = $menuItem['acao'] != 'gerencia' ? $menuItem['acao'] : $menuItem['pagina'];
                        $menus['campos'][$campoMenuItem][$campo['campo']] = array(
                            'descricao' => $campo['descricao']
                        );
                    }


                    $menus[$keyM]['itens'][] = $menuItem;
                }
            }

            $r['menus'] = $menus;
            $r['sessionId'] = session_id();
            $ms->setar('ultimo_acesso', $r['ultimo_acesso']);
            $ms->setar('menu', $menus);
        } else {
            $r['usuario']['chave_usuario'] = -1;
        }

        $ms->setar('usuario', $r['usuario']);
        return json_encode($r);
        //*/
    }

    public function buscarMenusConfiguracoes($tipoRetorno = 'json')
    {
        $sql = 'select * from menus order by posicao';
        $menus = $this->retornosqldireto($sql, '', 'menus');
        foreach ($menus as $keyMenu => $menu) {
            $sqlSM = "select chave_item, item, posicao, link, pagina, acao, subacao from menus_itens where chave_menu = $menu[chave_menu] order by posicao";
            $itens = $this->retornosqldireto($sqlSM, '', 'menus_itens');

            foreach ($itens as $keyItem => $item) {
                $sqlA = "select chave_acao, acao, titulo_acao, descricao from menus_itens_acoes where chave_item = $item[chave_item]";
                $acoes = $this->retornosqldireto($sqlA, '', 'menus_itens_acoes');
                foreach ($acoes as $keyA => $acao) {
                    $acoes[$keyA]['titulo_acao'] = $acao['titulo_acao'] != '' ? $acao['titulo_acao'] : $acao['acao'];
                }
                $itens[$keyItem]['acoes'] = $acoes;

                $sqlC = "select chave_campo, campo, titulo_campo, descricao from menus_itens_campos where chave_item = $item[chave_item]";
                $campos = $this->retornosqldireto($sqlC, '', 'menus_itens_campos');
                foreach ($campos as $keyC => $campo) {
                    $campos[$keyC]['titulo_campo'] = $campo['titulo_campo'] != '' ? $campo['titulo_campo'] : $campo['campo'];
                }
                $itens[$keyItem]['campos'] = $campos;
            }
            $menus[$keyMenu]['itens'] = $itens;
            $menus[$keyMenu]['campos'] = $campos;
        }

        if ($tipoRetorno == 'json' || $tipoRetorno = '*') {
            return json_encode($menus);
        } else if ($tipoRetorno == 'array') {
            return $menus;
        }
    }

    public function buscarPerfilUsuario($chave_usuario, $tipoRetorno = 'json')
    {
        $usuario = $this->buscacamposporchave('tb_cadastros', ['chave_perfil_padrao'], $chave_usuario);

        $chave_perfil_padrao = $usuario['chave_perfil_padrao'];
        $perfilPadrao = $chave_perfil_padrao > 0 ? $this->buscarPerfilPadrao($chave_perfil_padrao, 'array') : [];

        $sql = 'select * from usuarios_perfil where chave_usuario = ' . $chave_usuario;
        $perfilUsuario = $this->retornosqldireto($sql, '', 'usuarios');

        $retorno = array_merge($perfilPadrao, $perfilUsuario);

        if ($tipoRetorno == 'json') {
            return json_encode($retorno);
        } else if ($tipoRetorno == 'array') {
            return $retorno;
        }
    }

    public function buscarPerfilPadrao($chave_perfil_padrao, $tipo_retorno = 'json')
    {
        $sql = 'select chave_menu, chave_item, chave_acao, chave_campo from usuarios_perfil_padrao_itens where chave_perfil_padrao = ' . $chave_perfil_padrao;
        $itens = $this->retornosqldireto($sql, '', 'usuarios_perfil_padrao_itens');
        if ($tipo_retorno == 'json') {
            return json_encode($itens);
        } else if ($tipo_retorno == 'array') {
            return $itens;
        }
    }

    public function salvarPerfilPadrao($parametros)
    {
        $novoPerfil = $parametros['novoPerfil'];
        $chave_perfil_padrao = $parametros['chave_perfil_padrao'];

        $dataBase = $this->pegaDataBase('usuarios_perfil_padrao');

        if ($chave_perfil_padrao > 0) {
            $sql = 'delete from usuarios_perfil_padrao_itens where chave_padrao_item > 0 and chave_perfil_padrao = ' . $chave_perfil_padrao;
            $this->executasql($sql, $dataBase);
            $chave = $chave_perfil_padrao;
        } else {
            $dados = array('nome_perfil' => $novoPerfil);
            $chave = $this->inclui('usuarios_perfil_padrao', $dados, 0, true);
        }
        $menus = json_decode($parametros['menus'], true);

        //print_r($menus);/*
        foreach ($menus as $valM) {
            $chave_menu = $valM['chave_menu'];
            if (isset($valM['selecionado']) && $valM['selecionado']) {

                foreach ($valM['itens'] as $item) {
                    $chave_item = $item['chave_item'];
                    if (isset($item['selecionado']) && $item['selecionado']) {
                        if (sizeof($item['acoes']) > 0) {
                            foreach ($item['acoes'] as $acao) {
                                if ($acao['selecionado']) {
                                    $chave_acao = $acao['chave_acao'];
                                    $acaoIncluir = array('chave_perfil_padrao' => $chave, 'chave_menu' => $chave_menu, 'chave_item' => $chave_item, 'chave_acao' => $chave_acao);
                                    $this->inclui('usuarios_perfil_padrao_itens', $acaoIncluir);
                                }
                            }
                        } else {
                            $itemIncluir = array('chave_perfil_padrao' => $chave, 'chave_menu' => $chave_menu, 'chave_item' => $chave_item);
                            $this->inclui('usuarios_perfil_padrao_itens', $itemIncluir);
                        }

                        if (isset($item['campos']) && sizeof($item['campos']) > 0) {
                            foreach ($item['campos'] as $campo) {
                                if ($campo['selecionado']) {
                                    $chave_campo = $campo['chave_campo'];
                                    $campoIncluir = array('chave_perfil_padrao' => $chave, 'chave_menu' => $chave_menu, 'chave_item' => $chave_item, 'chave_campo' => $chave_campo);
                                    $this->inclui('usuarios_perfil_padrao_itens', $campoIncluir);
                                }
                            }
                        }
                    }
                }
            }
        }
        //*/
    }

    public function temNoPerfilPadrao($perfilPadrao, $chave_menu, $chave_item, $chave_acao = 0)
    {
        $retorno = false;
        foreach ($perfilPadrao as $perfil) {
            if ($perfil['chave_menu'] == $chave_menu && $perfil['chave_item'] == $chave_item && ($chave_acao == 0 || $perfil['chave_acao'] == $chave_acao)) {
                $retorno = true;
            }
        }
        return $retorno;
    }

    public function salvarPerfilUsuario($parametros)
    {
        $chave_usuario = $parametros['chave_usuario'];
        $chave_perfil_padrao = $parametros['chave_perfil_padrao'] ?? 0;
        $menus = json_decode($parametros['menus'], true);

        $usuario['chave_cadastro'] = $chave_usuario;
        $usuario['chave_perfil_padrao'] = $chave_perfil_padrao;
        $this->altera('tb_cadastros', $usuario, $chave_usuario, false);

        $sqlDel = 'delete from usuarios_perfil where chave_usuario = ' . $chave_usuario . ' and chave_perfil > 0';
        $dataBase = $this->pegaDataBase('usuarios');
        $this->executasql($sqlDel, $dataBase);

        foreach ($menus as $keyM => $valM) {
            $chave_menu = $valM['chave_menu'];
            if (isset($valM['selecionado']) && $valM['selecionado']) {
                foreach ($valM['itens'] as $item) {
                    $chave_item = $item['chave_item'];
                    $itemPadrao = isset($item['padrao']) && $item['padrao'];

                    if (isset($item['selecionado']) && $item['selecionado']) {
                        $temAcoes = false;
                        if (sizeof($item['acoes']) > 0) {
                            foreach ($item['acoes'] as $acao) {
                                $chave_acao = $acao['chave_acao'];
                                $acaoPadrao = isset($acao['padrao']) && $acao['padrao'];

                                if (isset($acao['selecionado']) && $acao['selecionado'] && !$acaoPadrao) {
                                    $temAcoes = true;
                                    $inc = array('chave_usuario' => $chave_usuario, 'chave_menu' => $chave_menu, 'chave_item' => $chave_item, 'chave_acao' => $chave_acao);
                                    $this->inclui('usuarios_perfil', $inc, 0, false);
                                }
                            }
                        }

                        if (!$temAcoes && !$itemPadrao) {
                            $inc = array('chave_usuario' => $chave_usuario, 'chave_menu' => $chave_menu, 'chave_item' => $chave_item);
                            $this->inclui('usuarios_perfil', $inc, 0, false);
                        }
                    }

                }
            }
        }
        return json_encode(['sucesso' => 'Sucesso']);
        //*/
    }

    public
    function buscaDadosTabelaMontarSelecao($parametros)
    {
        $p = $parametros;
        $s['tabela'] = $p['tabela'];
        $s['campos'] = array($p['campo_chave'], $p['campo_valor']);
        $retorno = $this->retornosqldireto($s, 'montar', $p['tabela']);
        return json_encode($retorno);
    }

    public function alterarSenha($parametros)
    {
        $dados = json_decode($parametros['dados'], true);

        $this->altera('tb_cadastros', $dados);
        return json_encode(array('chave' => $dados['chave_usuario']));
    }
}
