<?php
/**
 * SaborPro :: dashboard.php
 * Painel principal de administração com estatísticas e resumo de vendas.
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

exigir_login();

/* --------------------------- ESTATÍSTICAS --------------------------- */
$stats = [];

// Total faturado hoje
$r = $bd->query("SELECT COALESCE(SUM(total),0) AS v, COUNT(*) AS n
                   FROM pedidos WHERE DATE(criado_em) = CURDATE()");
$stats['faturamento'] = $r->fetch_assoc();
$r->free();

// Pedidos ativos (ainda não entregues)
$r = $bd->query("SELECT COUNT(*) AS n FROM pedidos WHERE estado <> 'Entregue'");
$stats['ativos'] = (int) $r->fetch_assoc()['n'];
$r->free();

// Ocupação de mesas
$r = $bd->query("SELECT
                    COUNT(*) AS total,
                    SUM(estado = 'Ocupada') AS ocupadas,
                    SUM(estado = 'Reservada') AS reservadas,
                    SUM(estado = 'Livre') AS livres
                FROM mesas");
$stats['mesas'] = $r->fetch_assoc();
$r->free();

// Prato mais vendido
$r = $bd->query("SELECT e.nome, SUM(i.quantidade) AS qtd
                   FROM itens_pedido i
                   JOIN ementa e ON e.id = i.ementa_id
                  GROUP BY e.id, e.nome
                  ORDER BY qtd DESC, e.nome ASC
                  LIMIT 1");
$stats['top'] = $r->fetch_assoc() ?: ['nome' => '—', 'qtd' => 0];
$r->free();

// Últimas vendas
$ultimasVendas = [];
$r = $bd->query("SELECT p.id, p.total, p.estado, p.criado_em, m.numero AS mesa, u.nome AS empregado
                   FROM pedidos p
                   JOIN mesas m ON m.id = p.mesa_id
                   JOIN utilizadores u ON u.id = p.utilizador_id
                  ORDER BY p.criado_em DESC
                  LIMIT 8");
while ($v = $r->fetch_assoc()) {
    $ultimasVendas[] = $v;
}
$r->free();

// Contagem de itens por estado (mini-gráfico)
$porEstado = ['Pendente' => 0, 'Em Preparação' => 0, 'Pronto' => 0, 'Entregue' => 0];
$r = $bd->query('SELECT estado, COUNT(*) AS n FROM pedidos GROUP BY estado');
while ($e = $r->fetch_assoc()) {
    $porEstado[$e['estado']] = (int) $e['n'];
}
$r->free();

$ocupacaoPct = $stats['mesas']['total'] > 0
    ? round(($stats['mesas']['ocupadas'] / $stats['mesas']['total']) * 100)
    : 0;

$paginaTitulo = 'Painel de Gestão';
$paginaAtiva  = 'dashboard';

$alertas = [];
if (isset($_GET['erro']) && $_GET['erro'] === 'permissao') {
    $alertas[] = ['aviso', 'Não tem permissão para aceder a essa área.'];
}
if (isset($_GET['msg']) && $_GET['msg'] === 'sucesso') {
    $alertas[] = ['ok', 'Operação concluída com sucesso.'];
}

require_once __DIR__ . '/includes/cabecalho.php';
?>

<!-- ===================== CABEÇALHO DA PÁGINA ===================== -->
<div class="cabecalho-pagina entre-topo">
    <div>
        <h1 class="mb-0">Olá, <?= e((string) (utilizador_atual()['nome'] ?? 'Utilizador')) ?></h1>
        <p class="subtitulo">Resumo do serviço de <?= date('d/m/Y') ?>.</p>
    </div>
    <div class="d-flex-wrap">
        <a href="pedidos.php" class="btn btn-primario"><?= icone('carrinho') ?> Novo pedido</a>
        <a href="mesas.php" class="btn btn-fantasma"><?= icone('mesa') ?> Ver mesas</a>
    </div>
</div>

<?php foreach ($alertas as [$tipo, $msg]): ?>
    <div class="alerta alerta-<?= $tipo ?>"><?= icone($tipo === 'ok' ? 'ok' : 'alerta') ?><div><?= e($msg) ?></div></div>
<?php endforeach; ?>

<!-- ===================== CARTÕES DE ESTATÍSTICA ===================== -->
<div class="grelha g-4 mb-3">
    <div class="cartao estatistica d1" style="--cor: var(--primario)">
        <div class="icone-estat"><?= icone('euro') ?></div>
        <p class="rotulo">Total Faturado Hoje</p>
        <div class="valor"><?= dinheiro((float) $stats['faturamento']['v']) ?></div>
        <p class="nota"><?= (int) $stats['faturamento']['n'] ?> pedido(s) registado(s) hoje</p>
    </div>

    <div class="cartao estatistica d2" style="--cor: var(--aviso)">
        <div class="icone-estat"><?= icone('relogio') ?></div>
        <p class="rotulo">Pedidos Ativos</p>
        <div class="valor"><?= (int) $stats['ativos'] ?></div>
        <p class="nota"><?= $porEstado['Pendente'] ?> pendente(s) · <?= $porEstado['Em Preparação'] ?> em preparação</p>
    </div>

    <div class="cartao estatistica d3" style="--cor: var(--sucesso)">
        <div class="icone-estat"><?= icone('mesa') ?></div>
        <p class="rotulo">Ocupação de Mesas</p>
        <div class="valor"><?= $ocupacaoPct ?>%</div>
        <p class="nota"><?= (int) $stats['mesas']['ocupadas'] ?> ocupada(s) · <?= (int) $stats['mesas']['reservadas'] ?> reservada(s) · <?= (int) $stats['mesas']['livres'] ?> livre(s)</p>
    </div>

    <div class="cartao estatistica d4" style="--cor: var(--info)">
        <div class="icone-estat"><?= icone('estrela') ?></div>
        <p class="rotulo">Prato Mais Vendido</p>
        <div class="valor" style="font-size:1.25rem;line-height:1.3"><?= e((string) $stats['top']['nome']) ?></div>
        <p class="nota"><?= (int) $stats['top']['qtd'] ?> unidade(s) servida(s)</p>
    </div>
</div>

<!-- ===================== GRÁFICO + ÚLTIMAS VENDAS ===================== -->
<div class="grelha" style="grid-template-columns: 1.4fr 1fr; align-items:start">

    <!-- Últimas vendas -->
    <div class="cartao">
        <div class="entre mb-2">
            <h3 class="mb-0">Últimas vendas</h3>
            <a href="pedidos.php" class="btn btn-fantasma btn-sm">Ver todos</a>
        </div>
        <div class="tabela-envolvente">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Mesa</th>
                        <th>Empregado</th>
                        <th>Estado</th>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$ultimasVendas): ?>
                    <tr><td colspan="5" class="texto-centro texto-3" style="padding:28px">Ainda não há pedidos registados.</td></tr>
                <?php else: ?>
                    <?php foreach ($ultimasVendas as $i => $v): ?>
                        <tr style="animation-delay: <?= $i * 0.04 ?>s">
                            <td class="texto-3">#<?= (int) $v['id'] ?></td>
                            <td class="negrito">Mesa <?= (int) $v['mesa'] ?></td>
                            <td class="texto-2"><?= e($v['empregado']) ?></td>
                            <td><span class="etiqueta <?= classe_estado((string) $v['estado']) ?>"><span class="ponto"></span><?= e($v['estado']) ?></span></td>
                            <td class="num negrito"><?= dinheiro((float) $v['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Distribuição de pedidos -->
    <div class="cartao">
        <h3>Estado dos pedidos</h3>
        <p class="pequeno texto-2">Distribuição global por estado.</p>

        <?php
        $totalPedidos = array_sum($porEstado) ?: 1;
        $cores = [
            'Pendente'      => 'var(--aviso)',
            'Em Preparação' => 'var(--info)',
            'Pronto'        => 'var(--sucesso)',
            'Entregue'      => 'var(--texto-3)',
        ];
        foreach ($porEstado as $estado => $n):
            $pct = round(($n / $totalPedidos) * 100);
        ?>
            <div class="mb-2">
                <div class="entre mb-1" style="font-size:.85rem">
                    <span class="texto-2"><?= e($estado) ?></span>
                    <span class="negrito"><?= (int) $n ?></span>
                </div>
                <div style="height:8px;border-radius:99px;background:var(--fundo-alt);overflow:hidden">
                    <div style="height:100%;width:<?= $pct ?>%;background:<?= $cores[$estado] ?>;border-radius:99px;transition:width .8s cubic-bezier(.4,0,.2,1)"></div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="cartao mt-3" style="padding:16px">
            <p class="rotulo mb-1">Atalhos</p>
            <div class="d-flex-wrap">
                <a href="pedidos.php?mesa=1" class="btn btn-fantasma btn-sm"><?= icone('mesa', '', 14) ?> Mesa 1</a>
                <a href="cozinha.php" class="btn btn-fantasma btn-sm"><?= icone('cozinha', '', 14) ?> Cozinha</a>
                <a href="ementa.php" class="btn btn-fantasma btn-sm"><?= icone('receita', '', 14) ?> Ementa</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/rodape.php'; ?>
