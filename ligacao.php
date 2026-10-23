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

/* ============================ UTF-8 (GLOBAL) ============================ */
// Todo o texto interno, leitura da BD e saída HTML trabalham em UTF-8 puro.
mb_internal_encoding('UTF-8');
mb_regex_encoding('UTF-8');
if (function_exists('mb_substitute_character')) {
    mb_substitute_character('none');
}
ini_set('default_charset', 'UTF-8');

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

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

/* ====================== COMPATIBILIDADE / MIGRAÇÕES =================== */

/**
 * Garante que as colunas extensíveis existem (migração idempotente).
 * Executada uma única vez por pedido — mantém compatibilidade com
 * bases de dados instaladas antes da refatorização.
 */
function saborpro_migrar(mysqli $bd): void
{
    static $feito = false;
    if ($feito) {
        return;
    }
    $feito = true;

    $colunas = [
        'ementa'  => [
            'etiquetas'  => "ADD COLUMN etiquetas VARCHAR(120) DEFAULT NULL AFTER disponivel",
            'destaque'   => 'ADD COLUMN destaque TINYINT(1) NOT NULL DEFAULT 0 AFTER etiquetas',
            'imagem_url' => 'ADD COLUMN imagem_url VARCHAR(500) DEFAULT NULL AFTER destaque',
            'nome_en'    => 'ADD COLUMN nome_en VARCHAR(120) DEFAULT NULL AFTER nome',
            'descricao_en' => 'ADD COLUMN descricao_en VARCHAR(255) DEFAULT NULL AFTER descricao',
        ],
        'pedidos' => [
            'tipo_pedido'       => "ADD COLUMN tipo_pedido ENUM('Takeaway','Na Mesa') NOT NULL DEFAULT 'Na Mesa' AFTER estado",
            'cliente_nome'      => 'ADD COLUMN cliente_nome VARCHAR(80) DEFAULT NULL AFTER tipo_pedido',
            'cliente_telefone'  => 'ADD COLUMN cliente_telefone VARCHAR(30) DEFAULT NULL AFTER cliente_nome',
            'observacoes'       => 'ADD COLUMN observacoes VARCHAR(255) DEFAULT NULL AFTER cliente_telefone',
        ],
    ];

    foreach ($colunas as $tabela => $defs) {
        $res = $bd->query("SHOW COLUMNS FROM `$tabela`");
        $existentes = [];
        while ($c = $res->fetch_assoc()) {
            $existentes[] = $c['Field'];
        }
        $res->free();

        foreach ($defs as $col => $sql) {
            if (!in_array($col, $existentes, true)) {
                $bd->query("ALTER TABLE `$tabela` $sql");
            }
        }
    }

    // Mesa "Balcão" (numero 0) para pedidos Takeaway do site público
    $res = $bd->query("SELECT id FROM mesas WHERE numero = 0");
    if (!$res->fetch_row()) {
        $bd->query("INSERT INTO mesas (numero, capacidade, estado) VALUES (0, 1, 'Livre')");
    }
    $res->free();

    // Avaliações dos clientes (site público)
    $bd->query("CREATE TABLE IF NOT EXISTS avaliacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome_cliente VARCHAR(80) NOT NULL,
        classificacao TINYINT NOT NULL DEFAULT 5,
        comentario VARCHAR(500) DEFAULT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        visivel TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_avaliacoes_visivel (visivel),
        INDEX idx_avaliacoes_data (criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

saborpro_migrar($bd);

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
function tem_pape(array|string $papeis): bool
{
    if (!autenticado()) {
        return false;
    }
    $papeis = is_array($papeis) ? $papeis : [$papeis];
    return in_array(utilizador_atual()['tipo'], $papeis, true);
}

/** Exige login (e opcionalmente um papel) redirecionando se necessário. */
function exigir_login(array|string $papeis = []): void
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

/**
 * Tempo decorrido desde uma data/hora, em minutos.
 * Devolve um array com o total de minutos e um texto curto legível.
 */
function tempo_decorrido(?string $dt): array
{
    if (!$dt) {
        return ['min' => 0, 'texto' => '—'];
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return ['min' => 0, 'texto' => '—'];
    }
    $min = max(0, (int) floor((time() - $ts) / 60));

    if ($min < 1) {
        $texto = 'agora mesmo';
    } elseif ($min < 60) {
        $texto = 'há ' . $min . ' min';
    } elseif ($min < 1440) {
        $texto = 'há ' . intdiv($min, 60) . ' h ' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT) . ' min';
    } else {
        $texto = 'há ' . intdiv($min, 1440) . ' dia(s)';
    }

    return ['min' => $min, 'texto' => $texto];
}

/** Devolve uma imagem de recurso caso o prato não tenha fotografia. */
function imagem_prato(?string $url): string
{
    $url = trim((string) $url);
    if ($url !== '' && preg_match('~^https?://~i', $url)) {
        return $url;
    }
    // Fotografia genérica de mesa/postura (Unsplash) usada como fallback
    return 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=900&q=80&auto=format&fit=crop';
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

/**
 * Devolve a cor de destaque (variável CSS) de uma categoria.
 * Entradas -> Laranja | Pratos -> Vermelho | Bebidas -> Azul | Sobremesas -> Dourado
 */
function cor_categoria(string $nome): string
{
    $n = mb_strtolower($nome);
    return match (true) {
        str_contains($n, 'entrada') => 'var(--cat-entradas)',
        str_contains($n, 'prato') || str_contains($n, 'principal') => 'var(--cat-pratos)',
        str_contains($n, 'bebida') || str_contains($n, 'bebido') => 'var(--cat-bebidas)',
        str_contains($n, 'sobremesa') || str_contains($n, 'doce') => 'var(--cat-sobremesas)',
        default => 'var(--cat-entradas)',
    };
}

/**
 * Converte a coluna `etiquetas` num array de etiquetas da ementa.
 * Ex.: "vegetariano,novidade" -> ['vegetariano', 'novidade']
 */
function etiquetas_ementa(?string $etiquetas): array
{
    if (!$etiquetas) {
        return [];
    }
    $lista = array_map('trim', explode(',', $etiquetas));
    return array_values(array_filter($lista));
}

/** Rótulo bonito para uma etiqueta da ementa. */
function rotulo_etiqueta(string $etiqueta): string
{
    return match ($etiqueta) {
        'vegetariano' => 'Vegetariano',
        'vegetariano ' => 'Vegetariano',
        'novidade'    => 'Novidade',
        'chef'        => 'Escolha do Chef',
        'picante'     => 'Picante',
        'sem-gluten'  => 'Sem Glúten',
        default       => ucfirst($etiqueta),
    };
}

/** Classe CSS da etiqueta da ementa (define a cor). */
function classe_etiqueta(string $etiqueta): string
{
    return match ($etiqueta) {
        'vegetariano' => 'tag-vegetariano',
        'novidade'    => 'tag-novidade',
        'chef'        => 'tag-chef',
        'picante'     => 'tag-picante',
        'sem-gluten'  => 'tag-sem-gluten',
        default       => 'tag-neutra',
    };
}
