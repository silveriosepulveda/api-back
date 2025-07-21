<?php

namespace ClasseGeral;

/**
 * Classe utilitária para geração e manipulação de combinações.
 *
 * Métodos implementados em Funcoes.php.
 */
class Combinacoes
{
     /**
     * Funcao que transforma um array em outro array com as combinacoes possiveis
     * do array de entrada
     * @param array $array variavel com os valores a serem combinados
     * @param string $separador Caracter que separara os valores nas combinacoes de retorno
     * @return array Variavel que retornara um array com as combinacoes
     */
    public function combinacoesPossiveis($array, $separador = '-')
    {
        $retorno = array();
        $tam = sizeof($array);
        // Generate all permutations of the array
        $permutations = Combinacoes::getPermutations($array, $tam);
        foreach ($permutations as $p) {
            $retorno[] = join($separador, $p);
        }
        return $retorno;
    }

    /**
     * Gera todas as permutações possíveis de um array.
     * @param array $array Array de entrada.
     * @param int $size Tamanho das permutações.
     * @return array Permutações possíveis.
     */
    public static function getPermutations($array, $size)
    {
        if ($size === 1) {
            $result = [];
            foreach ($array as $item) {
                $result[] = [$item];
            }
            return $result;
        }

        $result = [];
        foreach ($array as $key => $item) {
            $remaining = $array;
            unset($remaining[$key]);
            $perms = self::getPermutations(array_values($remaining), $size - 1);
            foreach ($perms as $perm) {
                array_unshift($perm, $item);
                $result[] = $perm;
            }
        }
        return $result;
    }
}
