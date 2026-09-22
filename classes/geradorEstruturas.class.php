<?php

/**
 * Gerador de estruturas — ferramenta de DESENVOLVIMENTO.
 *
 * Substitui o pipeline legado `montaEstrutura.php` + `converteJSONPHP.php`:
 * a partir de uma tabela, gera direto a classe PHP moderna
 * (`api/backLocal/classes/{Classe}.class.php`) com `estrutura()`.
 *
 * É carregado pelo fallback do router (`classes/{tabela}.class.php`) e,
 * portanto, passa pelo `authMiddleware` (diferente dos scripts soltos antigos).
 * Uso pretendido: desenvolvimento (e depois compilado para publicação).
 */
class geradorEstruturas extends \ClasseGeral\ClasseGeral
{
    /**
     * Lista as tabelas/views da base principal (para a UI de estrutura).
     * @return string JSON [{ nome, tipo }]
     */
    public function listarTabelas($parametros = [])
    {
        $base = $this->pegaDataBase();
        $sql = "SELECT TABLE_NAME AS nome, TABLE_TYPE AS tipo
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = " . var_export($base, true) . "
                ORDER BY TABLE_NAME";
        $linhas = $this->retornosqldireto($sql, '', '', '', false, false);
        return json_encode(is_array($linhas) ? $linhas : []);
    }

    /**
     * Lista as colunas de uma tabela (para a UI de estrutura).
     * Parâmetros: tabela
     * @return string JSON [{ campo, tipo, tamanho, tipoConsulta }]
     */
    public function camposTabela($parametros)
    {
        $tabela = trim((string)($parametros['tabela'] ?? ''));
        if (!$this->nomeValido($tabela)) {
            return json_encode(['erro' => 'Tabela inválida']);
        }

        $campos = $this->pegaTabelasInfo()->campostabela($tabela);
        if (!is_array($campos) || sizeof($campos) === 0) {
            return json_encode(['erro' => "Tabela '{$tabela}' não encontrada ou sem colunas"]);
        }

        $lista = [];
        foreach ($campos as $key => $c) {
            $lista[] = [
                'campo' => $c['campo'] ?? $key,
                'tipo' => $c['tipo'] ?? '',
                'tamanho' => $c['tamanho'] ?? '',
                'tipoConsulta' => $c['tipoConsulta'] ?? '',
            ];
        }
        return json_encode($lista);
    }

    /**
     * Gera a classe de estrutura de uma tabela.
     *
     * Parâmetros ($_POST):
     * - tabela        (obrigatório) nome da tabela/view
     * - nomeUsual     (opcional) rótulo humano (default: tabela)
     * - classe        (opcional) nome da classe (default: nomeClase(tabela))
     * - tipoEstrutura (opcional) default 'padrao'
     * - campos        (opcional) JSON { campo: { texto, md, sm, lg, xs,
     *                  obrigatorio, habilitadoEdicao, tipo, ... } } — quando
     *                  ausente, os campos são derivados das colunas da tabela.
     * - sobrescrever  (opcional) 'S' p/ sobrescrever classe existente
     * - criarMenu     (opcional) 'S' p/ inserir menu + item
     * - menu          (opcional) rótulo do menu (default: nomeUsual)
     * - posicaoMenu   (opcional) posição do menu
     *
     * @return string JSON { sucesso, classe, caminho, estrutura } | { erro }
     */
    public function criarEstrutura($parametros)
    {
        $p = is_array($parametros) ? $parametros : [];

        $tabela = trim((string)($p['tabela'] ?? ''));
        if ($tabela === '') {
            return json_encode(['erro' => 'Informe a tabela']);
        }

        $nomeUsual = trim((string)($p['nomeUsual'] ?? '')) ?: $tabela;
        $tipoEstrutura = trim((string)($p['tipoEstrutura'] ?? '')) ?: 'padrao';
        $classe = trim((string)($p['classe'] ?? '')) ?: $this->nomeClase($tabela);
        $classe = preg_replace('/[^A-Za-z0-9_]/', '', $classe);

        $tbInfo = $this->pegaTabelasInfo();
        $campoChaveTabela = strtolower((string)$tbInfo->campochavetabela($tabela));
        $camposTabela = array_change_key_case($tbInfo->campostabela($tabela), CASE_LOWER);

        if (sizeof($camposTabela) === 0) {
            return json_encode(['erro' => "Tabela '{$tabela}' não encontrada ou sem colunas"]);
        }

        // Opções da estrutura (UI) — sobrepõem os defaults.
        $opcoes = $p['opcoes'] ?? null;
        if (is_string($opcoes)) {
            $opcoes = json_decode($opcoes, true);
        }
        if (!is_array($opcoes)) {
            $opcoes = [];
        }
        $op = function (string $chave, $default) use ($opcoes) {
            return array_key_exists($chave, $opcoes) && $opcoes[$chave] !== '' && $opcoes[$chave] !== null
                ? $opcoes[$chave]
                : $default;
        };

        $nomeUsual = trim((string)$op('nomeUsual', $nomeUsual)) ?: $nomeUsual;
        $tipoEstrutura = trim((string)$op('tipoEstrutura', $tipoEstrutura)) ?: 'padrao';
        $campoChave = strtolower(trim((string)$op('campo_chave', $campoChaveTabela)));
        $raizModelo = strtolower(trim((string)$op('raizModelo', strtolower($classe))));
        $tabelaConsulta = trim((string)$op('tabelaConsulta', $tabela));

        $estrutura = [
            'tipoEstrutura' => $tipoEstrutura,
            'tabela' => $tabela,
            'tabelaConsulta' => $tabelaConsulta,
            'campo_chave' => $campoChave,
            'raizModelo' => $raizModelo,
            'textoPagina' => (string)$op('textoPagina', 'Gerenciamento de ' . $nomeUsual),
            'textoNovo' => (string)$op('textoNovo', 'Incluir ' . $nomeUsual),
            'nomeUsual' => $nomeUsual,
            'textoFormCadastro' => (string)$op('textoFormCadastro', 'Inclusao de ' . $nomeUsual),
            'textoFormAlteracao' => (string)$op('textoFormAlteracao', 'Alteracao de ' . $nomeUsual),
            'tipoListaConsulta' => (string)$op('tipoListaConsulta', 'lista'),
            'ocultarBotoesSuperiores' => (bool)$op('ocultarBotoesSuperiores', true),
            'todosCamposMaiusculo' => (bool)$op('todosCamposMaiusculo', true),
            'todasEtiquetasEmbutidas' => (bool)$op('todasEtiquetasEmbutidas', true),
            'filtrarAoIniciar' => (bool)$op('filtrarAoIniciar', true),
            'listaConsulta' => [],
            'campos' => [],
        ];

        // Campos customizados (UI) têm prioridade; senão, deriva das colunas.
        $camposCustom = $p['campos'] ?? null;
        if (is_string($camposCustom)) {
            $camposCustom = json_decode($camposCustom, true);
        }

        if (is_array($camposCustom) && sizeof($camposCustom) > 0) {
            foreach ($camposCustom as $nomeCampo => $cfg) {
                if (!$this->nomeValido((string)$nomeCampo)) {
                    continue;
                }
                $estrutura['campos'][$nomeCampo] = is_array($cfg) ? $cfg : [];
            }
        } else {
            foreach ($camposTabela as $key => $val) {
                // Colunas de chave (FKs/PK) começam por "CHAVE" no legado → oculto.
                if (substr((string)$val['campo'], 0, 5) === 'CHAVE') {
                    $estrutura['campos'][$key] = ['tipo' => 'oculto'];
                } else {
                    $estrutura['campos'][$key] = ['texto' => '', 'sm' => '2'];
                }
            }
        }

        // Campos da consulta (UI): { campo: { texto, md } }
        $listaCustom = $p['listaConsulta'] ?? null;
        if (is_string($listaCustom)) {
            $listaCustom = json_decode($listaCustom, true);
        }
        if (is_array($listaCustom)) {
            foreach ($listaCustom as $nomeCampo => $cfg) {
                if (!$this->nomeValido((string)$nomeCampo)) {
                    continue;
                }
                $estrutura['listaConsulta'][$nomeCampo] = is_array($cfg) ? $cfg : [];
            }
        }

        // Tabelas relacionadas (UI): 1 nível — tabela + bloco de repetição.
        $relacionadas = $p['relacionadas'] ?? null;
        if (is_string($relacionadas)) {
            $relacionadas = json_decode($relacionadas, true);
        }
        if (is_array($relacionadas) && sizeof($relacionadas) > 0) {
            $estrutura['tabelasRelacionadas'] = [];
            $iBloco = 0;
            foreach ($relacionadas as $rel) {
                if (!is_array($rel)) {
                    continue;
                }
                $tabRel = trim((string)($rel['tabela'] ?? ''));
                if (!$this->nomeValido($tabRel)) {
                    continue;
                }

                $raiz = trim((string)($rel['raizModelo'] ?? '')) ?: $this->nomeClase($tabRel);
                // `campo_chave` da relacionada = PK da PRÓPRIA tabela filha (ex.:
                // chave_item), nunca o FK. Sem isso o backend confunde item novo
                // com update e gera `WHERE <pk> = ` (vazio) → erro de SQL.
                $campoChaveRel = strtolower((string)$tbInfo->campochavetabela($tabRel));
                if ($campoChaveRel === '') {
                    $campoChaveRel = strtolower(trim((string)($rel['campo_chave'] ?? '')));
                }
                // `campo_relacionamento` = FK para a tabela principal (default: PK dela).
                $campoRelacionamento = strtolower(trim((string)($rel['campo_relacionamento'] ?? '')));
                if ($campoRelacionamento === '') {
                    $campoRelacionamento = $campoChaveTabela;
                }
                $texto = trim((string)($rel['texto'] ?? '')) ?: $tabRel;
                $titulo = trim((string)($rel['titulo'] ?? '')) ?: $texto;
                $campoValor = trim((string)($rel['campoValor'] ?? ''));
                $minimo = (int)($rel['minimoItensBloco'] ?? 0);
                $opcaoComprimir = !empty($rel['opcaoComprimir']);

                $camposRel = $rel['campos'] ?? [];
                if (!is_array($camposRel)) {
                    $camposRel = [];
                }

                // `detalhes` (exibição) e `itens` (repetição) derivados dos campos.
                $detalhes = [];
                $itens = [];
                foreach ($camposRel as $nomeCampo => $cfg) {
                    if (!$this->nomeValido((string)$nomeCampo)) {
                        continue;
                    }
                    $cfg = is_array($cfg) ? $cfg : [];
                    $det = [];
                    if (!empty($cfg['texto'])) $det['texto'] = $cfg['texto'];
                    if (!empty($cfg['sm'])) $det['sm'] = $cfg['sm'];
                    elseif (!empty($cfg['md'])) $det['sm'] = $cfg['md'];
                    $detalhes[$nomeCampo] = $det;
                    $itens[$nomeCampo] = [];
                }

                $estrutura['tabelasRelacionadas'][$tabRel] = [
                    'raizModelo' => $raiz,
                    'campo_chave' => $campoChaveRel,
                    'campo_relacionamento' => $campoRelacionamento,
                    'texto' => $texto,
                    'detalhes' => $detalhes,
                ];

                $repeticao = [
                    'tabela' => $tabRel,
                    'campoChave' => $campoChaveRel,
                    'campoValor' => $campoValor,
                    'nomeRepeticao' => 'item',
                    'itemRepetir' => $raiz,
                    'itens' => $itens,
                ];
                if ($minimo > 0) {
                    $repeticao['minimoItensBloco'] = (string)$minimo;
                }

                $estrutura['campos']['bloco_' . $iBloco] = [
                    'titulo' => $titulo,
                    'nomeBloco' => $raiz,
                    'tabela' => $tabRel,
                    'campoChave' => $campoChaveRel,
                    'variavelSalvar' => $raiz,
                    'opcaoComprimir' => $opcaoComprimir ? 'true' : 'false',
                    'classes' => 'col-xs-12 div1',
                    'campos' => $camposRel,
                    'repeticao' => $repeticao,
                ];
                $iBloco++;
            }
        }

        $codigo = "<?php\n\n"
            . "class {$classe} extends \\ClasseGeral\\ClasseGeral\n{\n"
            . "    private string \$funcoes = \"BaseArcabouco/funcoes.class.php\";\n"
            . "    private string \$tabela = " . var_export($tabela, true) . ";\n"
            . "    private string \$tabelaConsulta = " . var_export($tabelaConsulta, true) . ";\n"
            . "    private string \$campoChave = " . var_export($campoChave, true) . ";\n"
            . "    private string \$campoValor = \"\";\n\n"
            . "    public function estrutura(): array\n    {\n"
            . "        return " . $this->exportArray($estrutura, 2) . ";\n"
            . "    }\n}\n";

        $caminho = $this->pegaCaminhoApi() . 'api/backLocal/classes/' . $classe . '.class.php';

        if (file_exists($caminho) && empty($p['sobrescrever'])) {
            return json_encode([
                'erro' => "A classe '{$classe}' já existe (use sobrescrever=S)",
                'caminho' => $caminho,
            ]);
        }

        if (@file_put_contents($caminho, $codigo) === false) {
            return json_encode(['erro' => 'Não foi possível gravar o arquivo', 'caminho' => $caminho]);
        }

        $menuCriado = null;
        if (!empty($p['criarMenu'])) {
            $menuCriado = $this->criarMenu($p, $nomeUsual, $classe);
        }

        return json_encode([
            'sucesso' => true,
            'classe' => $classe,
            'caminho' => $caminho,
            'estrutura' => $estrutura,
            'menu' => $menuCriado,
        ]);
    }

    /**
     * Insere o menu + item apontando para a nova tela.
     * Retorna ['chave_menu'=>..., 'chave_item'=>...] ou ['erro'=>...].
     */
    private function criarMenu(array $p, string $nomeUsual, string $classe)
    {
        try {
            $nomeMenu = trim((string)($p['menu'] ?? '')) ?: $nomeUsual;

            $chaveMenu = $this->inclui('menus', [
                'menu' => $nomeMenu,
                'posicao' => (int)($p['posicaoMenu'] ?? 0),
                'disponivel' => 'S',
            ]);

            if (!$chaveMenu || $chaveMenu <= 0) {
                return ['erro' => 'Falha ao inserir o menu'];
            }

            $chaveItem = $this->inclui('menus_itens', [
                'chave_menu' => $chaveMenu,
                'item' => $nomeUsual,
                'link' => '',
                'target' => '',
                'posicao' => 1,
                'pagina' => 'sistema',
                'acao' => $classe,
                'subacao' => '',
                'disponivel' => 'S',
            ]);

            return ['chave_menu' => $chaveMenu, 'chave_item' => $chaveItem];
        } catch (\Throwable $e) {
            return ['erro' => 'Classe criada, mas falhou o menu: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // FASE 1 — CRIAR TABELA (DDL)
    // =========================================================================

    /** Tipos suportados → tipo MySQL. */
    private const TIPOS = [
        'int' => 'INT',
        'inteiro' => 'INT',
        'bigint' => 'BIGINT',
        'tinyint' => 'TINYINT',
        'varchar' => 'VARCHAR',
        'char' => 'CHAR',
        'text' => 'TEXT',
        'longtext' => 'LONGTEXT',
        'decimal' => 'DECIMAL',
        'float' => 'FLOAT',
        'double' => 'DOUBLE',
        'date' => 'DATE',
        'datetime' => 'DATETIME',
        'timestamp' => 'TIMESTAMP',
        'time' => 'TIME',
    ];

    /** Nomes com parâmetros que exigem tamanho entre parênteses. */
    private const TIPOS_COM_TAMANHO = [
        'VARCHAR', 'CHAR', 'INT', 'BIGINT', 'TINYINT', 'DECIMAL', 'FLOAT', 'DOUBLE',
    ];

    /**
     * Pré-visualiza o DDL (dry-run) da tabela — NÃO executa.
     * Parâmetros: schema (JSON string | array) { tabela, campos[], indices[] }
     */
    public function previaTabela($parametros)
    {
        $schema = $this->lerSchemaTabela($parametros);
        if (isset($schema['erro'])) {
            return json_encode($schema);
        }
        return json_encode([
            'sucesso' => true,
            'sql' => $this->gerarCreateTable($schema),
        ]);
    }

    /**
     * Cria a tabela (executa o DDL). Exige confirmar=S.
     * Parâmetros: schema + confirmar + (opcional) gerarEstrutura=S
     */
    public function criarTabela($parametros)
    {
        $schema = $this->lerSchemaTabela($parametros);
        if (isset($schema['erro'])) {
            return json_encode($schema);
        }

        if (empty($parametros['confirmar'])) {
            return json_encode(['erro' => 'Confirmação necessária (confirmar=S)']);
        }

        $tabela = $schema['tabela'];
        $sql = $this->gerarCreateTable($schema);

        try {
            $this->executasql($sql, $this->pegaDataBase($tabela));
        } catch (\Throwable $e) {
            return json_encode(['erro' => 'Falha ao criar a tabela: ' . $e->getMessage(), 'sql' => $sql]);
        }

        $estrutura = null;
        if (!empty($parametros['gerarEstrutura'])) {
            $r = json_decode($this->criarEstrutura([
                'tabela' => $tabela,
                'nomeUsual' => $parametros['nomeUsual'] ?? '',
                'classe' => $parametros['classe'] ?? '',
                'tipoEstrutura' => $parametros['tipoEstrutura'] ?? 'padrao',
                'sobrescrever' => $parametros['sobrescrever'] ?? '',
                'criarMenu' => $parametros['criarMenu'] ?? '',
                'menu' => $parametros['menu'] ?? '',
                'posicaoMenu' => $parametros['posicaoMenu'] ?? '',
            ]), true);
            $estrutura = $r;
        }

        return json_encode([
            'sucesso' => true,
            'tabela' => $tabela,
            'sql' => $sql,
            'estrutura' => $estrutura,
        ]);
    }

    /**
     * Lista as classes de estrutura existentes (arquivos em
     * api/backLocal/classes/), para carregar/editar no gerador.
     * @return string JSON [ "classe", ... ]
     */
    public function listarClasses($parametros = [])
    {
        $dir = $this->pegaCaminhoApi() . 'api/backLocal/classes/';
        $ignorar = ['dadosConexao', 'configuracoesTabelas'];
        $classes = [];

        if (is_dir($dir)) {
            foreach (glob($dir . '*.class.php') as $arq) {
                $nome = basename($arq, '.class.php');
                if (in_array($nome, $ignorar, true)) {
                    continue;
                }
                $classes[] = $nome;
            }
        }
        sort($classes);
        return json_encode($classes);
    }

    /**
     * Lê a estrutura de uma classe existente (para editar no gerador).
     * Parâmetros: classe
     * @return string JSON { sucesso, classe, estrutura } | { erro }
     */
    public function lerEstrutura($parametros)
    {
        $classe = trim((string)($parametros['classe'] ?? ''));
        if (!$this->nomeValido($classe)) {
            return json_encode(['erro' => 'Classe inválida']);
        }

        $arquivo = $this->pegaCaminhoApi() . 'api/backLocal/classes/' . $classe . '.class.php';
        if (!is_file($arquivo)) {
            return json_encode(['erro' => "Classe '{$classe}' não encontrada"]);
        }

        require_once($arquivo);
        if (!class_exists($classe)) {
            return json_encode(['erro' => "A classe '{$classe}' não foi definida no arquivo"]);
        }

        try {
            $obj = new $classe();
        } catch (\Throwable $e) {
            return json_encode(['erro' => 'Falha ao instanciar a classe: ' . $e->getMessage()]);
        }

        if (!method_exists($obj, 'estrutura')) {
            return json_encode(['erro' => 'A classe não tem o método estrutura()']);
        }

        $estrutura = $obj->estrutura();
        if (!is_array($estrutura)) {
            return json_encode(['erro' => 'estrutura() não retornou um array']);
        }

        return json_encode(['sucesso' => true, 'classe' => $classe, 'estrutura' => $estrutura]);
    }

    /** Lê e valida o schema de tabela recebido. */
    private function lerSchemaTabela($parametros)
    {
        $raw = $parametros['schema'] ?? '';
        $schema = is_array($raw) ? $raw : json_decode((string)$raw, true);

        if (!is_array($schema)) {
            return ['erro' => 'Schema inválido'];
        }

        $tabela = trim((string)($schema['tabela'] ?? ''));
        if (!$this->nomeValido($tabela)) {
            return ['erro' => 'Nome de tabela inválido (use letras, números e _)'];
        }

        $campos = $schema['campos'] ?? null;
        if (!is_array($campos) || sizeof($campos) === 0) {
            return ['erro' => 'Informe ao menos um campo'];
        }

        $nomes = [];
        foreach ($campos as $i => $c) {
            $nome = trim((string)($c['nome'] ?? ''));
            if (!$this->nomeValido($nome)) {
                return ['erro' => "Campo #" . ($i + 1) . " com nome inválido"];
            }
            if (in_array($nome, $nomes, true)) {
                return ['erro' => "Campo '{$nome}' duplicado"];
            }
            $nomes[] = $nome;

            $tipo = strtolower(trim((string)($c['tipo'] ?? '')));
            if (!isset(self::TIPOS[$tipo])) {
                return ['erro' => "Tipo inválido no campo '{$nome}'"];
            }
        }

        return $schema;
    }

    /** Nome de tabela/coluna seguro (evita injeção no DDL). */
    private function nomeValido(string $nome): bool
    {
        return $nome !== '' && (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $nome);
    }

    /**
     * Exporta um array como código PHP com sintaxe curta `[]` (indentado).
     * Substitui `var_export` (que gera `array ()`).
     */
    private function exportArray(array $arr, int $nivel): string
    {
        if (sizeof($arr) === 0) {
            return '[]';
        }

        $indFilho = str_repeat('    ', $nivel + 1);
        $indFecha = str_repeat('    ', $nivel);

        $ehLista = array_keys($arr) === range(0, sizeof($arr) - 1);
        $linhas = [];
        foreach ($arr as $k => $v) {
            $prefixo = $ehLista ? '' : var_export((string)$k, true) . ' => ';
            $linhas[] = $indFilho . $prefixo . $this->exportValor($v, $nivel + 1);
        }

        return "[\n" . implode(",\n", $linhas) . "\n" . $indFecha . ']';
    }

    /** Exporta um valor escalar/array para código PHP. */
    private function exportValor($v, int $nivel): string
    {
        if (is_array($v)) {
            return $this->exportArray($v, $nivel);
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if ($v === null) {
            return 'null';
        }
        return var_export($v, true); // string/int/float (com escape de string)
    }

    /** Monta o CREATE TABLE a partir do schema (já validado). */
    private function gerarCreateTable(array $schema): string
    {
        $tabela = $schema['tabela'];
        $linhas = [];
        $pks = [];

        foreach ($schema['campos'] as $c) {
            $nome = trim((string)$c['nome']);
            $tipo = self::TIPOS[strtolower(trim((string)$c['tipo']))];

            $tamanho = trim((string)($c['tamanho'] ?? ''));
            $tipoSql = $tipo;
            if ($tamanho !== '' && in_array($tipo, self::TIPOS_COM_TAMANHO, true)) {
                // decimal aceita "10,2"; o resto só dígitos.
                $tamanhoSeguro = $tipo === 'DECIMAL'
                    ? preg_replace('/[^0-9,]/', '', $tamanho)
                    : preg_replace('/[^0-9]/', '', $tamanho);
                if ($tamanhoSeguro !== '') {
                    $tipoSql .= '(' . $tamanhoSeguro . ')';
                }
            }

            $obrigatorio = !empty($c['obrigatorio']) || !empty($c['pk']);
            $clausula = "`{$nome}` {$tipoSql} " . ($obrigatorio ? 'NOT NULL' : 'NULL');

            if (isset($c['padrao']) && $c['padrao'] !== '') {
                $clausula .= ' DEFAULT ' . $this->valorDefaultSql((string)$c['padrao'], $tipo);
            }
            if (!empty($c['autoIncrement'])) {
                $clausula .= ' AUTO_INCREMENT';
            }

            $linhas[] = $clausula;

            if (!empty($c['pk'])) {
                $pks[] = $nome;
            }
        }

        if (sizeof($pks) > 0) {
            $linhas[] = 'PRIMARY KEY (' . implode(', ', array_map(fn($k) => "`{$k}`", $pks)) . ')';
        }

        foreach (($schema['indices'] ?? []) as $idx) {
            $camposIdx = $idx['campos'] ?? [];
            if (!is_array($camposIdx) || sizeof($camposIdx) === 0) {
                continue;
            }
            $cols = [];
            foreach ($camposIdx as $col) {
                $col = trim((string)$col);
                if ($this->nomeValido($col)) {
                    $cols[] = "`{$col}`";
                }
            }
            if (sizeof($cols) === 0) {
                continue;
            }
            $unico = !empty($idx['unico']) ? 'UNIQUE KEY' : 'KEY';
            $nomeIdx = $this->nomeValido((string)($idx['nome'] ?? ''))
                ? (string)$idx['nome']
                : 'idx_' . implode('_', $camposIdx);
            $linhas[] = "{$unico} `{$nomeIdx}` (" . implode(', ', $cols) . ')';
        }

        return "CREATE TABLE `{$tabela}` (\n  "
            . implode(",\n  ", $linhas)
            . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    }

    /** Valor de DEFAULT seguro por tipo. */
    private function valorDefaultSql(string $valor, string $tipo): string
    {
        if (strtoupper($valor) === 'CURRENT_TIMESTAMP') {
            return 'CURRENT_TIMESTAMP';
        }
        if (in_array($tipo, ['INT', 'BIGINT', 'TINYINT', 'DECIMAL', 'FLOAT', 'DOUBLE'], true)) {
            $num = str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $valor));
            return $num === '' || $num === '-' ? '0' : $num;
        }
        return "'" . str_replace("'", "''", $valor) . "'";
    }
}
