<?php
/**
 * SaborPro :: ligacao.php
 * ---------------------------------------------------------
 * Ligação segura à base de dados MySQL (mysqli) + helpers.
 *
 * PREPARAÇÃO PARA SERVIDOR REMOTO:
 * basta alterar o array $CFG_REMOTO (host, user, pass, base) ou
 * colocar as credenciais reais. A deteção localhost/127.0.0.1
 * usa automaticamente as credenciais locais do Laragon.
 */

declare(strict_types=1);

/* ============================ CONFIGURAÇÃO ============================ */

// Credenciais do servidor remoto (ex.: lab.csmiguel.pt)
$CFG_REMOTO = [
    'host' => 'lab.csmiguel.pt',
    'user' => 'utilizador_saborpro',
    'pass' => 'alterar_esta_password',
    'base' => 'saborpro',
    'porta' => 3306,
];

// Credenciais locais (Laragon / XAMPP / WAMP)
// NOTA: usa-se 127.0.0.1 (e não "localhost") porque no Windows o
// mysqli com "localhost" tenta o named pipe, que falha no Laragon.
$CFG_LOCAL = [
    'host' => '127.0.0.1',
    'user' => 'root',
    'pass' => '',
    'base' => 'saborpro',
    'porta' => 3306,
];

/**
 * Devolve a configuração adequada ao host pedido.
 */
function saborpro_config(string $host = 'localhost'): array
{
    global $CFG_REMOTO, $CFG_LOCAL;

    // Remove a porta do host (ex.: "127.0.0.1:8088" -> "127.0.0.1")
    $host = strtolower(trim($host));
    if (str_contains($host, ':')) {
        $host = explode(':', $host)[0];
    }
    $eLocal = in_array($host, ['localhost', '127.0.0.1', '::1', ''], true);

    $cfg = $eLocal ? $CFG_LOCAL : $CFG_REMOTO;

    // Permite forçar um host remoto via constante de ambiente
    $forcado = getenv('SABORPRO_DB_HOST');
    if ($forcado) {
        $cfg['host'] = $forcado;
        $cfg['user'] = getenv('SABORPRO_DB_USER') ?: $cfg['user'];
        $cfg['pass'] = getenv('SABORPRO_DB_PASS') !== false ? getenv('SABORPRO_DB_PASS') : $cfg['pass'];
        $cfg['base'] = getenv('SABORPRO_DB_BASE') ?: $cfg['base'];
    }

    return $cfg;
}

/* ============================== LIGAÇÃO =============================== */

$__cfg = saborpro_config($_SERVER['HTTP_HOST'] ?? 'localhost');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$bd = null;
$__erro = null;

// Tenta a porta configurada e, em alternativa, a 3307 (outras instalações do Laragon)
foreach ([(int) $__cfg['porta'], 3307, 3306] as $__porta) {
    try {
        $bd = @new mysqli(
            $__cfg['host'],
            $__cfg['user'],
            $__cfg['pass'],
            $__cfg['base'],
            $__porta
        );
        $bd->set_charset('utf8mb4');
        break;
    } catch (mysqli_sql_exception $e) {
        $__erro = $e;
        $bd = null;
    }
}

if ($bd === null) {
    http_response_code(500);
    die('<!DOCTYPE html><meta charset="utf-8"><title>SaborPro</title>'
        . '<body style="background:#0F172A;color:#F8FAFC;font-family:system-ui;padding:40px">'
        . '<h2 style="color:#EF4444">Não foi possível ligar à base de dados</h2>'
        . '<p>Verifique se o MySQL do Laragon está ligado e se a base <code>saborpro</code> '
        . 'foi importada a partir de <code>base_dados.sql</code>.</p>'
        . '<pre style="background:#1E293B;padding:16px;border-radius:12px;color:#94A3B8">'
        . htmlspecialchars($__erro ? $__erro->getMessage() : 'Erro desconhecido', ENT_QUOTES, 'UTF-8') . '</pre></body>');
}

/* ============================== SESSÃO ================================ */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ============================== HELPERS =============================== */

/** Escapa texto para saída segura em HTML. */
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formata um valor monetário para o formato € 0,00 */
function dinheiro(float $v): string
{
    return number_format($v, 2, ',', '.') . ' €';
}

/** Verifica se o utilizador está autenticado. */
function autenticado(): bool
{
    return !empty($_SESSION['utilizador']['id']);
}

/** Devolve os dados do utilizador autenticado (ou array vazio). */
function utilizador_atual(): array
{
    return $_SESSION['utilizador'] ?? [];
}

/** Verifica se o utilizador tem um dos tipos indicados. */
function tem_pape(array|p|string $papeis): bool
{
    if (!autenticado()) {
        return false;
    }
    $papeis = is_array($papeis) ? $papeis : [$papeis];
    return in_array(utilizador_atual()['tipo'], $papeis, true);
}

/** Exige login (e opcionalmente um papel) redirecionando se necessário. */
function exigir_login(array|p|string $papeis = []): void
{
    if (!autenticado()) {
        header('Location: index.php?erro=sessao');
        exit;
    }
    if ($papeis && !tem_pape($papeis)) {
        header('Location: dashboard.php?erro=permissao');
        exit;
    }
}

/** Devolve a URL base da aplicação (para links relativos). */
function base_url(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $dir === '' ? '' : $dir;
}

/** Data/hora em formato legível em português. */
function data_hora(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return '—';
    }
    return date('d/m/Y H:i', $ts);
}

/** Classe CSS consoante o estado do pedido. */
function classe_estado(string $estado): string
{
    return match ($estado) {
        'Pendente'      => 'estado-pendente',
        'Em Preparação' => 'estado-preparacao',
        'Pronto'        => 'estado-pronto',
        'Entregue'      => 'estado-entregue',
        default         => 'estado-desconhecido',
    };
}

/** Rótulo legível do estado da mesa. */
function classe_mesa(string $estado): string
{
    return match ($estado) {
        'Livre'     => 'mesa-livre',
        'Ocupada'   => 'mesa-ocupada',
        'Reservada' => 'mesa-reservada',
        default     => 'mesa-livre',
    };
}
