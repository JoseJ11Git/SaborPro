<?php
/**
 * SaborPro :: api/pedido.php
 * Endpoint JSON para registo de pedidos (usado pelo ecrã POS).
 */
declare(strict_types=1);

require_once __DIR__ . '/../ligacao.php';

header('Content-Type: application/json; charset=utf-8');

/** Devolve uma resposta JSON e termina o script. */
function resposta(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!autenticado() || !tem_pape(['admin', 'empregado'])) {
    resposta(['ok' => false, 'erro' => 'Sessão inválida ou sem permissões.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resposta(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}

$corpo = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($corpo)) {
    resposta(['ok' => false, 'erro' => 'JSON inválido.'], 400);
}

$mesaId = (int) ($corpo['mesa_id'] ?? 0);
$itens  = $corpo['itens'] ?? [];

if ($mesaId <= 0) {
    resposta(['ok' => false, 'erro' => 'Mesa não selecionada.'], 400);
}
if (!is_array($itens) || count($itens) === 0) {
    resposta(['ok' => false, 'erro' => 'O pedido está vazio.'], 400);
}

/* Valida a mesa */
$stmt = $bd->prepare('SELECT id FROM mesas WHERE id = ?');
$stmt->bind_param('i', $mesaId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    $stmt->close();
    resposta(['ok' => false, 'erro' => 'Mesa inexistente.'], 404);
}
$stmt->close();

/* Valida e agrega os itens (preço sempre vindo da BD — nunca do cliente) */
$agregado = [];
foreach ($itens as $item) {
    $id  = (int) ($item['id'] ?? 0);
    $qtd = (int) ($item['quantidade'] ?? 0);
    if ($id <= 0 || $qtd <= 0) {
        continue;
    }
    $agregado[$id] = ($agregado[$id] ?? 0) + $qtd;
}

if (!$agregado) {
    resposta(['ok' => false, 'erro' => 'Nenhum item válido no pedido.'], 400);
}

$precos = [];
$stmt = $bd->prepare("SELECT id, nome, preco, disponivel FROM ementa WHERE id IN ("
    . implode(',', array_map('intval', array_keys($agregado))) . ")");
$stmt->execute();
$res = $stmt->get_result();
while ($e = $res->fetch_assoc()) {
    if ((int) $e['disponivel'] !== 1) {
        $stmt->close();
        resposta(['ok' => false, 'erro' => 'O item "' . $e['nome'] . '" já não está disponível.'], 409);
    }
    $precos[(int) $e['id']] = (float) $e['preco'];
}
$stmt->close();

/* Gravação transacional do pedido */
$bd->begin_transaction();

try {
    $total = 0.0;
    foreach ($agregado as $id => $qtd) {
        $total += $qtd * $precos[$id];
    }
    $total = round($total, 2);

    $utilId = (int) utilizador_atual()['id'];

    $stmt = $bd->prepare("INSERT INTO pedidos (mesa_id, utilizador_id, total, estado) VALUES (?, ?, ?, 'Pendente')");
    $stmt->bind_param('iid', $mesaId, $utilId, $total);
    $stmt->execute();
    $pedidoId = (int) $bd->insert_id;
    $stmt->close();

    $stmt = $bd->prepare('INSERT INTO itens_pedido (pedido_id, ementa_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)');
    foreach ($agregado as $id => $qtd) {
        $preco = $precos[$id];
        $idItem = (int) $id;
        $qtdItem = (int) $qtd;
        $stmt->bind_param('iiid', $pedidoId, $idItem, $qtdItem, $preco);
        $stmt->execute();
    }
    $stmt->close();

    // A mesa passa a "Ocupada"
    $stmt = $bd->prepare("UPDATE mesas SET estado = 'Ocupada' WHERE id = ?");
    $stmt->bind_param('i', $mesaId);
    $stmt->execute();
    $stmt->close();

    $bd->commit();

    resposta([
        'ok'        => true,
        'pedido_id' => $pedidoId,
        'total'     => number_format($total, 2, ',', '.') . ' €',
        'estado'    => 'Pendente',
    ]);
} catch (Throwable $e) {
    $bd->rollback();
    resposta(['ok' => false, 'erro' => 'Erro ao gravar o pedido: ' . $e->getMessage()], 500);
}
