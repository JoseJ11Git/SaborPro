<?php
/**
 * SaborPro :: pedidos_publicos.php
 * -------------------------------------------------------------
 * Processa os pedidos efectuados no SITE PÚBLICO (sem login).
 * Recebe JSON, valida tudo no servidor e envia o pedido para a cozinha.
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';

header('Content-Type: application/json; charset=utf-8');

/** Resposta JSON e fim do script. */
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

/* ------------------------- VALIDAÇÃO ------------------------- */
$tipo        = ($corpo['tipo'] ?? '') === 'Takeaway' ? 'Takeaway' : 'Na Mesa';
$mesaNumero  = (int) ($corpo['mesa_numero'] ?? 0);
$nome        = trim((string) ($corpo['nome'] ?? ''));
$telefone    = trim((string) ($corpo['telefone'] ?? ''));
$observacoes = trim((string) ($corpo['observacoes'] ?? ''));
$itens       = $corpo['itens'] ?? [];

if ($nome === '' || mb_strlen($nome) < 2) {
    resposta(['ok' => false, 'erro' => 'Indique o seu nome.'], 400);
}
if ($telefone !== '' && !preg_match('/^[0-9 +()\-]{6,25}$/', $telefone)) {
    resposta(['ok' => false, 'erro' => 'Número de telefone inválido.'], 400);
}
if (!is_array($itens) || count($itens) === 0) {
    resposta(['ok' => false, 'erro' => 'O carrinho está vazio.'], 400);
}

/* ------------------------- MESA / BALCÃO ------------------------- */
if ($tipo === 'Na Mesa') {
    if ($mesaNumero <= 0) {
        resposta(['ok' => false, 'erro' => 'Escolha a mesa.'], 400);
    }
    $stmt = $bd->prepare('SELECT id FROM mesas WHERE numero = ? AND numero > 0');
    $stmt->bind_param('i', $mesaNumero);
    $stmt->execute();
    $mesa = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$mesa) {
        resposta(['ok' => false, 'erro' => 'Mesa inexistente.'], 404);
    }
    $mesaId = (int) $mesa['id'];
} else {
    // Mesa 0 = Balcão (pedidos takeaway)
    $stmt = $bd->prepare('SELECT id FROM mesas WHERE numero = 0');
    $stmt->execute();
    $balcao = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $mesaId = $balcao ? (int) $balcao['id'] : 0;

    if ($mesaId === 0) {
        $bd->query("INSERT INTO mesas (numero, capacidade, estado) VALUES (0, 1, 'Livre')");
        $mesaId = (int) $bd->insert_id;
    }
}

/* ------------------------- ITENS (preços da BD) ------------------------- */
$agregado = [];
foreach ($itens as $item) {
    $id  = (int) ($item['id'] ?? 0);
    $qtd = (int) ($item['quantidade'] ?? 0);
    if ($id > 0 && $qtd > 0) {
        $agregado[$id] = ($agregado[$id] ?? 0) + $qtd;
    }
}
if (!$agregado) {
    resposta(['ok' => false, 'erro' => 'Nenhum item válido no pedido.'], 400);
}

$precos = [];
$in     = implode(',', array_map('intval', array_keys($agregado)));
$stmt   = $bd->prepare("SELECT id, nome, preco, disponivel FROM ementa WHERE id IN ($in)");
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

if (!$precos) {
    resposta(['ok' => false, 'erro' => 'Os artigos selecionados não existem.'], 400);
}

/* Utilizador do sistema que fica associado ao pedido público */
$res = $bd->query("SELECT id FROM utilizadores WHERE tipo IN ('empregado','admin') ORDER BY FIELD(tipo,'empregado','admin') LIMIT 1");
$util = $res->fetch_row();
$res->free();
$utilId = $util ? (int) $util[0] : 1;

/* ------------------------- GRAVAÇÃO ------------------------- */
$bd->begin_transaction();
try {
    $total = 0.0;
    foreach ($agregado as $id => $qtd) {
        $total += $qtd * $precos[$id];
    }
    $total = round($total, 2);

    $stmt = $bd->prepare(
        "INSERT INTO pedidos (mesa_id, utilizador_id, total, estado, tipo_pedido, cliente_nome, cliente_telefone, observacoes)
         VALUES (?, ?, ?, 'Pendente', ?, ?, ?, ?)"
    );
    $stmt->bind_param('iisssss', $mesaId, $utilId, $total, $tipo, $nome, $telefone, $observacoes);
    $stmt->execute();
    $pedidoId = (int) $bd->insert_id;
    $stmt->close();

    $stmt = $bd->prepare('INSERT INTO itens_pedido (pedido_id, ementa_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)');
    foreach ($agregado as $id => $qtd) {
        $preco   = $precos[$id];
        $idItem  = (int) $id;
        $qtdItem = (int) $qtd;
        $stmt->bind_param('iiid', $pedidoId, $idItem, $qtdItem, $preco);
        $stmt->execute();
    }
    $stmt->close();

    // Se for pedido na mesa, a mesa fica ocupada
    if ($tipo === 'Na Mesa') {
        $stmt = $bd->prepare("UPDATE mesas SET estado = 'Ocupada' WHERE id = ?");
        $stmt->bind_param('i', $mesaId);
        $stmt->execute();
        $stmt->close();
    }

    $bd->commit();

    resposta([
        'ok'        => true,
        'pedido_id' => $pedidoId,
        'total'     => number_format($total, 2, ',', '.') . ' €',
        'tipo'      => $tipo,
        'mensagem'  => $tipo === 'Takeaway'
            ? 'Recebemos o seu pedido para entrega. Estimate 20 minutos.'
            : 'Recebemos o seu pedido. A mesa está a ser servida.',
    ]);
} catch (Throwable $e) {
    $bd->rollback();
    resposta(['ok' => false, 'erro' => 'Não foi possível registar o pedido.'], 500);
}
