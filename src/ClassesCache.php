<?php

namespace ClasseGeral;

/**
 * Classe responsável por gerenciar o cache de instâncias de classes
 * 
 * Centraliza toda a lógica de cache de classes para evitar múltiplas instanciações
 * e melhorar o desempenho da aplicação.
 */
class ClassesCache
{
    /**
     * Cache de instâncias de classes para evitar múltiplas instanciações
     * @var array
     */
    private array $instanceCache = [];

    /**
     * Instância cached da classe ConsultaDados
     * @var ConsultaDados|null
     */
    private ?ConsultaDados $consultaDados = null;

    /**
     * Instância cached da classe ManipulaDados
     * @var ManipulaDados|null
     */
    private ?ManipulaDados $manipulaDados = null;

    /**
     * Instância cached da classe TabelasInfo
     * @var TabelasInfo|null
     */
    private ?TabelasInfo $tabelasInfo = null;

    /**
     * Instância cached da classe Formatacoes
     * @var Formatacoes|null
     */
    private ?Formatacoes $formatacoes = null;

    /**
     * Instância cached da classe ManipulaSessao
     * @var ManipulaSessao|null
     */
    private ?ManipulaSessao $manipulaSessao = null;
     

    /**
     * Instância cached de classe Estruturas
     * @var Estruturas | null
     */
    private ?Estruturas $estruturas = null;

    /**
     * Instância cached de classe ManipulaValores
     * @var ManipulaValores | null
     */
    private ?ManipulaValores $manipulaValores = null;

    /** Instância cached de UploadSimples
     * @var UploadSimples | null
     */
    private ?UploadSimples $uploadSimples = null;

    /** Instância cached de GerenciaDiretorios
     * @var GerenciaDiretorios | null
     */
    private ?GerenciaDiretorios $gerenciaDiretorios = null;

    /** Instância cached de ManipulaStrings
     * @var ManipulaStrings | null
     */
    private ?ManipulaStrings $manipulaStrings = null;
    

    /**
     * Retorna uma instância cached da classe ConsultaDados
     * @return ConsultaDados
     */
    public function pegaConsultaDados(): ConsultaDados
    {
        if ($this->consultaDados === null) {
            $this->consultaDados = new ConsultaDados();
        }
        return $this->consultaDados;
    }

    /**
     * Retorna uma instância cached da classe ManipulaDados
     * @return ManipulaDados
     */
    public function pegaManipulaDados(): ManipulaDados
    {
        if ($this->manipulaDados === null) {
            $this->manipulaDados = new ManipulaDados();
        }
        return $this->manipulaDados;
    }

    /**
     * Retorna uma instância cached da classe TabelasInfo
     * @return TabelasInfo
     */
    public function pegaTabelasInfo(): TabelasInfo
    {
        if ($this->tabelasInfo === null) {
            $this->tabelasInfo = new TabelasInfo();
        }
        return $this->tabelasInfo;
    }

    /**
     * Retorna uma instância cached da classe Formatacoes
     * @return Formatacoes
     */
    public function pegaFormatacoes(): Formatacoes
    {
        if ($this->formatacoes === null) {
            $this->formatacoes = new Formatacoes();
        }
        return $this->formatacoes;
    }

    /**
     * Retorna uma instância cached da classe ManipulaSessao
     * @return ManipulaSessao
     */
    public function pegaManipulaSessao(): ManipulaSessao
    {
        if ($this->manipulaSessao === null) {
            $this->manipulaSessao = new ManipulaSessao();
        }
        return $this->manipulaSessao;
    }


    /** Retorna uma instância cached da classe Estruturas */
    public function pegaEstruturas() : Estruturas{
        if ($this->estruturas === null)
            $this->estruturas = new Estruturas();

        return $this->estruturas;
    }

    /** Retorna uma instância cached da classe ManipulaValores */
    public function pegaManipulaValores() : ManipulaValores{
        if ($this->manipulaValores === null)
            $this->manipulaValores = new ManipulaValores();

        return $this->manipulaValores;
    }

    /** Retorna uma instância cached de UploadSimples */
    public function pegaUploadSimples() : UploadSimples{
        if ($this->uploadSimples === null)
            $this->uploadSimples = new UploadSimples();

        return $this->uploadSimples;
    }

    /** Retorna uma instância cached de GerenciaDiretorios */
    public function pegaGerenciaDiretorios() : GerenciaDiretorios{
        if ($this->gerenciaDiretorios === null)
            $this->gerenciaDiretorios = new GerenciaDiretorios();

        return $this->gerenciaDiretorios;
    }

    /** Retorna uma instância cached de ManipulaStrings */
    public function pegaManipulaStrings() : ManipulaStrings{
        if ($this->manipulaStrings === null)
            $this->manipulaStrings = new ManipulaStrings();

        return $this->manipulaStrings;
    }

    /**
     * Método genérico para cache de instâncias por nome de classe
     * @param string $className Nome da classe
     * @return mixed Instância da classe
     */
    public function pegaClasseCache(string $className): mixed
    {
        if (!isset($this->instanceCache[$className])) {
            // Verificar se precisa do namespace
            $fullClassName = class_exists($className) ? $className : "\\ClasseGeral\\$className";
            $this->instanceCache[$className] = new $fullClassName();
        }
        return $this->instanceCache[$className];
    }

    /**
     * Cria uma instância de uma classe a partir do nome da classe com cache.
     * 
     * @param string $classe Nome da classe a ser instanciada.
     * @param callable $pegaCaminhoApi Callback para obter o caminho da API
     * @return mixed Instância da classe ou false em caso de falha.
     */
    public function criaClasseTabela(string $classe, callable $pegaCaminhoApi)
    {
        // Chave única para o cache incluindo o prefixo 'tabela_' para evitar conflitos
        $chaveCache = 'tabela_' . $classe;
        
        // Verificar se já existe no cache
        if (isset($this->instanceCache[$chaveCache])) {
            return $this->instanceCache[$chaveCache];
        }
        
        // Se não está no cache, verificar se o arquivo existe
        $caminhoApiLocal = $pegaCaminhoApi();
        $arquivo = $caminhoApiLocal . 'api/backLocal/classes/' . $classe . '.class.php';
        
        if (is_file($arquivo)) {
            require_once $arquivo;
            
            // Criar a instância e armazenar no cache
            $instancia = new $classe();
            $this->instanceCache[$chaveCache] = $instancia;
            
            return $instancia;
        } else {
            // Armazenar false no cache para evitar verificações repetidas de arquivos inexistentes
            $this->instanceCache[$chaveCache] = false;
            return false;
        }
    }

    /**
     * Limpa o cache de instâncias (útil para testes ou liberação de memória)
     * @param string|null $className Se especificado, limpa apenas essa classe do cache
     * @return void
     */
    public function clearInstanceCache(?string $className = null): void
    {
        if ($className !== null) {
            // Limpar classe específica do cache genérico
            unset($this->instanceCache[$className]);
            
            // Limpar também classe de tabela se especificada com prefixo
            $chaveTabelaCache = 'tabela_' . $className;
            unset($this->instanceCache[$chaveTabelaCache]);
            
            // Limpar também as instâncias específicas se for o caso
            if ($className === 'ManipulaDados') {
                $this->manipulaDados = null;
            } elseif ($className === 'TabelasInfo') {
                $this->tabelasInfo = null;
            } elseif ($className === 'Formatacoes') {
                $this->formatacoes = null;
            } elseif ($className === 'ConsultaDados') {
                $this->consultaDados = null;
            } elseif ($className === 'ManipulaSessao') {
                $this->manipulaSessao = null;
            }elseif($className === 'Estruturas')
                $this->estruturas = null;

        } else {
            // Limpar todo o cache
            $this->instanceCache = [];
            $this->manipulaDados = null;
            $this->tabelasInfo = null;
            $this->formatacoes = null;
            $this->consultaDados = null;
            $this->manipulaSessao = null;
            $this->estruturas = null;
        }
    }

    /**
     * Limpa apenas o cache de classes de tabela
     * @param string|null $classe Se especificado, limpa apenas essa classe de tabela do cache
     * @return void
     */
    public function clearTabelaCache(?string $classe = null): void
    {
        if ($classe !== null) {
            $chaveCache = 'tabela_' . $classe;
            unset($this->instanceCache[$chaveCache]);
        } else {
            // Limpar apenas as classes de tabela (que têm prefixo 'tabela_')
            foreach (array_keys($this->instanceCache) as $chave) {
                if (str_starts_with($chave, 'tabela_')) {
                    unset($this->instanceCache[$chave]);
                }
            }
        }
    }

    /**
     * Verifica se uma função existe em uma classe e, se necessário, inclui o arquivo da classe.
     * 
     * @param mixed $classe Classe ou nome da classe a ser verificada.
     * @param string $funcao Nome da função a ser verificada.
     * @param callable $pegaCaminhoApi Callback para obter o caminho da API
     * @return bool Retorna true se a função existe, caso contrário, false.
     */
    public function criaFuncaoClasse($classe, string $funcao, callable $pegaCaminhoApi): bool
    {
        if (gettype($classe) == 'string' && !class_exists($classe)) {
            $arquivoClasse = $pegaCaminhoApi() . 'api/backLocal/classes/' . $classe . '.class.php';
            if (file_exists($arquivoClasse)) {
                require_once $arquivoClasse;
                $classe = $this->criaClasseTabela($classe, $pegaCaminhoApi); // new $classe();
            }
        }

        if ($classe != '' && method_exists($classe, $funcao)) {
            return true;
        } else {
            return false;
        }
    }
}

