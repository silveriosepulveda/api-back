<?php

use ClasseGeral\ConClasseGeral;

/**
 * temas - Marca (cores) por app, persistida em arquivo JSON no servidor.
 *
 * Rotas automáticas (genéricas de `api.class.php`, sem alteração no roteador):
 * - GET  temas/buscarTemas/{appId} — PÚBLICO (brandea até a tela de login)
 * - POST temas/salvarTemas            — exige login (appId, mode, primaryLight, primaryDark)
 *
 * Arquivo por app: <projeto>/api/backLocal/config/temas_<appId>.json
 * Formato: {"mode":"light","primary":{"light":"#2e7d32","dark":"#66bb6a"}}
 */
class temas extends \ClasseGeral\ClasseGeral
{
    /** Apps autorizados a ter arquivo de marca (anti path-traversal por construção). */
    private const APPS_PERMITIDOS = ['clubes', 'sistema', 'playeron'];

    function __construct()
    {
        clearstatcache();
        $con = new ConClasseGeral;
        date_default_timezone_set('America/Sao_Paulo');
    }

    /**
     * GET temas/buscarTemas — público.
     * $parametros: ['appId' => 'clubes'] (GET codificado) ou string 'clubes'.
     */
    public function buscarTemas($parametros)
    {
        $appId = is_array($parametros) ? ($parametros['appId'] ?? '') : (string)$parametros;
        if (!$this->appValido($appId)) {
            return json_encode(['erro' => 'App inválido']);
        }

        $arq = $this->caminhoArquivo($appId, false);
        if ($arq !== null && is_file($arq)) {
            $json = json_decode((string)file_get_contents($arq), true);
            if (is_array($json)) {
                return json_encode(['configurado' => true] + $this->sanitizar($json));
            }
        }

        return json_encode(['configurado' => false] + $this->padrao($appId));
    }

    /**
     * POST temas/salvarTemas — exige login.
     * Chaves flat (appId, mode, primaryLight, primaryDark) ou 'dados' JSON.
     */
    public function salvarTemas($parametros)
    {
        $usuario = [];
        try {
            $usuario = $this->buscaUsuarioLogado();
        } catch (\Throwable $e) {
            $usuario = [];
        }
        if (empty($usuario['sessao'])) {
            return json_encode(['erro' => 'Usuário não logado, ou não localizado']);
        }

        $p = is_array($parametros) ? $parametros : [];
        if (isset($p['dados']) && is_string($p['dados'])) {
            $decodificado = json_decode($p['dados'], true);
            if (is_array($decodificado)) {
                $p = $decodificado;
            }
        }

        $appId = (string)($p['appId'] ?? '');
        $mode = (string)($p['mode'] ?? '');
        $light = (string)($p['primaryLight'] ?? '');
        $dark = (string)($p['primaryDark'] ?? '');

        if (!$this->appValido($appId)) {
            return json_encode(['erro' => 'App inválido']);
        }
        if ($mode !== 'light' && $mode !== 'dark') {
            return json_encode(['erro' => 'Modo inválido']);
        }
        if (!$this->corValida($light) || !$this->corValida($dark)) {
            return json_encode(['erro' => 'Cor inválida (use #rrggbb)']);
        }

        $arq = $this->caminhoArquivo($appId, true);
        if ($arq === null) {
            return json_encode(['erro' => 'Diretório de configuração indisponível']);
        }

        $conteudo = json_encode([
            'mode' => $mode,
            'primary' => ['light' => $light, 'dark' => $dark],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $ok = @file_put_contents($arq, $conteudo, LOCK_EX);
        if ($ok === false) {
            return json_encode(['erro' => 'Falha ao gravar configuração']);
        }

        return json_encode(['sucesso' => true]);
    }

    // ----------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------

    private function appValido($appId): bool
    {
        return is_string($appId) && in_array($appId, self::APPS_PERMITIDOS, true);
    }

    private function corValida($cor): bool
    {
        return is_string($cor) && (bool)preg_match('/^#[0-9A-Fa-f]{6}$/', $cor);
    }

    /** Marca padrão por app (quando não há arquivo). */
    private function padrao(string $appId): array
    {
        $verde = ['light' => '#2e7d32', 'dark' => '#66bb6a'];
        $mapa = [
            'clubes' => $verde,
            'sistema' => $verde,
            'playeron' => ['light' => '#dcb826', 'dark' => '#dcb826'],
        ];
        return ['mode' => 'light', 'primary' => $mapa[$appId] ?? $verde];
    }

    /** Normaliza o conteúdo do arquivo (ignora chaves extras, completa faltantes). */
    private function sanitizar(array $json): array
    {
        $appId = '';
        $padrao = $this->padrao($appId);
        $mode = ($json['mode'] ?? '') === 'dark' ? 'dark' : 'light';
        $primary = $json['primary'] ?? [];
        $light = $this->corValida($primary['light'] ?? '') ? $primary['light'] : $padrao['primary']['light'];
        $dark = $this->corValida($primary['dark'] ?? '') ? $primary['dark'] : $padrao['primary']['dark'];
        return ['mode' => $mode, 'primary' => ['light' => $light, 'dark' => $dark]];
    }

    /**
     * Diretório <projeto>/api/backLocal/config (canônico via sessão, fallback DOCUMENT_ROOT).
     * Com $criar=true, tenta criar o diretório.
     */
    private function diretorioConfig(bool $criar): ?string
    {
        $candidatos = [];

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[session_id()]['caminhoApiLocal'])) {
            $candidatos[] = rtrim((string)$_SESSION[session_id()]['caminhoApiLocal'], '/') . '/api/backLocal/config';
        }
        $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        if ($docRoot !== '') {
            $candidatos[] = $docRoot . '/api/backLocal/config';
        }

        foreach ($candidatos as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
            if ($criar && @mkdir($dir, 0775, true)) {
                return $dir;
            }
        }
        return null;
    }

    /** Caminho do arquivo (nome montado no servidor — nunca vem do cliente). */
    private function caminhoArquivo(string $appId, bool $criarDir): ?string
    {
        $dir = $this->diretorioConfig($criarDir);
        if ($dir === null) {
            return null;
        }
        return $dir . '/temas_' . $appId . '.json';
    }
}
