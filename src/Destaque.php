<?php

namespace ClasseGeral;

/**
 * Classe utilitária para manipulação de destaques.
 *
 * Métodos implementados em Funcoes.php.
 */
class Destaque extends ConClasseGeral
{
     public function __construct()
    {
        include_once 'bancodedados/conexao.php';
    }

    /**
     * Altera a posição de destaque de um registro em uma tabela.
     *
     * @param string $tabela Nome da tabela
     * @param string $campo_chave Campo chave da tabela
     * @param mixed $chave Valor da chave do registro
     * @param string $valor 'true' para destacar, 'false' para remover destaque
     * @param int $posicao Nova posição do registro (opcional)
     * @return int Nova posição do registro ou 0 em caso de erro
     */
    public function poremdestaque($tabela, $campo_chave, $chave, $valor, $posicao = 0)
    {
        $tabela = strtoupper($tabela);
        $campo_chave = strtoupper($campo_chave);

        $ativo = $valor == 'true' ? 'D' : 'S';

        $pos = new posicoes;

        if ($ativo == 'D') {
            $posicao = $pos->proximaposicao($tabela);
            $sql = "UPDATE $tabela SET DISPONIVEL = '$ativo', POSICAO = $posicao WHERE $campo_chave = $chave";
            $res = $this->executasql($sql);
            if ($res) {
                return $posicao;
            } else {
                return 0;
            }
        } else if ($ativo == 'S') {
            if ($pos->atualizarposicoesexclusao($tabela, $posicao, 'DISPONIVEL', 'D')) {
                $sql = "UPDATE $tabela SET DISPONIVEL = '$ativo', POSICAO = 0 WHERE $campo_chave = $chave";
                $res = $this->executasql($sql);
                if ($res) {
                    return 0;
                } else {
                    return 1;
                }
            } else {
                return 0;
            }
        }
    }

    /**
     * Retorna a quantidade de registros em destaque em uma tabela.
     *
     * @param string $tabela Nome da tabela
     * @return int Quantidade de registros em destaque
     */
    public function quantidadedestaque($tabela)
    {
        $tabela = strtoupper($tabela);
        $sql = "SELECT COUNT(POSICAO) AS QUANTIDADE FROM $tabela WHERE DISPONIVEL = 'D'";
        $res = $this->executasql($sql);
        $lin = $this->retornosql($res);
        $quantidade = $lin['QUANTIDADE'];
        return $quantidade;
    }// ...existing code from Funcoes.php...
}
