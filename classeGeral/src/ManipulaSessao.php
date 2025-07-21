<?php

namespace ClasseGeral;

/**
 * Classe utilitária para manipulação de sessões PHP.
 * Permite setar, pegar e excluir variáveis de sessão de forma hierárquica.
 */
class ManipulaSessao
{
    /**
     * Exclui uma variável da sessão, podendo ser hierárquica (ex: 'usuario', 'usuario,nome').
     * @param string $var Nome da variável ou caminho hierárquico separado por vírgula.
     */
    public function excluir($var)
    {
       // @session_start();
        $id = session_id();
        $c = explode(',', $var);
        $tamanho = sizeof($c);
        if ($var === 'tudo') {
            unset($_SESSION[$id]);
        } else if ($tamanho === 1 && isset($_SESSION[$id][$c[0]])) {
            unset($_SESSION[$id][$c[0]]);
        } else if ($tamanho === 2 && isset($_SESSION[$id][$c[0]][$c[1]])) {
            unset($_SESSION[$id][$c[0]][$c[1]]);
        } else if ($tamanho === 3 && isset($_SESSION[$id][$c[0]][$c[1]][$c[2]])) {
            unset($_SESSION[$id][$c[0]][$c[1]][$c[2]]);
        } else if ($tamanho === 4 && isset($_SESSION[$id][$c[0]][$c[1]][$c[2]][$c[3]])) {
            unset($_SESSION[$id][$c[0]][$c[1]][$c[2]][$c[3]]);
        } else if ($tamanho === 5 && isset($_SESSION[$id][$c[0]][$c[1]][$c[2]][$c[3]][$c[4]])) {
            unset($_SESSION[$id][$c[0]][$c[1]][$c[2]][$c[3]][$c[4]]);
        }
    }

    /**
     * Recupera o valor de uma variável da sessão, podendo ser hierárquica.
     * @param string $var Nome da variável ou caminho hierárquico separado por vírgula.
     * @return mixed Valor armazenado na sessão ou string vazia se não existir.
     */
    public function pegar($var)
    {
       // @session_start();
        $id = session_id();
        $c = explode(',', $var);
        $tamanho = sizeof($c);
        $retorno = '';
        if ($tamanho === 1 && isset($_SESSION[$id][$c[0]])) {
            $retorno = $_SESSION[$id][$c[0]];
        } else if ($tamanho === 2 && isset($_SESSION[$id][$c[0]][$c[1]])) {
            $retorno = $_SESSION[$id][$c[0]][$c[1]];
        } else if ($tamanho === 3 && isset($_SESSION[$id][$c[0]][$c[1]][$c[2]])) {
            $retorno = $_SESSION[$id][$c[0]][$c[1]][$c[2]];
        } else if ($tamanho === 4 && isset($_SESSION[$id][$c[0]][$c[1]][$c[2]][$c[3]])) {
            $retorno = $_SESSION[$id][$c[0]][$c[1]][$c[2]][$c[3]];
        } else if ($tamanho === 5 && isset($_SESSION[$id][$c[0]][$c[1]][$c[2]][$c[3]][$c[4]])) {
            $retorno = $_SESSION[$id][$c[0]][$c[1]][$c[2]][$c[3]][$c[4]];
        }
        return $retorno;
    }

    /**
     * Seta o valor de uma variável na sessão, podendo ser hierárquica.
     * @param string $var Nome da variável ou caminho hierárquico separado por vírgula.
     * @param mixed $valor Valor a ser armazenado.
     */
    public function setar($var, $valor)
    {
     //   @session_start();
        $id = session_id();
        $c = explode(',', $var);
        $tamanho = sizeof($c);
        if ($tamanho === 1) {
            $_SESSION[$id][$c[0]] = $valor;
        } else if ($tamanho === 2) {
            $_SESSION[$id][$c[0]][$c[1]] = $valor;
        } else if ($tamanho === 3) {
            $_SESSION[$id][$c[0]][$c[1]][$c[2]] = $valor;
        } else if ($tamanho === 4) {
            $_SESSION[$id][$c[0]][$c[1]][$c[2]][$c[3]] = $valor;
        } else if ($tamanho === 5) {
            $_SESSION[$id][$c[0]][$c[1]][$c[2]][$c[3]][$c[4]] = $valor;
        }
    }
}
