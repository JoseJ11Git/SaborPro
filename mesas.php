<?php
/**
 * SaborPro :: mesas.php
 * Mapa visual de mesas com estados, pedidos em curso e abertura de contas.
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

exigir_login();

$aviso = '';
$erro  = '';

/* ------------------------- AÇÕES DAS MESAS -------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao  = $_POST['acao'] ?? '';
    $mesaId = (int) ($_POST['mesa_id'] ?? 0);

    if ($mesaId > 0) {
        if ($acao === 'estado') {
            $novo = $_POST['estado'] ?? 'Livre';
            if (!in_array($novo, ['Livre', 'Ocupada', 'Reservada'], true)) {
                $erro = 'Estado inválido.';
            } else {
                $stmt = $bd->prepare('UPDATE mesas SET estado = ? WHERE id = ?');
                $stmt->bind_param('si', $novo, $mesaId);
                $stmt->execute();
                $stmt->close();
                $aviso = 'Mesa atualizada com sucesso.';
            }
        } elseif ($acao === 'liberar') {
            // Marca o pedido ativo como entregue e liberta a mesa
            $bd->begin_transaction();
            $stmt = $bd->prepare("UPDATE pedidos SET estado = 'Entregue' WHERE mesa_id = ? AND estado <> 'Entregue'");
            $stmt->bind_param('i', $mesaId);
            $stmt->execute();
            $stmt->close();

            $stmt = $bd->prepare("UPDATE mesas SET estado = 'Livre' WHERE id = ?");
            $stmt->bind_param('i', $mesaId);
            $stmt->execute();
            $stmt->close();
            $bd->commit();

            $aviso = 'Mesa liberada e conta encerrada.';
        }
    }
}

/* ---------------------------- DADOS --------------------------------- */
$mesas = [];
$r = $bd->query("SELECT m.id, m.numero, m.capacidade, m.estado,
                         (SELECT p.id FROM pedidos p
                           WHERE p.mesa_id = m.id AND p.estado <> 'Entregue'
                           ORDER BY p.criado_em DESC LIMIT 1) AS pedido_ativo,
                         (SELECT COALESCE(SUM(p.total),0) FROM pedidos p
                           WHERE p.mesa_id = m.id AND p.estado <> 'Entregue') AS total_ativo,
                         (SELECT COUNT(*) FROM itens_pedido i
                            JOIN pedidos p2 ON p2.id = i.pedido_id
                           WHERE p2.mesa_id = m.id AND p2.estado <> 'Entregue') AS nr_itens
                    FROM mesas m
                   ORDER BY m.numero");
while ($m = $r->fetch_assoc()) {
    $mesas[] = $m;
}
$r->free();

// Contagens para o resumo
$resumo = ['Livre' => 0, 'Ocupada' => 0, 'Reservada' => 0];
foreach ($mesas as $m) {
    $resumo[$m['estado']]++;
}

$paginaTitulo = 'Mapa de Mesas';
$paginaAtiva  = 'mesas';

require_once __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina entre-topo">
    <div>
        <h1 class="mb-0">Mapa de Mesas</h1>
        <p class="subtitulo">Visualização em tempo real da ocupação do restaurante.</p>
    </div>
    <div class="d-flex-wrap">
        <span class="etiqueta estado-pronto"><span class="ponto"></span><?= (int) $resumo['Livre'] ?> Livre(s)</span>
        <span class="etiqueta estado-pendente"><span class="ponto"></span><?= (int) $resumo['Ocupada'] ?> Ocupada(s)</span>
        <span class="etiqueta badge-neutro"><?= (int) $resumo['Reservada'] ?> Reservada(s)</span>
    </div>
</div>

<?php if ($aviso !== ''): ?>
    <div class="alerta alerta-ok" data-autoclose><?= icone('ok') ?><div><?= e($aviso) ?></div></div>
<?php endif; ?>
<?php if ($erro !== ''): ?>
    <div class="alerta alerta-erro" data-autoclose><?= icone('alerta') ?><div><?= e($erro) ?></div></div>
<?php endif; ?>

<div class="mapa-mesas">
    <?php foreach ($mesas as $i => $m): ?>
        <div class="mesa <?= classe_mesa((string) $m['estado']) ?> d<?= ($i % 8) + 1 ?>" style="animation-delay:<?= $i * 0.04 ?>s">

            <div class="numero"><?= (int) $m['numero'] ?></div>
            <div class="capacidade"><?= icone('utilizadores', '', 13) ?> <?= (int) $m['capacidade'] ?> lugares</div>

            <div class="estado-mesa"><span class="ponto"></span><?= e($m['estado']) ?></div>

            <?php if ((int) $m['nr_itens'] > 0): ?>
                <div class="total-mesa">
                    <?= (int) $m['nr_itens'] ?> item(ns)<br>
                    <strong class="acentuado"><?= dinheiro((float) $m['total_ativo']) ?></strong>
                </div>
            <?php endif; ?>

            <div class="acao-mesa">
                <a href="pedidos.php?mesa=<?= (int) $m['numero'] ?>" class="btn btn-primario btn-sm">
                    <?= icone('carrinho', '', 14) ?> Abrir
                </a>
                <?php if ($m['estado'] !== 'Livre'): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="acao" value="liberar">
                        <input type="hidden" name="mesa_id" value="<?= (int) $m['id'] ?>">
                        <button class="btn btn-sucesso btn-sm" title="Fechar conta e liberar mesa">
                            <?= icone('ok', '', 14) ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Alteração rápida de estado -->
            <form method="post" class="mt-1">
                <input type="hidden" name="acao" value="estado">
                <input type="hidden" name="mesa_id" value="<?= (int) $m['id'] ?>">
                <select name="estado" onchange="this.form.submit()" style="padding:6px 8px;font-size:.78rem" title="Alterar estado">
                    <?php foreach (['Livre', 'Ocupada', 'Reservada'] as $op): ?>
                        <option value="<?= $op ?>" <?= $op === $m['estado'] ? 'selected' : '' ?>><?= $op ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<!-- ===================== LEGENDA ===================== -->
<div class="cartao mt-3">
    <div class="entre-topo">
        <div>
            <h3 class="mb-1">Como funciona</h3>
            <p class="pequeno mb-0 texto-2">
                <strong style="color:var(--sucesso)">Livre</strong> — mesa disponível ·
                <strong style="color:var(--perigo)">Ocupada</strong> — conta em curso ·
                <strong style="color:var(--aviso)">Reservada</strong> — reserva confirmada.
                Use o botão <em>Abrir</em> para iniciar um pedido ou <em>verde</em> para fechar a conta.
            </p>
        </div>
        <a href="pedidos.php" class="btn btn-primario"><?= icone('mais') ?> Novo pedido</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/rodape.php'; ?>
