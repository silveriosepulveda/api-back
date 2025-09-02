<?php

namespace ClasseGeral;


class Estruturas extends \ClasseGeral\ClasseGeral {

    /**
     * Busca a estrutura de uma tabela para geração de formulários, relatórios, etc.
     *
     * @param array $parametros Parâmetros para a busca da estrutura.
     * @param string $tipoRetorno (Opcional) Tipo de retorno desejado (json ou array).
     * @return mixed Estrutura da tabela em formato JSON ou array.
     */
    public function buscarEstrutura($parametros, $tipoRetorno = 'json'): mixed
    {
        $parametros = isset($parametros['parametros']) ? json_decode($parametros['parametros'], true) : $parametros;
        $classeEntrada = !is_array($parametros) ? $parametros : $parametros['classe'];

        $parametrosEnviados = isset($parametros['parametrosEnviados']) ? json_decode(base64_decode($parametros['parametrosEnviados']), true) : [];

        $retorno = [];
        $classe = $this->nomeClase($classeEntrada);

        $caminhoAPILocal = $_SESSION[session_id()]['caminhoApiLocal'];

        $arquivo = '';
        //Implementando a comparacao para configuracoesMenus que está em api-back e não em backLocal
        if ($classe == 'configuracaoMenus')
            $arquivo = $caminhoAPILocal . 'api/api-back/classes/' . $classe . '.class.php';
        else
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
                        'texto' => $valores['texto'] ?? '',
                        'padrao' => $valores['valor'] ?? '',
                        'tipo' => !isset($valores['texto']) ? 'oculto' : 'texto',
                    ];

                    if (isset($retorno['campos'][$campo])) {
                        $novoCampo['atributos_input']['ng-disabled'] = $retorno['campos'][$campo]['atributos_input']['ng-disabled'] ?? true;
                    } else {
                        $novoCampo['atributos_input']['ng-disabled'] = true;
                    }
                    $retorno['campos'][$campo] = isset($retorno['campos'][$campo]) ?
                        array_merge($retorno['campos'][$campo], $novoCampo) : $novoCampo;
                }
            }
        }

        $origemCampos = $parametros['origem'] ?? 'cadastro';

        $retorno['camposObrigatorios'] = $this->camposObrigatorios($retorno, $origemCampos);

        //Vendo se alguma Acao do Item tem comparacao com o usuario logado
        foreach ($retorno['acoesItensConsulta'] ?? [] as $nome => $val) {

        }

        $retorno = $this->validaEstrutura($retorno);

        return $tipoRetorno == 'array' ? $retorno : json_encode($retorno);
    }

    /**
     * Valida a estrutura JSON removendo campos que não estão autorizados no perfil do usuário
     * @param array $estrutura Estrutura JSON a ser validada
     * @return array Estrutura validada
     */
    protected function validaEstrutura(array $estrutura): array
    {
        $ms = new \ClasseGeral\ManipulaSessao();
        $usuario = $this->buscaUsuarioLogado();
        $adm = $usuario['administrador_sistema'] == 'S';

        // Se for administrador, não precisa validar
        if ($adm) {
            return $estrutura;
        }

        $menus = $ms->pegar('menu');
        $camposValidar = $menus['campos'][$estrutura['classe']] ?? [];

        // Se não há campos para validar, retorna a estrutura original
        if (empty($camposValidar)) {
            return $estrutura;
        }

        // Valida recursivamente a estrutura
        return $this->validaEstruturaRecursiva($estrutura, $camposValidar);
    }

    /**
     * Função recursiva para validar a estrutura
     * @param array $estrutura Estrutura a ser validada
     * @param array $camposValidar Array com campos autorizados
     * @return array Estrutura validada
     */
    private function validaEstruturaRecursiva($estrutura, $camposValidar)
    {
        // Concatena listaConsulta com campos correspondentes
        if (isset($estrutura['listaConsulta']) && is_array($estrutura['listaConsulta']) && 
            isset($estrutura['campos']) && is_array($estrutura['campos'])) {
            $estrutura['listaConsulta'] = $this->concatenaCampos($estrutura['listaConsulta'], $estrutura['campos']);
        }

        // Concatena repeticao[itens] com campos correspondentes
        if (isset($estrutura['repeticao']['itens']) && is_array($estrutura['repeticao']['itens']) && 
            isset($estrutura['campos']) && is_array($estrutura['campos'])) {
            $estrutura['repeticao']['itens'] = $this->concatenaCampos($estrutura['repeticao']['itens'], $estrutura['campos']);
        }

        // Valida campos principais
        if (isset($estrutura['campos']) && is_array($estrutura['campos'])) {
            $estrutura['campos'] = $this->validaCampos($estrutura['campos'], $camposValidar);
        }

        // Valida listaConsulta
        if (isset($estrutura['listaConsulta']) && is_array($estrutura['listaConsulta'])) {
            $estrutura['listaConsulta'] = $this->validaCampos($estrutura['listaConsulta'], $camposValidar);
        }

        // Valida repeticao[itens]
        if (isset($estrutura['repeticao']['itens']) && is_array($estrutura['repeticao']['itens'])) {
            $estrutura['repeticao']['itens'] = $this->validaCampos($estrutura['repeticao']['itens'], $camposValidar);
        }

        // Valida recursivamente blocos dentro de campos
        if (isset($estrutura['campos']) && is_array($estrutura['campos'])) {
            foreach ($estrutura['campos'] as $campo => $config) {
                if (isset($config['campos']) && is_array($config['campos'])) {
                    $estrutura['campos'][$campo] = $this->validaEstruturaRecursiva($config, $camposValidar);
                }
            }
        }

        return $estrutura;
    }

    /**
     * Concatena campos de listaConsulta/repeticao com campos correspondentes
     * @param array $camposOrigem Array de campos origem (listaConsulta ou repeticao[itens])
     * @param array $camposDestino Array de campos destino (campos)
     * @return array Campos concatenados
     */
    private function concatenaCampos($camposOrigem, $camposDestino)
    {
        $camposConcatenados = [];

        foreach ($camposOrigem as $campo => $config) {
            // Se existe o mesmo campo em campos, concatena as configurações
            if (isset($camposDestino[$campo])) {
                $camposConcatenados[$campo] = array_merge($camposDestino[$campo], $config);
            } else {
                // Se não existe, mantém apenas a configuração original
                $camposConcatenados[$campo] = $config;
            }
        }

        return $camposConcatenados;
    }

    /**
     * Valida um array de campos removendo os não autorizados
     * @param array $campos Array de campos a ser validado
     * @param array $camposValidar Array com campos autorizados
     * @return array Campos validados
     */
    private function validaCampos($campos, $camposValidar)
    {
        $camposValidados = [];

        foreach ($campos as $campo => $config) {
            // Verifica se o campo tem verificarPerfil = true
            $verificarPerfil = isset($config['verificarPerfil']) && $config['verificarPerfil'] === true;

            if ($verificarPerfil) {
                // Se tem verificarPerfil = true, verifica se está na lista de campos autorizados
                if (array_key_exists($campo, $camposValidar)) {
                    $camposValidados[$campo] = $config;
                }
                // Se não está autorizado, remove o campo (não adiciona ao array)
            } else {
                // Se não tem verificarPerfil ou é false, mantém o campo
                $camposValidados[$campo] = $config;
            }
        }

        return $camposValidados;
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
}
