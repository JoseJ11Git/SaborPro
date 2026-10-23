<?php
/**
 * SaborPro :: api/avaliacao.php
 * -------------------------------------------------------------
 * Recebe uma avaliação do site público (sem login).
 * A avaliação entra com `visivel = 0` e aguarda moderação.
 *
 * POST JSON: { nome, classificacao, comentario, website (honeypot) }
 */
declare(strict_types=1);

require_once __DIR__ . '/../ligacao.php';

header('Content-Type: application/json; charset=utf-8');

function resposta(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resposta(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}

$corpo = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($corpo)) {
    resposta(['ok' => false, 'erro' => 'Pedido inválido.'], 400);
}

/* Honeypot invisível: se vier preenchido é um bot. */
if (trim((string) ($corpo['website'] ?? '')) !== '') {
    resposta(['ok' => true, 'mensagem' => 'Obrigado pela sua avaliação.']);
}

$nome         = trim((string) ($corpo['nome'] ?? ''));
$classificacao = (int) ($corpo['classificacao'] ?? 0);
$comentario   = trim((string) ($corpo['comentario'] ?? ''));

$nome       = preg_replace('/\s+/u', ' ', $nome) ?? '';
$comentario = preg_replace('/\s+/u', ' ', $comentario) ?? '';

if (mb_strlen($nome) < 2 || mb_strlen($nome) > 80) {
    resposta(['ok' => false, 'erro' => 'Indique o seu nome (2 a 80 caracteres).'], 400);
}
if ($classificacao < 1 || $classificacao > 5) {
    resposta(['ok' => false, 'erro' => 'Escolha uma classificação de 1 a 5 estrelas.'], 400);
}
if (mb_strlen($comentario) < 3) {
    resposta(['ok' => false, 'erro' => 'Escreva um pequeno comentário sobre a sua experiência.'], 400);
}
if (mb_strlen($comentario) > 500) {
    resposta(['ok' => false, 'erro' => 'O comentário não pode exceder 500 caracteres.'], 400);
}

/* Limite simples: uma avaliação por minuto por sessão. */
$agora = time();
if (!empty($_SESSION['ultima_avaliacao']) && ($agora - (int) $_SESSION['ultima_avaliacao']) < 45) {
    resposta(['ok' => false, 'erro' => 'Aguarde alguns segundos antes de enviar outra avaliação.'], 429);
}

$stmt = $bd->prepare('INSERT INTO avaliacoes (nome_cliente, classificacao, comentario, visivel) VALUES (?, ?, ?, 0)');
$stmt->bind_param('sis', $nome, $classificacao, $comentario);
$stmt->execute();
$stmt->close();

$_SESSION['ultima_avaliacao'] = $agora;

resposta([
    'ok'        => true,
    'mensagem'  => 'Obrigado! A sua avaliação será publicada após aprovação da equipa.',
]);
