<?php

namespace ClasseGeral;

/**
 * Classe utilitária para manipulação de documentos (ex: geração, validação).
 *
 * Métodos implementados em Funcoes.php.
 */
class ManipulaDocumentos
{
   /**
     * Valida um CNPJ verificando se é um número de CNPJ válido.
     *
     * @param string $cnpj CNPJ a ser validado
     * @return bool Resultado da validação
     */
    public function valida_cnpj($cnpj)
    {
        // Deixa o CNPJ com apenas n�meros
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        // Garante que o CNPJ � uma string
        $cnpj = (string)$cnpj;

        // O valor original
        $cnpj_original = $cnpj;

        // Captura os primeiros 12 n�meros do CNPJ
        $primeiros_numeros_cnpj = substr($cnpj, 0, 12);

        /**
         * Multiplica��o do CNPJ
         *
         * @param string $cnpj Os digitos do CNPJ
         * @param int $posicoes A posi��o que vai iniciar a regress�o
         * @return int O
         *
         */
        function multiplica_cnpj($cnpj, $posicao = 5)
        {
            // Vari�vel para o c�lculo
            $calculo = 0;

            // La�o para percorrer os item do cnpj
            for ($i = 0; $i < strlen($cnpj); $i++) {
                // C�lculo mais posi��o do CNPJ * a posi��o
                $calculo = $calculo + ($cnpj[$i] * $posicao);

                // Decrementa a posi��o a cada volta do la�o
                $posicao--;

                // Se a posi��o for menor que 2, ela se torna 9
                if ($posicao < 2) {
                    $posicao = 9;
                }
            }
            // Retorna o c�lculo
            return $calculo;
        }

        // Faz o primeiro c�lculo
        $primeiro_calculo = multiplica_cnpj($primeiros_numeros_cnpj);

        // Se o resto da divis�o entre o primeiro c�lculo e 11 for menor que 2, o primeiro
        // D�gito � zero (0), caso contr�rio � 11 - o resto da divis�o entre o c�lculo e 11
        $primeiro_digito = ($primeiro_calculo % 11) < 2 ? 0 : 11 - ($primeiro_calculo % 11);

        // Concatena o primeiro d�gito nos 12 primeiros n�meros do CNPJ
        // Agora temos 13 n�meros aqui
        $primeiros_numeros_cnpj .= $primeiro_digito;

        // O segundo c�lculo � a mesma coisa do primeiro, por�m, come�a na posi��o 6
        $segundo_calculo = multiplica_cnpj($primeiros_numeros_cnpj, 6);
        $segundo_digito = ($segundo_calculo % 11) < 2 ? 0 : 11 - ($segundo_calculo % 11);

        // Concatena o segundo d�gito ao CNPJ
        $cnpj = $primeiros_numeros_cnpj . $segundo_digito;

        // Verifica se o CNPJ gerado � id�ntico ao enviado
        if ($cnpj === $cnpj_original) {
            return true;
        } else {
            return false;
        }
    }
}
