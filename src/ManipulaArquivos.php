<?php

namespace ClasseGeral;

/**
 * Classe utilitária para manipulação de arquivos (ex: leitura, escrita, upload).
 *
 * Métodos implementados em Funcoes.php.
 */
class ManipulaArquivos
{
     /**
     * Lê o conteúdo de um arquivo e retorna como uma string.
     *
     * @param string $caminho Caminho do arquivo a ser lido
     * @param bool $temaspas Se deve adicionar aspas ao redor do conteúdo
     * @return string Conteúdo do arquivo
     */
    public function arquivoparavariavel($caminho, $temaspas = true)
    {
        $retorno = $temaspas ? '"' : '';
        if (is_file($caminho)) {
            $temp = fopen($caminho, 'r');
            while (($linha = fgets($temp, 4096)) != false) {
                $retorno .= $linha;
            }
        }
        $retorno .= $temaspas ? '"' : '';
        return $retorno;
    }
}
