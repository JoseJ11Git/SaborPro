<?php
/**
 * SaborPro :: cozinha.php
 * KDS — Ecrã de cozinha (Kitchen Display System).
 * Mostra os pedidos por estado e permite avançar com 1 clique.
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

exigir_login();

/* ------------------------ AVANÇAR ESTADO ---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'estado') {
    $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
    $novo     = $_POST['novo_estado'] ?? '';

    $permitidos = ['Pendente', 'Em Preparação', 'Pronto', 'Entregue'];
    if ($pedidoId > 0 && in_array($novo, $permitidos, true)) {
        $stmt = $bd->prepare('UPDATE pedidos SET estado = ? WHERE id = ?');
        $stmt->bind_param('si', $novo, $pedidoId);
        $stmt->execute();
        $stmt->close();

        // Se o pedido foi entregue, a mesa fica livre
        if ($novo === 'Entregue') {
            $stmt = $bd->prepare("UPDATE mesas m SET m.estado = 'Livre'
                                  WHERE m.id = (SELECT mesa_id FROM pedidos WHERE id = ?)
                                    AND NOT EXISTS (SELECT 1 FROM pedidos p WHERE p.mesa_id = m.id AND p.estado <> 'Entregue')");
            $stmt->bind_param('i', $pedidoId);
            $stmt->execute();
            $stmt->close();
        }
    }
    // Recarrega a página para atualizar o quadro
    header('Location: cozinha.php');
    exit;
}

/* ----------------------------- DADOS -------------------------------- */
$pedidos = [];
$r = $bd->query("SELECT p.id, p.total, p.estado, p.criado_em, m.numero AS mesa,
                         u.nome AS empregado,
                         (SELECT COUNT(*) FROM itens_pedido i WHERE i.pedido_id = p.id) AS nr_itens
                   FROM pedidos p
                   JOIN mesas m ON m.id = p.mesa_id
                   JOIN utilizadores u ON u.id = p.utilizador_id
                  WHERE p.estado <> 'Entregue'
                  ORDER BY FIELD(p.estado,'Pendente','Em Preparação','Pronto'), p.criado_em ASC");
while ($p = $r->fetch_assoc()) {
    $pedidos[] = $p;
}
$r->free();

// Itens de cada pedido
$itensPorPedido = [];
if ($pedidos) {
    $ids = array_column($pedidos, 'id');
    $in  = implode(',', array_map('intval', $ids));
    $r = $bd->query("SELECT i.pedido_id, i.quantidade, i.preco_unitario, e.nome
                       FROM itens_pedido i JOIN ementa e ON e.id = i.ementa_id
                      WHERE i.pedido_id IN ($in)
                      ORDER BY i.id");
    while ($i = $r->fetch_assoc()) {
        $itensPorPedido[(int) $i['pedido_id']][] = $i;
    }
    $r->free();
}

$contagem = ['Pendente' => 0, 'Em Preparação' => 0, 'Pronto' => 0];
foreach ($pedidos as $p) {
    if (isset($contagem[$p['estado']])) {
        $contagem[$p['estado']]++;
    }
}

// Minutos decorridos desde a criação do pedido
function minutos_decorridos(string $criadoEm): int
{
    $ts = strtotime($criadoEm);
    return $ts === false ? 0 : max(0, (int) floor((time() - $ts) / 60));
}

$corEstado = [
    'Pendente'      => 'var(--aviso)',
    'Em Preparação' => 'var(--info)',
    'Pronto'        => 'var(--sucesso)',
];

$paginaTitulo = 'Ecrã de Cozinha';
$paginaAtiva  = 'cozinha';
$autoRefresh  = 20;   // segundos entre recarregamentos automáticos

require_once __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina entre-topo">
    <div>
        <h1 class="mb-0">Ecrã de Cozinha</h1>
        <p class="subtitulo">Pedidos em curso — a atualização é automática a cada 20 segundos.</p>
    </div>
    <div class="d-flex-wrap">
        <span class="etiqueta estado-pendente"><span class="ponto"></span><?= (int) $contagem['Pendente'] ?> Pendente(s)</span>
        <span class="etiqueta estado-preparacao"><span class="ponto"></span><?= (int) $contagem['Em Preparação'] ?> Em preparação</span>
        <span class="etiqueta estado-pronto"><span class="ponto"></span><?= (int) $contagem['Pronto'] ?> Pronto(s)</span>
    </div>
</div>

<?php if (isset($_GET['novo'])): ?>
    <div class="alerta alerta-ok" data-autoclose><?= icone('ok') ?><div>Pedido #<?= (int) $_GET['novo'] ?> registado e enviado para a cozinha.</div></div>
<?php endif; ?>

<?php if (!$pedidos): ?>
    <div class="cartao texto-centro" style="padding:60px 20px">
        <?= icone('cozinha', '', 44) ?>
        <h3 class="mt-2">Nada em preparação</h3>
        <p class="mb-0">Todos os pedidos foram entregues. A cozinha está tranquila.</p>
    </div>
<?php else: ?>
    <div class="kds-grelha">
        <?php foreach ($pedidos as $i => $p): ?>
            <?php $min = minutos_decorridos((string) $p['criado_em']); ?>
            <article class="kds-cartao d<?= ($i % 8) + 1 ?>" style="--cor: <?= $corEstado[$p['estado']] ?? 'var(--aviso)' ?>; animation-delay: <?= $i * 0.05 ?>s">

                <div class="cab">
                    <div>
                        <div class="mesa-tag">Mesa <?= (int) $p['mesa'] ?></div>
                        <div class="tempo">#<?= (int) $p['id'] ?> · <?= e($p['empregado']) ?></div>
                    </div>
                    <div style="text-align:right">
                        <span class="etiqueta <?= classe_estado((string) $p['estado']) ?>"><span class="ponto"></span><?= e($p['estado']) ?></span>
                        <div class="tempo mt-1"><?= $min ?> min</div>
                    </div>
                </div>

                <ul>
                    <?php foreach ($itensPorPedido[(int) $p['id']] ?? [] as $it): ?>
                        <li>
                            <span><span class="q"><?= (int) $it['quantidade'] ?>×</span><?= e($it['nome']) ?></span>
                            <span class="texto-3 pequeno"><?= dinheiro((float) $it['preco_unitario']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="entre mb-2 pequeno texto-2">
                    <span><?= (int) $p['nr_itens'] ?> item(ns)</span>
                    <span class="negrito acentuado"><?= dinheiro((float) $p['total']) ?></span>
                </div>

                <div class="acoes">
                    <?php if ($p['estado'] === 'Pendente'): ?>
                        <form method="post" style="flex:1;display:flex">
                            <input type="hidden" name="acao" value="estado">
                            <input type="hidden" name="pedido_id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="novo_estado" value="Em Preparação">
                            <button class="btn btn-aviso btn-bloco"><?= icone('cozinha', '', 15) ?> Iniciar Preparação</button>
                        </form>
                    <?php elseif ($p['estado'] === 'Em Preparação'): ?>
                        <form method="post" style="flex:1;display:flex">
                            <input type="hidden" name="acao" value="estado">
                            <input type="hidden" name="pedido_id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="novo_estado" value="Pronto">
                            <button class="btn btn-sucesso btn-bloco"><?= icone('ok', '', 15) ?> Marcar como Pronto</button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="flex:1;display:flex">
                            <input type="hidden" name="acao" value="estado">
                            <input type="hidden" name="pedido_id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="novo_estado" value="Entregue">
                            <button class="btn btn-primario btn-bloco"><?= icone('ok', '', 15) ?> Servir / Entregar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="cartao mt-3">
    <p class="pequeno mb-0 texto-2">
        <strong>Fluxo:</strong> Pendente <span class="texto-3">→</span> Em Preparação <span class="texto-3">→</span> Pronto <span class="texto-3">→</span> Entregue.
        Ao marcar como <em>Entregue</em>, a mesa é automaticamente libertada.
    </p>
</div>

<?php require_once __DIR__ . '/includes/rodape.php'; ?>
