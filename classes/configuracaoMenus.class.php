<?php

class configuracaoMenus extends \ClasseGeral\ClasseGeral
{
    private string $funcoes = "BaseArcabouco/funcoes.class.php";
    private string $tabela = "menus";
    private string $tabelaConsulta = "menus";
    private string $campoChave = "chave_menu";
    private string $campoValor = "";


    public function estrutura(): array
    {
        return [
            'tipoEstrutura' => 'padrao',
            'ordemConsulta' => 'posicao',
            'tabela' => 'menus',
            'campo_chave' => 'chave_menu',
            'raizModelo' => 'menu',
            'textoPagina' => 'Gerenciamento de Menus do Sistema',
            'textoNovo' => 'Incluir Menu',
            'nomeUsual' => 'Menus',
            'textoFormCadastro' => 'Inclusão de Menus',
            'textoFormAlteracao' => 'Alteração de Menus',
            'tabelasRelacionadas' => [
                'menus_itens' => [
                    'raizModelo' => 'menuItens',
                    'campo_chave' => 'chave_item',
                    'campo_relacionamento' => 'chave_menu',
                    'texto' => 'Ítens do Menu',
                    'tabelasSubRelacionadas' => [
                        'menus_itens_acoes' => [
                            'raizModelo' => 'acoes',
                            'campo_chave' => 'chave_acao',
                            'campo_relacionamento' => 'chave_item',
                            'texto' => 'Acoes do Ítem',
                        ],
                        'menus_itens_campos' => [
                            'raizModelo' => 'campos',
                            'campo_chave' => 'chave_campo',
                            'campo_relacionamento' => 'chave_item',
                            'texto' => 'Campos do Ítem',
                        ],
                    ],
                ],
            ],
            'listaConsulta' => [
                'menu' => [
                    'texto' => 'Menu',
                    'md' => '7',
                ],
                'posicao' => [
                    'texto' => 'Pos.',
                    'md' => '2',
                ],
            ],
            'campos' => [
                'bloco_0' => [
                    'classes' => 'col-xs-12 div1',
                    'classesFilho' => 'row',
                    'campos' => [
                        'chave_menu' => ['tipo' => 'oculto',],
                        'menu' => [
                            'texto' => 'Menu',
                            'md' => '6',
                            'atributos_input' => ['valor_existe' => '',],
                            'obrigatorio' => 'true',
                        ],
                        'posicao' => [
                            'texto' => 'Posição',
                            'md' => '1',
                            'tipo' => 'inteiro',
                        ],
                    ],
                ],
                'bloco_1' => [
                    'titulo' => 'Itens do Menu',
                    'classes' => 'col-xs-12',
                    'classesFilho' => 'row div4 top30',
                    'nomeBloco' => 'menuItens',
                    'tabela' => 'menus_itens',
                    'campoChave' => 'chave_item',
                    'aoExcluir' => 'E',
                    'variavelSalvar' => 'menuItens',
                    'variavelInferior' => 'acoes',
                    'campos' => [
                        'chave_item' => [
                            'tipo' => 'oculto',
                            'padrao' => '0',
                        ],
                        'item' => [
                            'texto' => 'Item',
                            'md' => '3',
                            'obrigatorio' => 'true',
                        ],
                        'pagina' => [
                            'texto' => 'Diretório',
                            'md' => '3',
                            'obrigatorio' => 'true',
                        ],
                        'acao' => [
                            'texto' => 'Arquivo',
                            'md' => '2',
                            'obrigatorio' => 'true',
                        ],
                        'subacao' => [
                            'texto' => 'Ação',
                            'md' => '2',
                        ],
                        'botaoAcoesPadrao' => [
                            'click' => 'adicionarAcoesPadrao()',
                            'texto' => 'Ações Padrao',
                            'classes' => 'btn btn-primary baixarbotao input-lg',
                            'md' => '1',
                        ],
                    ],
                    'repeticao' => [
                        'classes' => '',
                        'nomeRepeticao' => 'item',
                        'campoChave' => 'chave_item',
                        'campoValor' => 'item',
                        'itemRepetir' => 'menuItens',
                        'itens' => [
                            'item' => ['texto' => 'Ítem',],
                            'pagina' => ['texto' => 'Dir.',],
                            'acao' => ['texto' => 'Arq.',],
                            'subacao' => ['texto' => 'Ação',],
                        ],
                        'campos' => [
                            'bloco_1_0' => [
                                'classes' => 'col-xs-12 div6 borda',
                                'nomeBloco' => 'acoes',
                                'tabela' => 'menus_itens_acoes',
                                'campoChave' => 'chave_acao',
                                'aoExcluir' => 'E',
                                'variavelSalvar' => 'acoes',
                                'variavelSuperior' => 'menuItens',
                                'titulo' => 'Ações do Item',
                                'campos' => [
                                    'acao' =>
                                        [
                                            'texto' => 'Ação do Item',
                                            'md' => '2',
                                        ],
                                    'titulo_acao' => [
                                        'texto' => 'Título',
                                        'md' => '3',
                                    ],
                                    'descricao' => [
                                        'texto' => 'Descrição (Hint)',
                                        'md' => '5',
                                    ],
                                ],
                                'repeticao' => [
                                    'campoChave' => 'chave_acao',
                                    'campoValor' => 'acao',
                                    'itemRepetir' => 'acoes',
                                    'classes' => '',
                                    'itens' => [
                                        0 => 'acao',
                                    ],
                                    'tabela' => 'menus_itens_acoes',
                                    'nomeRepeticao' => 'acao',
                                    'mensagemExcluir' => 'Excluir',
                                    'nivel' => '2',
                                ],
                            ],
                            'bloco_campos_itens' => [
                                'classes' => 'col-xs-12 div2 borda',
                                'classesFilho' => 'row div3',
                                'nomeBloco' => 'campos',
                                'tabela' => 'menus_itens_campos',
                                'campoChave' => 'chave_campo',
                                'aoExcluir' => 'E',
                                'variavelSalvar' => 'campos',
                                'variavelSuperior' => 'menuItens',
                                'titulo' => 'Campos do Ítem',
                                'campos' => [
                                    'campo' => [
                                        'texto' => 'Campo do Ítem',
                                        'md' => '3',
                                    ],
                                    'titulo_campo' => [
                                        'texto' => 'Título',
                                        'md' => '3',
                                    ],
                                    'descricao' => [
                                        'texto' => 'Descrição (Hint)',
                                        'md' => '4',
                                    ],
                                ],
                                'repeticao' => [
                                    'campoChave' => 'chave_campo',
                                    'campoValor' => 'campo',
                                    'itemRepetir' => 'campos',
                                    'classes' => '',
                                    'itens' => [0 => 'campo',],
                                    'tabela' => 'menus_itens_campos',
                                    'nomeRepeticao' => 'campo',
                                    'mensagemExcluir' => 'Excluir',
                                    'nivel' => '2',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
