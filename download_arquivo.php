<?php
$arquivo = $_GET["arquivo"];
@session_start();

$caminho = $_SESSION[session_id()]['caminhoApiLocal'];
$file = $caminho.$arquivo;

$extensoes = array('jpg', 'rar', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'rar');
$ext = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));

echo $arquivo;
if (in_array($ext, $extensoes) && file_exists($file)) {
    echo ' é arquivo';
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($file));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    ob_clean();
    flush();
    readfile($file);
    exit;
}
?>