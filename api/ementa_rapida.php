<?php
/**
 * SaborPro :: api/ementa_rapida.php
 * -------------------------------------------------------------
 * Actualização rápida de preço / stock a partir do painel técnico.
 * Recebe JSON: { id, campo: "preco"|"disponivel", valor }
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

$id    = (int) ($corpo['id'] ?? 0);
$campo = (string) ($corpo['campo'] ?? '');
$valor = $corpo['valor'] ?? null;

if ($id <= 0) {
    resposta(['ok' => false, 'erro' => 'Item inválido.'], 400);
}

try {
    if ($campo === 'preco') {
        $preco = round((float) $valor, 2);
        if ($preco <= 0 || $preco > 9999) {
            resposta(['ok' => false, 'erro' => 'Preço inválido.'], 400);
        }
        $stmt = $bd->prepare('UPDATE ementa SET preco = ? WHERE id = ?');
        $stmt->bind_param('di', $preco, $id);
        $stmt->execute();
        $afetadas = $stmt->affected_rows;
        $stmt->close();

        if ($afetadas < 0) {
            resposta(['ok' => false, 'erro' => 'Não foi possível actualizar o preço.'], 500);
        }
        resposta(['ok' => true, 'campo' => 'preco', 'valor' => $preco]);
    }

    if ($campo === 'disponivel') {
        $disp = ((int) $valor) === 1 ? 1 : 0;
        $stmt = $bd->prepare('UPDATE ementa SET disponivel = ? WHERE id = ?');
        $stmt->bind_param('ii', $disp, $id);
        $stmt->execute();
        $stmt->close();
        resposta(['ok' => true, 'campo' => 'disponivel', 'valor' => $disp]);
    }
} catch (Throwable $erro) {
    error_log('SaborPro ementa_rapida: ' . $erro->getMessage());
    resposta(['ok' => false, 'erro' => 'Não foi possível actualizar o item.'], 500);
}

resposta(['ok' => false, 'erro' => 'Campo não suportado.'], 400);
