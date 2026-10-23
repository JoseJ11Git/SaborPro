<?php
/**
 * SaborPro :: api/avaliacao_admin.php
 * -------------------------------------------------------------
 * Moderação das avaliações no portal de gestão.
 * POST JSON: { id, acao: "visivel" | "eliminar", valor: 0|1 }
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

if (!autenticado()) {
    resposta(['ok' => false, 'erro' => 'Sessão inválida.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resposta(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}

$corpo = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($corpo)) {
    resposta(['ok' => false, 'erro' => 'Pedido inválido.'], 400);
}

$id   = (int) ($corpo['id'] ?? 0);
$acao = (string) ($corpo['acao'] ?? '');

if ($id <= 0) {
    resposta(['ok' => false, 'erro' => 'Avaliação inválida.'], 400);
}

if ($acao === 'visivel') {
    $valor = ((int) ($corpo['valor'] ?? 0)) === 1 ? 1 : 0;
    $stmt = $bd->prepare('UPDATE avaliacoes SET visivel = ? WHERE id = ?');
    $stmt->bind_param('ii', $valor, $id);
    $stmt->execute();
    $stmt->close();
    resposta(['ok' => true, 'visivel' => $valor]);
}

if ($acao === 'eliminar') {
    $stmt = $bd->prepare('DELETE FROM avaliacoes WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    resposta(['ok' => true, 'eliminado' => true]);
}

resposta(['ok' => false, 'erro' => 'Ação desconhecida.'], 400);
