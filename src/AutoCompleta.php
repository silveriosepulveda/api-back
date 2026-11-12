<?php

/**
 * AutoCompleta - Classe para autocompletar campos (completacampo).
 *
 * Extraída de conClasseGeral.php para centralizar a lógica de autocompletar.
 *
 * @package ClasseGeral
 */

namespace ClasseGeral;

class AutoCompleta extends \ClasseGeral\ClasseGeral
{
    /**
     * Completa um campo com sugestões baseadas em um texto informado.
     *
     * @param array $p Parâmetros para a busca:
     *   - tabela: Nome da tabela.
     *   - campo_chave: Nome do campo chave.
     *   - campo_valor: Nome do campo de exibição.
     *   - term: Texto digitado pelo usuário.
     *   - campo_chave2, campo_chave3, campo_chave4: Campos adicionais de filtro.
     *   - chave2, chave3, chave4: Valores dos campos adicionais.
     *   - minimo: Número mínimo de caracteres para buscar.
     *   - ordenar: 'S' para ordenar por campo_valor.
     *   - campoOrdem: Campo específico para ordenação.
     *   - repetirvalores: 'S' para permitir valores repetidos.
     *   - verificarEmpresaUsuario: Se deve filtrar por empresa do usuário logado.
     *
     * @return string JSON com [{chave, valor, complemento_valor, valor2, valor3, valor4, imagem}].
     */
    public function completacampo(array $p): string
    {
        $tbInfo = new \ClasseGeral\TabelasInfo();

        $mostrarSQL = false;
        $texto = $p['term'] ?? '';

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
        if ($chave2 === '') $chave2 = 0;

        $campo_chave3 = isset($p['campo_chave3']) ? strtolower($p['campo_chave3']) : '';
        $chave3 = $p['chave3'] ?? 0;
        if ($chave3 === '') $chave3 = 0;

        $campo_chave4 = isset($p['campo_chave4']) && $p['campo_chave4'] != '' ? strtolower($p['campo_chave4']) : '';
        $chave4 = $p['chave4'] ?? 0;
        if ($chave4 === '') $chave4 = 0;

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

        // chave3/chave4 entram sem aspas no SQL. Se vierem vazias/undefined,
        // o AND fica incompleto ("= and ...") e a query retorna 500.
        $chave3Valida = is_scalar($chave3)
            && $chave3 !== ''
            && $chave3 !== 'undefined'
            && $chave3 !== 'null';
        if ($campo_chave3 != '' && $chave3Valida) {
            $sql .= " AND TP.$campo_chave3 = $chave3";
        }

        $chave4Valida = is_scalar($chave4)
            && $chave4 !== ''
            && $chave4 !== 'undefined'
            && $chave4 !== 'null';
        if ($campo_chave4 != '' && $chave4Valida) {
            $sql .= " AND TP.$campo_chave4 = $chave4";
        }

        if (isset($campos_tabela['arquivado'])) {
            $sql .= ' AND TP.arquivado = "N"';
        }
        if (isset($campos_tabela['disponivel'])) {
            $sql .= ' AND TP.disponivel = "S"';
        }

        // Esses campos são comparados exclusivamente para a Central, depois vejo isso.
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
        if (is_file($caminhoApiLocal . '/api/backLocal/classes/configuracoesTabelas.class.php')) {
            require_once $caminhoApiLocal . '/api/backLocal/classes/configuracoesTabelas.class.php';
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
            // Campo de ordenação vazio gera "ORDER BY TP." e erro de SQL.
            // Alguns autoCompletes enviam campoOrdem '' quando não configurado.
            $campoOrdem = isset($p['campoOrdem']) && $p['campoOrdem'] != 'null' && $p['campoOrdem'] != ''
                ? $p['campoOrdem']
                : $campo_valor;
            $sql .= " ORDER BY TP.$campoOrdem";
        } else {
            $sql .= " ORDER BY TP.$campo_chave";
        }

        if ($mostrarSQL)
            echo $sql;

        $dados = $this->retornosqldireto($sql, '', $tabela, false, false);

        $temp = []; //Variável para não deixar repetir valores
        $retorno = [];

        foreach ($dados as $key => $resultado) {
            $valor = $resultado[strtolower($campo_valor)];

            //Comparando se o valor já foi lançado, se sim não lanço novamente
            ////Tirei esta comparacao pois podem haver dois nomes iguais
            //e tambem pus o complemento_valor para identificar quando acontecer
            if (!array_key_exists($valor, $temp) || $repetirvalores == 'S') {
                $temp[$valor] = $valor;
                $retorno[] = [
                    'chave' => $resultado[strtolower($campo_chave)],
                    'valor' => $resultado[strtolower($campo_valor)],
                    'complemento_valor' => $resultado[strtolower($complemento_valor)] ?? '',
                    'valor2' => $resultado[strtolower($campo_valor2)] ?? '',
                    'valor3' => $resultado[strtolower($campo_valor3)] ?? '',
                    'valor4' => $resultado[strtolower($campo_valor4)] ?? '',
                    'imagem' => $resultado[strtolower($campo_imagem)] ?? '',
                ];
            }
        }
        return json_encode($retorno);
        //*/
    }

    /**
     * Completa um campo com sugestões baseadas em um texto informado,
     * buscando em todas as tabelas que possuem o campo especificado.
     *
     * @param string $campo Nome do campo a ser pesquisado.
     * @return void (imprime JSON com [{chave, valor}])
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
                $valor[] = array('chave' => 0, 'valor' => $this->formatavalorexibir($lin['VALOR'], 'varchar', true));
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
}
