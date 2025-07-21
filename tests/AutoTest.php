<?php
// tests/AutoTest.php
// Script para testar automaticamente todas as classes e métodos do projeto com dados aleatórios

require_once __DIR__ . '/../vendor/autoload.php';

function randomString($length = 10) {
    return substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', 5)), 0, $length);
}

function randomInt() {
    return rand(0, 10000);
}

function randomFloat() {
    return rand(0, 10000) / 100.0;
}

function randomBool() {
    return (bool)rand(0, 1);
}

function randomImageFile() {
    $tmp = tempnam(sys_get_temp_dir(), 'img');
    $im = imagecreatetruecolor(100, 100);
    $bg = imagecolorallocate($im, rand(0,255), rand(0,255), rand(0,255));
    imagefill($im, 0, 0, $bg);
    imagepng($im, $tmp);
    imagedestroy($im);
    return $tmp;
}

function randomArray() {
    return [
        'tela' => 'exemplo',
        'key' => 1,
        'selecionado' => 'true',
        'campo_chave' => 'id',
        'chave' => 123,
        'parametrosConsulta' => ['todosItensSelecionados' => true, 'itensPagina' => 1],
        'lista' => [['selecionado' => 'true']],
        'tabela' => 'usuarios',
        'campo' => 'id',
        'valor' => 'valor',
        'dados' => [],
        'configuracoes' => [],
    ];
}

function randomParamsForMethod($className, $methodName) {
    // Métodos que esperam array como primeiro parâmetro
    $arrayMethods = [
        'selecionarItemConsulta', 'selecionarTodosItensConsulta', 'montaItensRelatorio', 'consulta',
        'buscarParaAlterar', 'buscarAnexos', 'detalhar', 'manipula', 'verificaRelacionamentos',
        'anexarArquivos', 'excluir', 'alterarPosicaoAnexo', 'buscarPorChave', 'buscarChavePorCampos',
        'buscarDuplicidadeCadastro', 'campostabela', 'pegaDataBase', 'executasql', 'retornosql',
        'campochavetabela', 'retornosqldireto', 'montasql', 'formatarValoresExibir', 'buscarCampoDistintoTabela',
        'montaFiltrosRelatorios', 'qtditensselecionados', 'tabelasbase', 'tabelasPorCampo', 'valorexibirconsulta',
        'tipodadocampo', 'objetoexistesimples', 'objetoexiste', 'objetoexistecomposto', 'objetoemuso',
        'arraydadostabela', 'buscaumcampotabela', 'buscachaveporcampos', 'buscacamposporchave',
        'buscadadosalterar', 'buscatextoalterar', 'buscadadosalterarjson', 'buscadadosimprimir',
        'altera', 'echaveestrangeira', 'exclui', 'inclui', 'proximachave', 'ordenaresultadoconsulta',
        'jsonpopulaselect', 'completacampo', 'completacampopornomedecampo', 'listatabelaspornomedecampo',
        'buscarDuplicidadeCadastro', 'alterarAnexo', 'excluiranexo', 'excluirAnexosTabela'
    ];
    $intMethods = ['rotacionarImagem'];
    $arrayFirstParam = in_array($methodName, $arrayMethods);
    $intFirstParam = in_array($methodName, $intMethods);
    if ($arrayFirstParam) {
        return [randomArray()];
    }
    if ($intFirstParam) {
        return [rand(1, 100)];
    }
    // Métodos que esperam string, string, string...
    return [randomString(), randomString(), randomString(), randomString()];
}

function testClass($className, $logFile) {
    echo "\nTestando classe: $className\n";
    try {
        $refClass = new ReflectionClass($className);
        $constructor = $refClass->getConstructor();
        $instance = $constructor && $constructor->getNumberOfParameters() === 0 ? $refClass->newInstance() : null;
        foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isDestructor() || $method->isStatic()) continue;
            $params = randomParamsForMethod($className, $method->getName());
            $resultLine = $className . '::' . $method->getName() . '(' . implode(', ', array_map('gettype', $params)) . ') - ';
            try {
                $result = $method->invokeArgs($instance ?: $refClass->newInstanceWithoutConstructor(), $params);
                echo "  Testando método: {$method->getName()}(" . implode(', ', array_map('gettype', $params)) . ")... OK\n";
                $resultLine .= "OK";
            } catch (Throwable $e) {
                echo "  Testando método: {$method->getName()}(" . implode(', ', array_map('gettype', $params)) . ")... ERRO: ".$e->getMessage()."\n";
                $resultLine .= "ERRO: " . $e->getMessage();
            }
            file_put_contents($logFile, $resultLine . PHP_EOL, FILE_APPEND);
        }
    } catch (Throwable $e) {
        $resultLine = "ERRO ao testar classe $className: ".$e->getMessage();
        echo $resultLine . "\n";
        file_put_contents($logFile, $resultLine . PHP_EOL, FILE_APPEND);
    }
}

// Descobre todas as classes do src/
foreach (glob(__DIR__ . '/../src/*.php') as $file) {
    try {
        require_once $file;
    } catch (Throwable $e) {
        echo "Ignorando arquivo ".$file." devido a erro: ".$e->getMessage()."\n";
    }
}

$logFile = __DIR__ . '/auto_test_log.txt';
file_put_contents($logFile, ""); // Limpa o log antes de rodar

$declared = get_declared_classes();
foreach ($declared as $class) {
    $ref = new ReflectionClass($class);
    if (strpos($ref->getFileName(), realpath(__DIR__ . '/../src/')) === 0) {
        testClass($class, $logFile);
    }
}

echo "\nTestes automáticos finalizados.\nLog salvo em tests/auto_test_log.txt\n";
