<?php

namespace ClasseGeral;


class EstruturaValidacao extends \ClasseGeral\ClasseGeral {

    protected function validaEstruturaTeste($estrutura)
    {
        $classe = $estrutura['classe'];
        $ms = new \ClasseGeral\ManipulaSessao();
        $usuario = $this->buscaUsuarioLogado();
        $adm = $usuario['administrador_sistema'] == 'S';

        $menus = $ms->pegar('menu');

        $acoes = $menus['acoes'] ?? [];
        $camposVerPerfil = $menus['campos'] ?? [];

        //Verificando se tem campo a ser validado
        if (count($camposVerPerfil) > 0 && isset($camposVerPerfil[$classe])) {
            //Vendo os campos da lista consulta
            foreach ($estrutura['listaConsulta'] as $campo => $val) {
                $val = array_merge($val, $estrutura['campos'][$campo] ?? []);
                $verPerfil = isset($val['verificarPerfil']) && $val['verificarPerfil'] && !$adm;
                if ($verPerfil) {
                    if (! array_key_exists($campo, $camposVerPerfil)) {
                        unset($estrutura['listaConsulta'][$campo]);
                    }
                }
            }
            //Vendo primeiro nos campos principais
            foreach ($estrutura['campos'] as $campo => $val) {
                $verPerfil = isset($val['verificarPerfil']) && $val['verificarPerfil'] && !$adm;
                if ($verPerfil) {
                    if (! array_key_exists($campo, $camposVerPerfil)) {
                        unset($estrutura['campos'][$campo]);
                    }
                }
            }
        }
        return $estrutura;
    }

    /**
     * Valida a estrutura JSON removendo campos que não estão autorizados no perfil do usuário
     * @param array $estrutura Estrutura JSON a ser validada
     * @return array Estrutura validada
     */
    protected function validaEstrutura($estrutura)
    {
        $ms = new \ClasseGeral\ManipulaSessao();
        $usuario = $this->buscaUsuarioLogado();
        $adm = $usuario['administrador_sistema'] == 'S';

        // Se for administrador, não precisa validar
        if ($adm) {
            return $estrutura;
        }

        $menus = $ms->pegar('menu');
        $camposValidar = $menus['campos'] ?? [];

        // Se não há campos para validar, retorna a estrutura original
        if (empty($camposValidar)) {
            return $estrutura;
        }

        // Valida recursivamente a estrutura
        $estrutura = $this->validaEstruturaRecursiva($estrutura, $camposValidar);

        return $estrutura;
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
                $camposConcatenados[$campo] = array_merge($config, $camposDestino[$campo]);
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
                if (in_array($campo, $camposValidar)) {
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
}
