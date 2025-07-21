<?php

namespace ClasseGeral;

/**
 * Classe utilitária para manipulação de strings (ex: abreviação de nomes, remoção de acentos).
 */
class ManipulaStrings
{
    /**
     * Abrevia nomes compostos, mantendo o primeiro e último nome completos e abreviando os do meio.
     * Exemplo: "João da Silva Pereira" => "João S. Pereira"
     * @param string $nome Nome completo a ser abreviado
     * @return string Nome abreviado
     */
    public function abrevia($nome)
    {
        $nomeOriginal = $nome;
        $nome = explode(" ", $nome); // cria o array $nome com as partes da string

        $num = count($nome); // conta quantas partes o nome tem
        if ($num == 2) { // se tiver somente nomes, não abrevia
            return $nomeOriginal; // retorna nome original
        } else { // pelo contrário executa a função
            $count = 0;
            $novo_nome = ''; // variavel que irá concatenar as partes do nome
            foreach ($nome as $var) { // loop no array
                if ($count == 0) {
                    $novo_nome .= $var . ' ';
                } // mostra primeiro nome

                $count++; // acrescenta +1 na no contador
                /* agora só irá abreviar os nomes do meio, com a condição abaixo, porém, se for algum contido no array de preposições mais comuns, não irá abreviar */
                if (($count >= 2) && ($count < $num)) {
                    $array = array('do', 'Do', 'DO', 'da', 'Da', 'DA', 'de', 'De', 'DE', 'dos', 'Dos', 'DOS', 'das', 'Das', 'DAS');
                    if (in_array($var, $array)) {
                        //$novo_nome .= $var . ' '; // não abreviou
                    } // fim if array
                    else {
                        $novo_nome .= substr($var, 0, 1) . '. '; // abreviou
                    } // fim else
                } // fim if nomes do meio
                if ($count == $num) {
                    $novo_nome .= $var;
                } // mostra último nome, quando o contador (count) alcançar o número total de valores do array $nome
            } // fim foreach
            return $novo_nome; // retorna novo nome
        } // fim else
    } // fim da função

    /**
     * Remove acentos de uma string e, opcionalmente, troca espaços por traço.
     * @param string $texto Texto de entrada
     * @param bool $trocarespacoportraco Se deve trocar espaços por traço
     * @return string Texto sem acentos
     */
    public function limparacentos($texto, $trocarespacoportraco = false)
    {
        if ($texto != '') {
            return preg_replace(array(
                "/(á|à|ã|â|ä)/",
                "/(Á|À|Ã|Â|Ä)/",
                "/(é|è|ê|ë)/",
                "/(É|È|Ê|Ë)/",
                "/(í|ì|î|ï)/",
                "/(Í|Ì|Î|Ï)/",
                "/(ó|ò|õ|ô|ö)/",
                "/(Ó|Ò|Õ|Ô|Ö)/",
                "/(ú|ù|û|ü)/",
                "/(Ú|Ù|Û|Ü)/",
                "/(ñ)/",
                "/(Ñ)/",
                "/(ç)/",
                "/(Ç)/",
                "/(º)/"
            ), explode(" ", "a A e E i I o O u U n N c C  "), $texto);
            if ($trocarespacoportraco) {
                $retorno = preg_replace("/\s+/", "-", $retorno);
            }
        } else {
            $retorno = '';
        }
        return $retorno;
    }

    public function tiraMascaras($texto, $inserir = '')
    {
        if ($texto != '') {
            $array = array('(', ')', '-', '/', '.', ',');
            $retorno = str_replace($array, $inserir, $texto);
        } else {
            $retorno = '';
        }
        return $retorno;
    }

    public function adicionaCarecateres($texto, $tamanho, $caracterAcrescentar, $alinhamento = 'esquerdo')
    {
        $texto = trim($texto);
        $qtdAcrescentar = $tamanho - strlen($texto);

        $textoAcrescentar = '';

        if ($alinhamento == 'centro'){
            $qtdAcrescentar = (int) ($tamanho - strlen($texto) ) / 2;
        }

        for ($i = 0; $i < $qtdAcrescentar; $i++) {
            $textoAcrescentar .= $caracterAcrescentar;
        }

        if ($alinhamento == 'direito'){
            $retorno = $textoAcrescentar . $texto;
        }else if ($alinhamento == 'esquerdo' ){
            $retorno = $texto . $textoAcrescentar;
        }else if ($alinhamento == 'centro'){
            $retorno = $textoAcrescentar . $texto . $textoAcrescentar;
        }

        return $retorno;
    }

    public function trechoTexto($texto, $limite, $para = ".", $pontos = "...")
    {
        $texto = strip_tags($texto);
        if (strlen($texto) <= $limite) return $texto;

        if (false !== ($pontodeparada = strpos($texto, $para, $limite))) {
            if ($pontodeparada < strlen($texto) - 1) {
                $texto = substr($texto, 0, $pontodeparada) . $pontos;
            }
        }
        return trim($texto);
    }


}
