<?php

namespace ClasseGeral;

/**
 * Classe utilitária para gerenciamento de diretórios (criação, listagem, remoção).
 */
class GerenciaDiretorios
{
    /**
     * Cria diretórios recursivamente a partir de um caminho informado.
     * @param string $caminho Caminho do diretório a ser criado
     * @return bool Sucesso da operação
     */
    public function criadiretorio($caminho)
    {
        ini_set('display_errors', 1);
        $temp = explode('/', $caminho);
        $diratual = '';
        foreach ($temp as $key => $dir) {
            //echo substr(trim($dir), strlen(trim($dir)) - 1, 1) . "\n";
            $diratual .= $dir . '/';
            if (!is_dir($diratual)) {
                mkdir($diratual, 0777, true);
            }
        }
        return true;
    }

    /**
     * Lista arquivos de um diretório filtrando por extensões conhecidas.
     * @param string $caminho Caminho do diretório
     * @return array Lista de arquivos encontrados
     */
    public function listaarquivosdiretorio($caminho)
    {
        /* Apaga o diret�rio e todo o seu cont�do */
        $extencoes = array('rar', 'jpg', 'jpeg', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'pdf', 'exe');
        $dir = $caminho;
        $dirt = scandir($dir);
        $retorno = array();
//Apagando os arquivos da pasta raiz
        foreach ($dirt as $arq) {
            $arquivo = $dir . $arq;
            if (trim($arq != '.') && trim($arq != '..')) {
                $temp = explode('.', $arq);
                $ext = $temp[sizeof($temp) - 1];
                if (in_array($ext, $extencoes)) {
                    $retorno[] = $arq;
                }
            }
        }
        return ($retorno);
    }

    /**
     * Remove um diretório e todo o seu conteúdo.
     * @param string $caminho Caminho do diretório
     * @return void
     */
    public function apagadiretorio($caminho)
    {
        /* Apaga o diret�rio e todo o seu cont�do */
        $dir = $caminho;
        $dirt = scandir($dir);

//Apagando os arquivos da pasta raiz
        foreach ($dirt as $arq) {
            $arquivo = $dir . $arq;
            if (is_file($arquivo)) {
                unlink($arquivo);
            } else if (is_dir($arquivo) && trim($arq != '.') && trim($arq != '..')) {
                $dirtam = scandir($arquivo);
                foreach ($dirtam as $imgtam) {
                    $imagem = $arquivo . '/' . $imgtam;
                    if (is_file($imagem))
                        unlink($imagem);
                }
                rmdir($arquivo);
            }
        }
        rmdir($dir);
    }

}
