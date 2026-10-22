<?php
/**
 * SaborPro :: api/mesa.php
 * -------------------------------------------------------------
 * API JSON do simulador de planta de sala 2D.
 *  GET  ?mesa=N            -> estado da mesa, pedido ativo e itens
 *  POST acao=adicionar     -> acrescenta item ao pedido ativo
 *  POST acao=fechar        -> fecha a conta (pedido Entregue, mesa Livre)
 *  POST acao=estado        -> muda o estado da mesa
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

/* ======================= LEITURA (GET) ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mesaId = (int) ($_GET['mesa'] ?? 0);
    if ($mesaId <= 0) {
        resposta(['ok' => false, 'erro' => 'Mesa inválida.'], 400);
    }

    $stmt = $bd->prepare('SELECT id, numero, capacidade, estado FROM mesas WHERE id = ?');
    $stmt->bind_param('i', $mesaId);
    $stmt->execute();
    $mesa = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$mesa) {
        resposta(['ok' => false, 'erro' => 'Mesa inexistente.'], 404);
    }

    // Pedido ativo mais recente da mesa
    $stmt = $bd->prepare("SELECT id, estado, total, tipo_pedido, cliente_nome, criado_em
                             FROM pedidos
                            WHERE mesa_id = ? AND estado <> 'Entregue'
                            ORDER BY criado_em DESC LIMIT 1");
    $stmt->bind_param('i', $mesaId);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $itens = [];
    if ($pedido) {
        $pid = (int) $pedido['id'];
        $stmt = $bd->prepare('SELECT i.id, i.quantidade, i.preco_unitario, e.nome
                                FROM itens_pedido i JOIN ementa e ON e.id = i.ementa_id
                               WHERE i.pedido_id = ? ORDER BY i.id');
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($i = $res->fetch_assoc()) {
            $itens[] = $i;
        }
        $stmt->close();
    }

    resposta([
        'ok'     => true,
        'mesa'   => [
            'id'        => (int) $mesa['id'],
            'numero'    => (int) $mesa['numero'],
            'capacidade'=> (int) $mesa['capacidade'],
            'estado'    => (string) $mesa['estado'],
        ],
        'pedido' => $pedido ? [
            'id'          => (int) $pedido['id'],
            'estado'      => (string) $pedido['estado'],
            'total'       => (float) $pedido['total'],
            'tipo'        => (string) $pedido['tipo_pedido'],
            'cliente'     => $pedido['cliente_nome'],
            'criado_em'   => data_hora((string) $pedido['criado_em']),
        ] : null,
        'itens'  => $itens,
    ]);
    exit;
}

/* ======================= ESCRITA (POST) ======================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resposta(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}

$corpo = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($corpo)) {
    $corpo = $_POST;
}

$acao    = $corpo['acao'] ?? '';
$mesaId  = (int) ($corpo['mesa'] ?? 0);
$itemId  = (int) ($corpo['item'] ?? 0);
$qtd     = max(1, (int) ($corpo['qtd'] ?? 1));
$novoEst = (string) ($corpo['estado'] ?? '');

if ($mesaId <= 0) {
    resposta(['ok' => false, 'erro' => 'Mesa inválida.'], 400);
}

/* --- Alterar estado --- */
if ($acao === 'estado') {
    if (!in_array($novoEst, ['Livre', 'Ocupada', 'Reservada'], true)) {
        resposta(['ok' => false, 'erro' => 'Estado inválido.'], 400);
    }
    $stmt = $bd->prepare('UPDATE mesas SET estado = ? WHERE id = ?');
    $stmt->bind_param('si', $novoEst, $mesaId);
    $stmt->execute();
    $stmt->close();
    resposta(['ok' => true, 'estado' => $novoEst]);
    exit;
}

/* --- Fechar conta --- */
if ($acao === 'fechar') {
    $bd->begin_transaction();
    try {
        $stmt = $bd->prepare("UPDATE pedidos SET estado = 'Entregue'
                               WHERE mesa_id = ? AND estado <> 'Entregue'");
        $stmt->bind_param('i', $mesaId);
        $stmt->execute();
        $stmt->close();

        $stmt = $bd->prepare("UPDATE mesas SET estado = 'Livre' WHERE id = ?");
        $stmt->bind_param('i', $mesaId);
        $stmt->execute();
        $stmt->close();

        $bd->commit();
        resposta(['ok' => true, 'mensagem' => 'Conta encerrada. Mesa libertada.']);
    } catch (Throwable $e) {
        $bd->rollback();
        resposta(['ok' => false, 'erro' => 'Não foi possível fechar a conta.'], 500);
    }
    exit;
}

/* --- Adicionar item ao pedido ativo --- */
if ($acao === 'adicionar') {
    if ($itemId <= 0) {
        resposta(['ok' => false, 'erro' => 'Item inválido.'], 400);
    }

    $stmt = $bd->prepare('SELECT id, preco, nome, disponivel FROM ementa WHERE id = ?');
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        resposta(['ok' => false, 'erro' => 'Item inexistente.'], 404);
    }
    if ((int) $item['disponivel'] !== 1) {
        resposta(['ok' => false, 'erro' => 'Esse item está indisponível.'], 409);
    }

    $preco = (float) $item['preco'];

    // Procura (ou cria) o pedido ativo da mesa
    $stmt = $bd->prepare("SELECT id FROM pedidos WHERE mesa_id = ? AND estado <> 'Entregue' ORDER BY criado_em DESC LIMIT 1");
    $stmt->bind_param('i', $mesaId);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $bd->begin_transaction();
    try {
        if ($pedido) {
            $pedidoId = (int) $pedido['id'];
        } else {
            $utilId = (int) utilizador_atual()['id'];
            $stmt = $bd->prepare("INSERT INTO pedidos (mesa_id, utilizador_id, total, estado, tipo_pedido)
                                   VALUES (?, ?, 0, 'Pendente', 'Na Mesa')");
            $stmt->bind_param('ii', $mesaId, $utilId);
            $stmt->execute();
            $pedidoId = (int) $bd->insert_id;
            $stmt->close();

            $stmt = $bd->prepare("UPDATE mesas SET estado = 'Ocupada' WHERE id = ?");
            $stmt->bind_param('i', $mesaId);
            $stmt->execute();
            $stmt->close();
        }

        // Se já existe o mesmo item, soma a quantidade
        $stmt = $bd->prepare('SELECT id, quantidade FROM itens_pedido WHERE pedido_id = ? AND ementa_id = ?');
        $pid = $pedidoId;
        $iid = $itemId;
        $stmt->bind_param('ii', $pid, $iid);
        $stmt->execute();
        $existente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existente) {
            $novaQtd = (int) $existente['quantidade'] + $qtd;
            $stmt = $bd->prepare('UPDATE itens_pedido SET quantidade = ? WHERE id = ?');
            $stmt->bind_param('ii', $novaQtd, (int) $existente['id']);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $bd->prepare('INSERT INTO itens_pedido (pedido_id, ementa_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iiid', $pedidoId, $itemId, $qtd, $preco);
            $stmt->execute();
            $stmt->close();
        }

        // Recalcula o total do pedido
        $stmt = $bd->prepare('SELECT COALESCE(SUM(quantidade * preco_unitario),0) FROM itens_pedido WHERE pedido_id = ?');
        $stmt->bind_param('i', $pedidoId);
        $stmt->execute();
        $total = (float) $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        $stmt = $bd->prepare('UPDATE pedidos SET total = ? WHERE id = ?');
        $stmt->bind_param('di', $total, $pedidoId);
        $stmt->execute();
        $stmt->close();

        $bd->commit();
        resposta(['ok' => true, 'pedido_id' => $pedidoId, 'total' => $total]);
    } catch (Throwable $e) {
        $bd->rollback();
        resposta(['ok' => false, 'erro' => 'Não foi possível adicionar o item.'], 500);
    }
    exit;
}

resposta(['ok' => false, 'erro' => 'Ação desconhecida.'], 400);
