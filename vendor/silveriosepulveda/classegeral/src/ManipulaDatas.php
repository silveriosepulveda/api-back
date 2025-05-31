<?php

namespace ClasseGeral;

/**
 * Classe utilitária para manipulação de datas (ex: formatação, cálculo de diferença).
 *
 * Métodos implementados em Funcoes.php.
 */
class ManipulaDatas
{

    /**
     * Converte uma data para o formato por extenso.
     *
     * @param string $data Data a ser convertida
     * @return string Data por extenso
     */
    public function dataExtenso($data)
    {
// leitura das datas
        $temp = explode('/', $data);
        if (sizeof($temp) < 2) {
            return false;
        } else {
            $dia = intval($temp[0]);
            $mes = intval($temp[1]);
            $ano = $temp[2];

            $meses = $this->arraymeses();
            $mes_extenso = $meses[$mes];
            return $dia . ' de ' . $mes_extenso . ' de ' . $ano;
        }
    }

    /**
     * Calcula a diferença em dias entre duas datas.
     *
     * @param string $data_inicial Data inicial
     * @param string $data_final Data final
     * @param string $tipo Tipo de entrada das datas ('base' para Y-m-d ou 'tela' para d/m/Y)
     * @return int|bool Diferença em dias ou false em caso de erro
     */
    public function diferencaEntreDatas($data_inicial, $data_final, $tipo = 'base')
    {
        if ($tipo == 'base') {
            $temp_i = explode('-', $data_inicial);
            $ano_i = intval($temp_i[0]);
            $mes_i = intval($temp_i[1]);
            $dia_i = intval($temp_i[2]);

            $temp_f = explode('-', $data_final);
            $ano_f = intval($temp_f[0]);
            $mes_f = intval($temp_f[1]);
            $dia_f = intval($temp_f[2]);
        } else if ($tipo == 'tela') {
            $temp_i = explode('/', $data_inicial);
            $ano_i = $temp_i[2];
            $mes_i = $temp_i[1];
            $dia_i = $temp_i[0];

            $temp_f = explode('/', $data_final);
            $ano_f = $temp_f[2];
            $mes_f = $temp_f[1];
            $dia_f = $temp_f[0];
        }

        $data_i = mktime(0, 0, 0, $mes_i, $dia_i, $ano_i);
        $data_f = mktime(0, 0, 0, $mes_f, $dia_f, $ano_f);
        $segundos_diferenca = $data_f - $data_i;
        $dias_diferenca = $segundos_diferenca / (60 * 60 * 24);
        return floor($dias_diferenca);
    }

    /**
     * Adiciona uma quantidade de meses a uma data.
     *
     * @param string $data Data de entrada
     * @param int $meses Quantidade de meses a serem adicionados
     * @param string $tipo Tipo de entrada da data ('base' para Y-m-d ou 'tela' para d/m/Y)
     * @param string $tipoRetorno Tipo de saída da data ('tela' para d/m/Y ou 'base' para Y-m-d)
     * @return string Data com os meses adicionados
     */
    public function somarMesesAData($data, $meses, $tipo = 'base', $tipoRetorno = 'tela')
    {
        if ($tipo == 'base') {
            $temp = explode('-', $data);
            $ano = $temp[0];
            $mes = $temp[1];
            $dia = $temp[2];
        } else if ($tipo == 'tela') {
            $temp = explode('/', $data);
            $ano = $temp[2];
            $mes = $temp[1];
            $dia = $temp[0];
        }

        if ($dia > 28) {
            if ($this->validadata($ano . '-' . ($mes + $meses) . '-' . $dia)) {
                $dataRetorno = mktime(0, 0, 0, ($mes + $meses), $dia, $ano);
            } else {
                //Verificando se e mes 12
                if ($meses >= 12) {
                    $anos = intval($meses / 12);
                    $meses = $meses % 12 > 0 ? ($meses % 12) + 1 : 1;

                    if ($this->validadata(($ano + $anos) . '-' . $meses . '-' . $dia)) {
                        $tempU = array(($ano + $anos), $meses, $dia);
                    } else {
                        $tempU = explode('-', $this->ultimoDiaMes(($ano + $anos) . '-' . $meses . '-1'));
                    }
                } else {
                    $tempU = explode('-', $this->ultimoDiaMes($ano . '-' . ($mes + $meses) . '-1'));
                }

                $dataRetorno = mktime(0, 0, 0, $tempU[1], $tempU[2], $tempU[0]);
            }
        } else {
            $dataRetorno = mktime(0, 0, 0, ($mes + $meses), $dia, $ano);
        }

        return $tipoRetorno == 'tela' ? date('d/m/Y', $dataRetorno) : date('Y-m-d', $dataRetorno);

    }

    /**
     * Adiciona uma quantidade de dias a uma data.
     *
     * @param string $data Data de entrada
     * @param int $dias Quantidade de dias a serem adicionados
     * @param string $tipoEntrada Tipo de entrada da data ('base' para Y-m-d ou 'tela' para d/m/Y)
     * @param string $tipoSaida Tipo de saída da data ('tela' para d/m/Y ou 'base' para Y-m-d)
     * @return string Data com os dias adicionados
     */
    public function somarDiasAData($data, $dias, $tipoEntrada = 'base', $tipoSaida = 'tela')
    {
        $data = str_replace("'", '', $data);

        if ($tipoEntrada == 'base') {
            $temp = explode('-', $data);
            $ano = $temp[0];
            $mes = $temp[1];
            $dia = $temp[2];
        } else if ($tipoEntrada == 'tela') {
            $temp = explode('/', $data);
            $ano = $temp[2];
            $mes = $temp[1];
            $dia = $temp[0];
        }

        //echo $dia .'--' . $mes . ' -- ' . $ano;
        $dataRetorno = mktime(0, 0, 0, $mes, $dia + $dias, $ano);

        if ($tipoSaida == 'tela') {
            return date('d/m/Y', $dataRetorno);
        } else if ($tipoSaida == 'base') {
            return date('Y-m-d', $dataRetorno);
        }
    }

    /**
     * Subtrai uma quantidade de dias de uma data.
     *
     * @param string $data Data de entrada
     * @param int $dias Quantidade de dias a serem subtraídos
     * @param string $tipoEntrada Tipo de entrada da data ('base' para Y-m-d ou 'tela' para d/m/Y)
     * @param string $tipoSaida Tipo de saída da data ('tela' para d/m/Y ou 'base' para Y-m-d)
     * @return string Data com os dias subtraídos
     */
    public function subtrairDiasDaData($data, $dias, $tipoEntrada = 'base', $tipoSaida = 'tela')
    {
        $data = str_replace("'", '', $data);

        if ($tipoEntrada == 'base') {
            $temp = explode('-', $data);
            $ano = $temp[0];
            $mes = $temp[1];
            $dia = $temp[2];
        } else if ($tipoEntrada == 'tela') {
            $temp = explode('/', $data);
            $ano = $temp[2];
            $mes = $temp[1];
            $dia = $temp[0];
        }

        //echo $dia .'--' . $mes . ' -- ' . $ano;
        $dataRetorno = mktime(0, 0, 0, $mes, $dia - $dias, $ano);

        if ($tipoSaida == 'tela') {
            return date('d/m/Y', $dataRetorno);
        } else if ($tipoSaida == 'base') {
            return date('Y-m-d', $dataRetorno);
        }
    }

    /**
     * Retorna um array com os meses do ano.
     *
     * @param int $mes Número do mês (1 a 12) para retorno do nome específico, ou 0 para retornar todos os meses
     * @return array|mixed Array com os nomes dos meses ou nome do mês específico
     */
    public function arraymeses($mes = 0)
    {
        $meses = array(
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro'
        );
        if ($mes > 0) {
            return $meses[$mes];
        } else {
            return $meses;
        }
    }

    /**
     * Retorna um array com os dias da semana.
     *
     * @return array Array com os nomes dos dias da semana
     */
    public function arraydiassemana()
    {
        $dias_semana = array(
            0 => 'Domingo',
            1 => 'Segunda',
            2 => 'Terça',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sábado'
        );
        return $dias_semana;
    }

    /**
     * Retorna o dia da semana para uma determinada data.
     *
     * @param string $data Data a ser verificada
     * @param string $tipo Tipo de entrada da data ('tela' para d/m/Y ou 'base' para Y-m-d)
     * @return array Array contendo a chave do dia da semana e o nome do dia
     */
    public function diadasemana($data, $tipo = 'tela')
    {

        if ($tipo == 'base') {
            $temp = explode('-', $data);
            $ano = $temp[0];
            $mes = $temp[1];
            $dia = $temp[2];
        } else if ($tipo == 'tela') {
            $temp = explode('/', $data);
            $ano = $temp[2];
            $mes = $temp[1];
            $dia = $temp[0];
        }

        $chave = date("w", mktime(0, 0, 0, $mes, $dia, $ano));
        $dias = $this->arraydiassemana();


        $dia = $dias[$chave];
        return array('chave_dia' => $chave, 'dia' => $dia);
    }

    /**
     * Retorna um array com os dias de um mês específico de um ano.
     *
     * @param int $chave_mes Número do mês (1 a 12)
     * @param string $ano Ano desejado (se não informado, será considerado o ano atual)
     * @return array Array contendo o ano, o nome do mês e os dias do mês
     */
    public function arraydiasmes($chave_mes, $ano = '')
    {
        date_default_timezone_set('America/Sao_Paulo');
        $meses = $this->arraymeses();
        $dias_semana = $this->arraydiassemana();

        $ano = $ano === '' ? date('Y') : $ano;
        $retorno['ano'] = $ano;
        $retorno['meses'][$chave_mes]['nome_mes'] = $meses[$chave_mes];

        for ($dia = 1; $dia <= 31; $dia++) {
            if (checkdate($chave_mes, $dia, $ano)) {
                $chave_dia_semana = date('w', mktime(0, 0, 0, $chave_mes, $dia, $ano));
                $retorno['meses'][$chave_mes]['dias'][$dia] = $dias_semana[$chave_dia_semana];
            }
        }
        return $retorno;
    }

    /**
     * Retorna um array com os dias de todos os meses de um ano.
     *
     * @param string $ano Ano desejado (se não informado, será considerado o ano atual)
     * @return array Array contendo o ano, os meses e os dias de cada mês
     */
    public function arraydiasmeses($ano = '')
    {
        date_default_timezone_set('America/Sao_Paulo');
        $ano = $ano === '' ? date('Y') : $ano;
        $retorno = array();
        $retorno['ano'] = $ano;
        $meses = $this->arraymeses();
        $dias_semana = $this->arraydiassemana();

        for ($chave_mes = 1; $chave_mes <= 12; $chave_mes++) {
            $retorno['meses'][$chave_mes]['nome_mes'] = $meses[$chave_mes];
            for ($dia = 1; $dia <= 31; $dia++) {
                if (checkdate($chave_mes, $dia, $ano)) {
                    $chave_dia_semana = date('w', mktime(0, 0, 0, $chave_mes, $dia, $ano));
                    $retorno['meses'][$chave_mes]['dias'][$dia]['chave_dia_semana'] = $chave_dia_semana;
                    $retorno['meses'][$chave_mes]['dias'][$dia]['dia_semana'] = $dias_semana[$chave_dia_semana];
                }
            }
        }
        return $retorno;
    }

    /**
     * Valida uma data verificando se é uma data real no calendário.
     *
     * @param string $data Data a ser validada
     * @param string $origem Origem da data ('base' para Y-m-d ou 'tela' para d/m/Y)
     * @return bool Resultado da validação
     */
    public function validadata($data, $origem = 'base')
    {
        date_default_timezone_set('America/Sao_Paulo');
        if ($data != '') {
            if ($origem === 'base') {
                $temp = explode('-', $data);
                $d = $temp[2];
                $m = $temp[1];
                $a = $temp[0];
            } else if ($origem === 'tela') {
                $temp = explode('/', $data);
                $d = $temp[0];
                $m = $temp[1];
                $a = $temp[2];
            }

            return checkdate($m, $d, $a);
        } else {
            return false;
        }
    }

    /**
     * Retorna o primeiro dia de um mês a partir de uma data.
     *
     * @param string $data Data de referência
     * @param string $origem Origem da data ('base' para Y-m-d ou 'tela' para d/m/Y)
     * @param string $destino Formato de saída da data ('base' para Y-m-d ou 'tela' para d/m/Y)
     * @return string Primeiro dia do mês no formato desejado
     */
    public function primeiroDiaMes($data, $origem = 'base', $destino = 'base')
    {
        $data = str_replace("'", "", $data);
        if ($origem == 'base') {
            $temp = explode('-', $data);
            $ano = $temp[0];
            $mes = $temp[1];
            echo $ano . ' - ' . $mes . ' - ' . "\n";
        } else if ($origem == 'tela') {
            $temp = explode('/', $data);
            $ano = $temp[2];
            $mes = $temp[1];
        }


        return $destino == 'tela' ? "01/$mes/$ano" : $ano . '-' . $mes . '-01';
    }

    /**
     * Retorna o último dia de um mês a partir de uma data.
     *
     * @param string $data Data de referência
     * @param string $origem Origem da data ('base' para Y-m-d ou 'tela' para d/m/Y)
     * @param string $destino Formato de saída da data ('base' para Y-m-d ou 'tela' para d/m/Y)
     * @return string Último dia do mês no formato desejado
     */
    public function ultimoDiaMes($data, $origem = 'base', $destino = 'base')
    {
        $data = str_replace("'", "", $data);

        if ($origem == 'base') {
            $temp = explode('-', $data);
            $ano = $temp[0];
            $mes = $temp[1];
            $dia = $temp[2];
        } else if ($origem == 'tela') {
            $temp = explode('/', $data);
            $ano = $temp[2];
            $mes = $temp[1];
            $dia = $temp[0];
        }

        $c = 31;
        $dataRetorno = 'va';

        while ($c >= 28) {
            //echo $c . '-' . $mes . '-' . $ano . ' ------ ';
            if ($this->validadata($ano . '-' . $mes . '-' . $c)) {
                $dataRetorno = mktime(0, 0, 0, $mes, $c, $ano);
                return $destino == 'base' ? date('Y-m-d', $dataRetorno) : date('d/m/Y', $dataRetorno);
            }
            $c--;
        }
    }

    /**
     * Retorna o número da semana de uma data.
     *
     * @param string $data Data de referência
     * @param string $origem Origem da data ('base' para Y-m-d ou 'tela' para d/m/Y)
     * @param string $destino Formato de saída da semana ('base' para número da semana ou 'tela' para intervalo de datas)
     * @return mixed Número da semana ou intervalo de datas
     */
    public function numeroSemana($data, $origem = 'base', $destino = 'base')
    {
        $data = str_replace("'", "", $data);

        if ($origem == 'base') {
            $temp = explode('-', $data);
            $ano = $temp[0];
            $mes = $temp[1];
            $dia = $temp[2];
        } else if ($origem == 'tela') {
            $temp = explode('/', $data);
            $ano = $temp[2];
            $mes = $temp[1];
            $dia = $temp[0];
        }

        $date = mktime(0, 0, 0, $mes, $dia, $ano);
        return (int)date('W', $date);
    }

    /**
     * Retorna um array com todas as datas em um intervalo entre duas datas.
     *
     * @param string $dataInicial Data inicial do intervalo
     * @param string $dataFinal Data final do intervalo
     * @param string $tipoEntrada Tipo de entrada das datas ('base' para Y-m-d ou 'tela' para d/m/Y)
     * @param string $tipoSaida Tipo de saída das datas ('tela' para d/m/Y ou 'base' para Y-m-d)
     * @return array Array com as datas do intervalo
     */
    public function arrayDatasIntervalo($dataInicial, $dataFinal, $tipoEntrada = 'base', $tipoSaida = 'tela')
    {
        $diferenca = $this->diferencaEntreDatas($dataInicial, $dataFinal, $tipoEntrada);

        $retorno = [];

        for ($i = 0; $i <= $diferenca; $i++) {

            $retorno[] = $this->somarDiasAData($dataInicial, $i, $tipoEntrada, $tipoSaida);
        }

        return $retorno;

    }


}
