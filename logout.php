<?php
/**
 * SaborPro :: logout.php
 * Encerramento seguro da sessão.
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';

// Limpa todas as variáveis de sessão
$_SESSION = [];

// Destrói o cookie de sessão
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => 'Lax',
    ]);
}

// Destrói a sessão no servidor
session_destroy();

// Redireciona para a landing page com confirmação
header('Location: index.php?saiu=1');
exit;
