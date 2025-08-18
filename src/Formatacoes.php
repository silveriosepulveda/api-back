<?php

namespace ClasseGeral;
class Formatacoes extends \ClasseGeral\ClasseGeral {
    /**
     * Formata valores para exibição em um relatório, considerando as configurações da tabela.
     *
     * @param string $tabela Nome da tabela cujos valores serão formatados.
     * @param array $valores Valores a serem formatados.
     * @return array Valores formatados.
     */
    public function formatarValoresExibir($tabela, $valores)
    {
        $campos = array_change_key_case($this->campostabela($tabela), CASE_LOWER);

        foreach ($campos as $campo => $val) {
            if (isset($valores[$campo])) {
                $valores[$campo] = $this->formatavalorexibir($valores[$campo], $val['tipo']);
            }
        }
        return $valores;
    }

    /**
     * Formata um valor para exibição de acordo com seu tipo.
     *
     * @param mixed $valor Valor a ser formatado.
     * @param string $tipo Tipo do dado.
     * @param bool $htmlentitie (Opcional) Se deve ou não aplicar htmlentities no valor.
     * @return mixed Valor formatado para exibição.
     */
    public function formatavalorexibir($valor, $tipo, $htmlentitie = true)
    {
        $retorno = '';

        if ($tipo == 'int' || $tipo == 'bigint' || $tipo == 'tinyint') {
            $retorno = $valor != '' && $valor != null ? (int)$valor : $valor;

        } else if ($tipo == 'float' || $tipo == 'double' || $tipo == 'decimal' || $tipo == 'real') {
            if (sizeof(explode(',', $valor ?? '')) > 1) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            }
            $retorno = $valor != '' ? number_format($valor, 2, ',', '.') : '';

        } else if ($tipo == 'varchar' || $tipo == 'char' || $tipo == 'text') {
            $retorno = $valor != null && $valor != 'undefined' ? $valor : '';
        } else if ($tipo == 'urlYoutube') {
            $retorno = str_replace('watch?v=', 'embed/', $valor);
        } else if ($tipo == 'longtext' || $tipo == 'text') {

            if (substr(trim($valor), 0, 1) == '{') {
                $retorno = json_decode(preg_replace('/(\r\n)|\n|\r/', '\\n', $valor), true);
            } else {
                $retorno = $htmlentitie ? htmlentities($valor) : $valor;
                $retorno = str_replace("\'", "'", $valor);
            }
        } else if ($tipo == 'date') {

            if ($valor != '') {
                $temp = explode('/', $valor);
                if (sizeof($temp) > 1) {
                    $retorno = $valor;
                } else {
                    date_default_timezone_set('America/Sao_Paulo');

                    $retorno = date('d/m/Y', strtotime($valor));
                }
            } else {
                $retorno = '';
            }
        } else if ($tipo == 'time') {
            $retorno = substr($valor, 0, 5);
        } else if ($tipo == 'timestamp') {
            $retorno = $valor != '' ?
                $this->formatavalorexibir(explode(' ', $valor)[0], 'date') . ' ' . $this->formatavalorexibir(explode(' ', $valor)[1], 'time') : '';
        } else if ($tipo == 'varbinary') {
            $retorno = $valor;
        } else if ($tipo == 'json') {
            $retorno = json_decode(preg_replace('/(\r\n)|\n|\r/', '\\n', $valor), true);
            $retorno = gettype($retorno) == 'string' ? json_decode($retorno, true) : $retorno;
        } else {
            $retorno = '';
        }

        ini_set("display_errors", 1);
        return $retorno;
    }

    /**
     * Retorna um valor formatado para uso em uma query SQL, de acordo com seu tipo.
     *
     * @param string $tipo Tipo do dado.
     * @param mixed $valor Valor a ser formatado.
     * @param string $origem (Opcional) Origem do dado (consulta, inclusao, alteracao).
     * @param string $campo (Opcional) Nome do campo relacionado ao valor.
     * @return mixed Valor formatado.
     */
    public function retornavalorparasql($tipo, $valor, $origem = 'consulta', $campo = '')
    {
        if ($valor === 'undefined')
            $valor = null;

        if (($tipo == 'varchar' || $tipo == 'char') && !is_array($valor)) {
            $valor = "'" . trim(str_replace("'", "\'", $valor), '"') . "'";
        } else if ($tipo == 'longtext' || $tipo == 'text') {
            if ($valor != 'undefined') {
                $valor = stripslashes($valor);
                //Fazendo esta linha para salvar as ' dentro de '
                $valor = str_replace("'", "\'", $valor);
                $valor = "'" . $valor . "'";
            } else {
                $valor = 'null';
            }
        } else if ($tipo == 'float' || $tipo == 'decimal' || $tipo == 'real') {
            $valor = $this->configvalor($valor);
        } else if ($tipo == 'int') {
            if ($origem != 'consulta') {
                if ($valor === null || $valor === '') {
                    $valor = 'null';
                } else
                    $valor = (int)$valor;
            } else {
                if ($campo != '')
                    echo $valor . $this->q;

                $valor = ($valor != '' && (int)$valor >= 0) ? (int)$valor : '0';
            }
        } else if ($tipo == 'date') {
            if ($valor != '' && $valor != 'CURRENT_DATE' && $valor != 'undefined') {
                $d = explode('/', $valor);
                if (sizeof($d) > 1) {//Neste caso a data vem da tela
                    $valor = $d[2] . '-' . $d[1] . '-' . $d[0];
                    $valor = "'" . $valor . "'";
                } else {//Neste caso a data j� est� em formato de tabela
                    $valor = $valor;
                }
            } else if ($valor == '' || $valor == 'undefined') {
                $valor = 'null';
            }
        } else if ($tipo == 'time') {
            if (strtolower($valor) != 'CURRENT_TIME')
                $valor = "'" . $valor . "'";
        } else if ($tipo == 'timestamp') {

            if ($valor != '') {
                if ($valor == 'dataAtual') {
                    $valor = "'" . $this->pegaDataHora() . "'";
                } else {
                    $temp = explode(' ', $valor);
                    if (sizeof($temp) == 2) {
                        $data = $this->retornavalorparasql('date', $temp[0]);
                        $valor = "'" . str_replace("'", '', $data) . ' ' . $temp[1] . "'";
                    }
                }
            } else {
                $valor = 'null';
            }
        } else if ($tipo == 'json') {
            if (!is_array($valor)) {
                $valor = $valor != '' ? "JSON_QUOTE('" . $valor . "') " : 'null';
            } else {
                $valor = $valor != '' ? "JSON_QUOTE('" . json_encode($valor, JSON_UNESCAPED_UNICODE) . "')" : 'null';
            }
        }

        $valor_saida = $valor;
        return $valor_saida;
    }

    public function configvalor($valor): array|string
    {
        if ($valor == '' || $valor == 'undefined')
            $valor = 0;
        $temp = $valor;
        $temp = str_replace('.', '', $temp);
        $temp = str_replace(',', '.', $temp);
        return ($temp);
    }
}
