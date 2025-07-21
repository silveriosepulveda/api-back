<?php

namespace ClasseGeral;

/**
 * Classe utilitária para manipulação de posições.
 *
 * Métodos implementados em Funcoes.php.
 */
class Posicoes extends ConClasseGeral
{
    /**
     * Altera a posição de um registro na tabela, podendo incrementar ou decrementar a posição de outros registros.
     *
     * @param string $tabela Nome da tabela
     * @param string $campo_chave Campo chave do registro a ser alterado
     * @param mixed $chave_primaria Valor da chave primária do registro
     * @param int $acrescentar Valor a ser acrescido (ou diminuído, se negativo) na posição atual
     * @param int $pos_atual Posição atual do registro
     * @param int $nova_pos Nova posição desejada para o registro
     * @param string $campo_ctp Campo da tabela que não deve ser alterado (opcional)
     * @param mixed $valor_ctp Valor do campo que não deve ser alterado (opcional)
     * @return int|bool 1 em caso de sucesso, ou false em caso de erro
     */
    public function alterarposicaonova($tabela, $campo_chave, $chave_primaria, $acrescentar, $pos_atual, $nova_pos, $campo_ctp = 'naousar', $valor_ctp = '')
    {
        $tabela = $this->nometabela($tabela);
        $campo_chave = $campo_chave != '' ? strtoupper($campo_chave) : $this->campochavetabela($tabela);

        $sql = "UPDATE $tabela SET POSICAO = $nova_pos WHERE $campo_chave = $chave_primaria";

        $operador = $acrescentar > 0 ? '+' : '-';

        $sql2 = "UPDATE $tabela SET POSICAO = POSICAO $operador 1";

        if ($acrescentar < 0) {
            $sql2 .= " WHERE POSICAO > $pos_atual AND POSICAO <= $nova_pos";
        } else if ($acrescentar > 0) {
            $sql2 .= " WHERE POSICAO >= $nova_pos AND POSICAO < $pos_atual";
        }

        if ($campo_ctp != 'naousar') {
            $sql_ctp = "SELECT $campo_ctp FROM $tabela WHERE $campo_chave = $chave_primaria";
            $res_ctp = $this->executasql($sql_ctp);
            $lin = $this->retornosql($res_ctp);
            $sql2 .= " AND $campo_ctp = $valor_ctp";
        }
        $sql2 .= " AND $campo_chave != $chave_primaria";

        $res = $this->executasql($sql);
        $res2 = $this->executasql($sql2);

        return 1;
    }

    /**
     * Fun��o que altera as posi��es na tabela indicada
     * @param type $tabela Tabela que sera realizada a alteracao
     * @param type $campo_chave
     * @param type $posicao_atual
     * @param type $nova_posicao
     * @param type $chave_tabela_primaria
     * @param type $valor_ctp
     * @return boolean
     */
    public function alterarposicao($tabela, $campo_chave = '', $posicao_atual = 0, $nova_posicao = 0, $chave_tabela_primaria = '', $valor_ctp = 0)
    {
        $tabela = $this->nometabela($tabela);

        $campo_chave = $campo_chave = '' ? $this->campochavetabela($tabela) : $campo_chave;

        $chave_tabela_primaria = strtoupper((string)$chave_tabela_primaria);

        $chave_pos_atual = $this->pegarchavepelaposicao($tabela, $campo_chave, $posicao_atual, $chave_tabela_primaria, $valor_ctp);

        $chave_pos_nova = $this->pegarchavepelaposicao($tabela, $campo_chave, $nova_posicao, $chave_tabela_primaria, $valor_ctp);

        //Ponho a nova posicao no campo que tem a posi�ao atual
        $sql = "UPDATE $tabela SET POSICAO = $nova_posicao";
        $sql .= " WHERE $campo_chave = $chave_pos_atual";
        if ($valor_ctp > 0) {
            $sql .= " AND $chave_tabela_primaria = $valor_ctp";
        }

        //echo $sql . "\n";

        $res = $this->executasql($sql);
        //Ponho a posi�ao atual no campo que tem a nova posi�ao
        $sql1 = "UPDATE $tabela SET POSICAO = $posicao_atual";
        $sql1 .= " WHERE $campo_chave = $chave_pos_nova";
        if ($valor_ctp > 0) {
            $sql1 .= " AND $chave_tabela_primaria = $valor_ctp";
        }
        //echo $sql1 . "\n";

        $res1 = $this->executasql($sql1);
        return true;
    }

    /**
     * Retorna a chave de um registro a partir da sua posição na tabela.
     *
     * @param string $tabela Nome da tabela
     * @param string $campo_chave Campo chave da tabela
     * @param int $posicao Posição do registro
     * @param string $chave_tabela_primaria Chave da tabela primária (opcional)
     * @param mixed $valor_ctp Valor da chave da tabela primária (opcional)
     * @return mixed Valor da chave do registro
     */
    public function pegarchavepelaposicao($tabela, $campo_chave, $posicao, $chave_tabela_primaria = '', $valor_ctp = 0)
    {
        $tabela = strtoupper($tabela);
        $campo_chave = strtoupper($campo_chave);
        $chave_tabela_primaria = strtoupper($chave_tabela_primaria);


        $sql = "SELECT $campo_chave AS CHAVE FROM $tabela WHERE POSICAO = $posicao";
        if ($valor_ctp > 0)
            $sql .= " AND $chave_tabela_primaria = '$valor_ctp'";
//echo $sql;
        $res = $this->executasql($sql);
        $lin = $this->retornosql($res);
        return $lin['CHAVE'];
    }

    /**
     * Retorna a próxima posição disponível em uma tabela para um novo registro.
     *
     * @param string $tabela Nome da tabela
     * @param string $campo_tabela_primaria Campo da tabela primária (opcional)
     * @param string $chave_primaria Chave do registro (opcional)
     * @return int Próxima posição disponível
     */
    public function proximaposicao($tabela, $campo_tabela_primaria = '', $chave_primaria = 'naousar')
    {
        $tabela = strtoupper($tabela);
        $campo_tabela_primaria = strtoupper($campo_tabela_primaria);
        $campo_chave = $this->campochavetabela($tabela);
//Neste caso h� uma tabela primaria relacionada, tipo MENU_ITENS com MENUS
        if ($chave_primaria != 'naousar') {
            $sql = "SELECT COALESCE(MAX(POSICAO), 0) + 1 AS POSICAO FROM $tabela";
            $sql .= " WHERE $campo_tabela_primaria = '$chave_primaria'";
        } else {
            $sql = "SELECT COALESCE(MAX(POSICAO), 0) + 1 AS POSICAO FROM $tabela";
        }
//echo $sql;
        $res = $this->executasql($sql);

        $lin = $this->retornosql($res);
        return $lin['POSICAO'];
    }

    /**
     * Retorna a posição de um registro em uma tabela a partir da sua chave.
     *
     * @param string $tabela Nome da tabela
     * @param string $campo_chave Campo chave da tabela
     * @param mixed $chave Valor da chave do registro
     * @return int Posição do registro
     */
    public function pegarposicao($tabela, $campo_chave, $chave)
    {
        $tabela = strtoupper($tabela);
        $campo_chave = strtoupper($campo_chave);
        $sql = "SELECT POSICAO FROM $tabela WHERE $campo_chave = $chave";

        $res = $this->executasql($sql);
        $lin = $this->retornosql($res);
        return $lin['POSICAO'];
    }

    /**
     * Atualiza as posições dos registros em uma tabela após a exclusão de um registro.
     *
     * @param string $tabela Nome da tabela
     * @param int $posicao Posição do registro excluído
     * @param string $campo_tabela_primaria Campo da tabela primária (opcional)
     * @param mixed $valor_ctp Valor da chave da tabela primária (opcional)
     * @return bool
     */
    public function atualizarposicoesexclusao($tabela, $posicao, $campo_tabela_primaria = '', $valor_ctp = '')
    {
        $tabela = strtoupper($tabela);
        $campo_chave = $this->campochavetabela($tabela);
        $campo_tabela_primaria = strtoupper($campo_tabela_primaria);

        $sql = "UPDATE $tabela SET POSICAO = POSICAO - 1";
        $sql .= " WHERE $campo_chave > 0 AND POSICAO > $posicao";

        if ($valor_ctp != '')
            $sql .= " AND $campo_tabela_primaria = '$valor_ctp'";

        //echo $sql;
        $res = $this->executasql($sql);
        return true;
    }// ...existing code from Funcoes.php...
}
