<?php
/**
 * SaborPro :: includes/painel_resumo.php
 * -------------------------------------------------------------
 * PAINEL TÉCNICO DE OPERAÇÕES (tema claro)
 *  · Métricas rápidas (faturação, ocupação, pendentes, ticket)
 *  · Monitor de pedidos do site com notificação visual
 *  · Controlo de ementa com preço editável e estado de stock
 *  · Últimas vendas + distribuição por estado
 */

$util = utilizador_atual();

/* ------------------------------ MÉTRICAS ------------------------------ */
$r = $bd->query("SELECT COALESCE(SUM(total),0) AS v, COUNT(*) AS n
                   FROM pedidos WHERE DATE(criado_em) = CURDATE()");
$faturacao = $r->fetch_assoc();
$r->free();

$r = $bd->query("SELECT COUNT(*) AS n FROM pedidos WHERE estado = 'Pendente'");
$pendentes = (int) $r->fetch_assoc()['n'];
$r->free();

$r = $bd->query("SELECT COUNT(*) AS n FROM pedidos WHERE estado IN ('Pendente','Em Preparação','Pronto')");
$ativos = (int) $r->fetch_assoc()['n'];
$r->free();

$r = $bd->query("SELECT
                    COUNT(*) AS total,
                    SUM(estado = 'Ocupada') AS ocupadas,
                    SUM(estado = 'Reservada') AS reservadas,
                    SUM(estado = 'Livre') AS livres
                 FROM mesas WHERE numero > 0");
$mesasResumo = $r->fetch_assoc();
$r->free();

$totalMesas = (int) ($mesasResumo['total'] ?? 0);
$pctOcupacao = $totalMesas > 0 ? (int) round(((int) $mesasResumo['ocupadas'] / $totalMesas) * 100) : 0;

$r = $bd->query('SELECT COALESCE(SUM(capacidade),0) AS c FROM mesas WHERE numero > 0');
$capacidadeTotal = (int) $r->fetch_assoc()['c'];
$r->free();

$ticketMedio = (int) $faturacao['n'] > 0 ? (float) $faturacao['v'] / (int) $faturacao['n'] : 0.0;

/* --------------------- PEDIDOS DO SITE (monitor) --------------------- */
$pedidosSite = [];
$r = $bd->query("SELECT p.id, p.total, p.estado, p.tipo_pedido, p.cliente_nome, p.cliente_telefone,
                        p.criado_em, m.numero AS mesa
                   FROM pedidos p JOIN mesas m ON m.id = p.mesa_id
                  WHERE p.tipo_pedido = 'Takeaway' OR p.cliente_nome IS NOT NULL
                  ORDER BY p.criado_em DESC LIMIT 6");
while ($x = $r->fetch_assoc()) {
    $pedidosSite[] = $x;
}
$r->free();

$novosSite = 0;
foreach ($pedidosSite as $ps) {
    if ($ps['estado'] === 'Pendente' && $ps['tipo_pedido'] === 'Takeaway') {
        $novosSite++;
    }
}

/* -------------------------- CONTROLO DE EMENTA -------------------------- */
$ementaControlo = [];
$r = $bd->query("SELECT e.id, e.nome, e.preco, e.disponivel, c.nome AS categoria
                   FROM ementa e JOIN categorias c ON c.id = e.categoria_id
                  ORDER BY e.disponivel DESC, c.nome, e.nome");
while ($x = $r->fetch_assoc()) {
    $ementaControlo[] = $x;
}
$r->free();

/* ----------------------------- ÚLTIMAS VENDAS ----------------------------- */
$ultimasVendas = [];
$r = $bd->query("SELECT p.id, p.total, p.estado, p.tipo_pedido, p.cliente_nome, p.criado_em,
                        m.numero AS mesa
                   FROM pedidos p JOIN mesas m ON m.id = p.mesa_id
                  ORDER BY p.criado_em DESC LIMIT 7");
while ($x = $r->fetch_assoc()) {
    $ultimasVendas[] = $x;
}
$r->free();

/* --------------------------- DISTRIBUIÇÃO --------------------------- */
$porEstado = ['Pendente' => 0, 'Em Preparação' => 0, 'Pronto' => 0, 'Entregue' => 0];
$r = $bd->query('SELECT estado, COUNT(*) AS n FROM pedidos GROUP BY estado');
while ($x = $r->fetch_assoc()) {
    $porEstado[$x['estado']] = (int) $x['n'];
}
$r->free();
$totalPedidos = array_sum($porEstado) ?: 1;

/* --------------------- GRÁFICO: PEDIDOS POR HORA --------------------- */
$porHora = [];
$r = $bd->query("SELECT HOUR(criado_em) AS h, COUNT(*) AS n, COALESCE(SUM(total),0) AS v
                   FROM pedidos
                  WHERE DATE(criado_em) = CURDATE()
                  GROUP BY HOUR(criado_em)");
while ($x = $r->fetch_assoc()) {
    $porHora[(int) $x['h']] = ['n' => (int) $x['n'], 'v' => (float) $x['v']];
}
$r->free();

$horasGrafico = range(0, 23);
$maxHora      = 1;
$horaPico     = null;
$pedidosHoje  = 0;
foreach ($horasGrafico as $h) {
    $n = $porHora[$h]['n'] ?? 0;
    $pedidosHoje += $n;
    if ($n > $maxHora) {
        $maxHora = $n;
    }
    if ($horaPico === null || $n > ($porHora[$horaPico]['n'] ?? 0)) {
        $horaPico = $h;
    }
}
if ($pedidosHoje === 0) {
    $horaPico = null;
}

/* ------------------ GRÁFICO: TOP PRATOS (30 DIAS) ------------------ */
$topPratos = [];
$r = $bd->query("SELECT e.nome, SUM(i.quantidade) AS qtd
                   FROM itens_pedido i
                   JOIN ementa e ON e.id = i.ementa_id
                   JOIN pedidos p ON p.id = i.pedido_id
                  WHERE p.criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  GROUP BY e.id, e.nome
                  ORDER BY qtd DESC
                  LIMIT 5");
while ($x = $r->fetch_assoc()) {
    $topPratos[] = ['nome' => (string) $x['nome'], 'qtd' => (int) $x['qtd']];
}
$r->free();

$totalTopPratos = array_sum(array_column($topPratos, 'qtd'));
$coresPratos = ['#D97706', '#991B1B', '#0284C7', '#059669', '#7C3AED'];
$raioDonut   = 58;
$circunferencia = 2 * M_PI * $raioDonut;
$acumulado = 0.0;

/* ------------------------- AVALIAÇÕES (gestão) ------------------------- */
$avaliacoesAdmin = [];
$r = $bd->query("SELECT id, nome_cliente, classificacao, comentario, criado_em, visivel
                   FROM avaliacoes
                  ORDER BY visivel ASC, criado_em DESC
                  LIMIT 30");
while ($x = $r->fetch_assoc()) {
    $avaliacoesAdmin[] = $x;
}
$r->free();

$r = $bd->query("SELECT COUNT(*) AS total, COALESCE(AVG(classificacao),0) AS media,
                        SUM(visivel = 0) AS pendentes
                   FROM avaliacoes");
$resAval = $r->fetch_assoc();
$r->free();

$avalPendentes = (int) ($resAval['pendentes'] ?? 0);
$avalTotal     = (int) ($resAval['total'] ?? 0);
$avalMedia     = $avalTotal > 0 ? round((float) $resAval['media'], 1) : 0.0;
?>

<!-- ===================== CABEÇALHO DO PAINEL ===================== -->
<div class="kpi-topo">
    <div class="titulo-bloco">
        <h1>Painel de operações</h1>
        <p class="texto-2">
            <?= e((string) ($util['nome'] ?? 'Utilizador')) ?> ·
            <?= date('d/m/Y') ?> ·
            <span class="negrito"><?= (int) $mesasResumo['livres'] ?> mesas livres</span>
        </p>
    </div>
    <div class="d-flex-wrap">
        <a href="pedidos.php" class="btn btn-primario"><?= icone('carrinho') ?> Novo pedido</a>
        <a href="mesas.php" class="btn btn-fantasma"><?= icone('sala') ?> Planta da sala</a>
        <a href="cozinha.php" class="btn btn-fantasma"><?= icone('cozinha') ?> Cozinha</a>
    </div>
</div>

<?php foreach (($alertas ?? []) as $al): ?>
    <div class="alerta alerta-<?= e($al[0]) ?>">
        <?= icone($al[0] === 'ok' ? 'ok' : 'alerta') ?><div><?= e($al[1]) ?></div>
    </div>
<?php endforeach; ?>

<?php if ($novosSite > 0): ?>
    <div class="faixa-novos">
        <span class="ponto-pulso"></span>
        <span><strong><?= (int) $novosSite ?> pedido(s) novo(s) do site</strong> à espera de confirmação — verifique o ecrã da cozinha.</span>
        <a href="cozinha.php" class="btn btn-sm btn-fantasma" style="margin-left:auto">Abrir cozinha</a>
    </div>
<?php endif; ?>

<!-- ===================== MÉTRICAS ===================== -->
<div class="grelha g-4 mb-2">
    <div class="cartao estatistica" style="--cor:#1D4ED8">
        <div class="icone-estat"><?= icone('euro') ?></div>
        <p class="rotulo">Faturação do dia</p>
        <div class="valor"><?= dinheiro((float) $faturacao['v']) ?></div>
        <p class="nota"><?= (int) $faturacao['n'] ?> pedido(s) · ticket <?= dinheiro($ticketMedio) ?></p>
    </div>

    <div class="cartao estatistica" style="--cor:#059669">
        <div class="icone-estat"><?= icone('sala') ?></div>
        <p class="rotulo">Ocupação da sala</p>
        <div class="valor"><?= $pctOcupacao ?>%</div>
        <div style="height:5px;border-radius:99px;background:#F1F5F9;overflow:hidden;margin:8px 0 6px">
            <div style="height:100%;width:<?= $pctOcupacao ?>%;background:#059669;border-radius:99px"></div>
        </div>
        <p class="nota"><?= (int) $mesasResumo['ocupadas'] ?> ocupadas · <?= (int) $mesasResumo['reservadas'] ?> reservadas · <?= (int) $mesasResumo['livres'] ?> livres</p>
    </div>

    <div class="cartao estatistica" style="--cor:#D97706">
        <div class="icone-estat"><?= icone('relogio') ?></div>
        <p class="rotulo">Pedidos pendentes</p>
        <div class="valor"><?= $pendentes ?></div>
        <p class="nota"><?= $ativos ?> ativo(s) no total · <?= $porEstado['Em Preparação'] ?> em preparação</p>
    </div>

    <div class="cartao estatistica" style="--cor:#0284C7">
        <div class="icone-estat"><?= icone('saco') ?></div>
        <p class="rotulo">Encomendas do site</p>
        <div class="valor"><?= (int) $novosSite ?></div>
        <p class="nota">Takeaway por confirmar · actualiza a cada 90 s</p>
    </div>
</div>

<!-- ===================== MONITOR + EMENTA ===================== -->
<div class="grelha" style="grid-template-columns:1fr 1.35fr;align-items:start">

    <!-- Monitor de pedidos do site -->
    <div class="cartao">
        <div class="entre mb-2">
            <div>
                <h3 class="mb-0" style="font-size:.98rem">Pedidos do site</h3>
                <p class="pequeno texto-3 mb-0" style="margin:2px 0 0">Recebidos sem login</p>
            </div>
            <a href="cozinha.php" class="btn btn-fantasma btn-sm" style="margin-left:auto">Ver cozinha</a>
        </div>

        <?php if (!$pedidosSite): ?>
            <div class="vazio"><?= icone('saco') ?>Ainda sem encomendas do site.</div>
        <?php else: ?>
            <div class="pedido-monitor">
                <?php foreach ($pedidosSite as $ps): ?>
                    <?php $t = tempo_decorrido((string) $ps['criado_em']); ?>
                    <div class="pedido-monitor-item <?= ($ps['estado'] === 'Pendente' && $ps['tipo_pedido'] === 'Takeaway') ? 'novo' : '' ?>">
                        <div class="pmo-info">
                            <div class="pmo-titulo">
                                #<?= (int) $ps['id'] ?> · <?= e((string) ($ps['cliente_nome'] ?: 'Cliente')) ?>
                                <span class="tag <?= $ps['tipo_pedido'] === 'Takeaway' ? 'tag-sem-gluten' : 'tag-neutra' ?>">
                                    <?= e((string) $ps['tipo_pedido']) ?>
                                </span>
                            </div>
                            <div class="pmo-sub">
                                <?= $ps['tipo_pedido'] === 'Takeaway' ? 'Balcão' : 'Mesa ' . (int) $ps['mesa'] ?>
                                · <?= e($t['texto']) ?>
                                <?php if ($ps['cliente_telefone']): ?> · <?= e((string) $ps['cliente_telefone']) ?><?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align:right">
                            <div class="pmo-valor"><?= dinheiro((float) $ps['total']) ?></div>
                            <span class="etiqueta <?= classe_estado((string) $ps['estado']) ?>" style="margin-top:3px">
                                <span class="ponto"></span><?= e((string) $ps['estado']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Distribuição por estado -->
        <div class="mt-3" style="border-top:1px solid var(--borda);padding-top:14px">
            <p class="painel-titulo">Fluxo de pedidos</p>
            <?php
            $cores = [
                'Pendente'      => '#D97706',
                'Em Preparação' => '#0284C7',
                'Pronto'        => '#059669',
                'Entregue'      => '#94A3B8',
            ];
            foreach ($porEstado as $estado => $n):
                $pct = (int) round(($n / $totalPedidos) * 100);
            ?>
                <div style="margin-bottom:8px">
                    <div class="entre" style="font-size:.74rem;margin-bottom:3px">
                        <span class="texto-2"><?= e($estado) ?></span>
                        <span class="negrito" style="margin-left:auto"><?= (int) $n ?></span>
                    </div>
                    <div style="height:5px;border-radius:99px;background:#F1F5F9;overflow:hidden">
                        <div style="height:100%;width:<?= $pct ?>%;background:<?= $cores[$estado] ?>;border-radius:99px;transition:width .8s ease"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Controlo de ementa -->
    <div class="cartao">
        <div class="entre mb-2">
            <div>
                <h3 class="mb-0" style="font-size:.98rem">Controlo de ementa</h3>
                <p class="pequeno texto-3 mb-0" style="margin:2px 0 0">Preço e stock editáveis em linha</p>
            </div>
            <a href="ementa.php" class="btn btn-fantasma btn-sm" style="margin-left:auto">Gerir tudo</a>
        </div>

        <div class="tabela-envolvente" style="border:0">
            <table class="tabela tabela-compacta" style="min-width:480px">
                <thead>
                    <tr>
                        <th>Prato</th>
                        <th>Categoria</th>
                        <th class="num">Preço</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ementaControlo as $item): ?>
                    <tr data-item="<?= (int) $item['id'] ?>">
                        <td>
                            <div class="negrito" style="font-size:.82rem"><?= e($item['nome']) ?></div>
                        </td>
                        <td class="texto-3" style="font-size:.74rem"><?= e($item['categoria']) ?></td>
                        <td class="num">
                            <div class="controlo-linha" style="justify-content:flex-end">
                                <input type="number" step="0.10" min="0.10"
                                       value="<?= e(number_format((float) $item['preco'], 2, '.', '')) ?>"
                                       data-preco="<?= (int) $item['id'] ?>"
                                       aria-label="Preço de <?= e($item['nome']) ?>">
                                <span class="texto-3" style="font-size:.72rem">€</span>
                            </div>
                        </td>
                        <td>
                            <button type="button"
                                    class="stock-toggle <?= (int) $item['disponivel'] === 1 ? 'ativo' : '' ?>"
                                    data-stock="<?= (int) $item['id'] ?>"
                                    data-estado="<?= (int) $item['disponivel'] ?>">
                                <span class="stock-interruptor"></span>
                                <?= (int) $item['disponivel'] === 1 ? 'Disponível' : 'Esgotado' ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===================== ÚLTIMAS VENDAS ===================== -->
<div class="cartao mt-2">
    <div class="entre mb-2">
        <h3 class="mb-0" style="font-size:.98rem">Últimas vendas</h3>
        <a href="pedidos.php" class="btn btn-fantasma btn-sm" style="margin-left:auto">Abrir POS</a>
    </div>

    <div class="tabela-envolvente" style="border:0">
        <table class="tabela tabela-compacta">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Mesa</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Hora</th>
                    <th>Estado</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$ultimasVendas): ?>
                <tr><td colspan="7" class="texto-centro texto-3" style="padding:22px">Ainda não há pedidos registados.</td></tr>
            <?php else: ?>
                <?php foreach ($ultimasVendas as $v): ?>
                    <tr>
                        <td class="texto-3">#<?= (int) $v['id'] ?></td>
                        <td class="negrito"><?= (int) $v['mesa'] === 0 ? 'Balcão' : 'Mesa ' . (int) $v['mesa'] ?></td>
                        <td class="texto-2"><?= e((string) ($v['cliente_nome'] ?: '—')) ?></td>
                        <td>
                            <span class="etiqueta <?= $v['tipo_pedido'] === 'Takeaway' ? 'badge-takeaway' : 'badge-neutro' ?>">
                                <?= $v['tipo_pedido'] === 'Takeaway' ? icone('saco', '', 11) : icone('mesa', '', 11) ?>
                                <?= e((string) $v['tipo_pedido']) ?>
                            </span>
                        </td>
                        <td class="texto-3" style="font-size:.76rem"><?= date('H:i', strtotime((string) $v['criado_em'])) ?></td>
                        <td><span class="etiqueta <?= classe_estado((string) $v['estado']) ?>"><span class="ponto"></span><?= e((string) $v['estado']) ?></span></td>
                        <td class="num negrito"><?= dinheiro((float) $v['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================== GRÁFICOS (SVG / CSS PUROS) ===================== -->
<div class="cartao grafico-cartao mt-2">
    <div class="entre mb-2">
        <div>
            <h3 class="mb-0" style="font-size:.98rem">Vendas por hora</h3>
            <p class="pequeno texto-3 mb-0" style="margin:2px 0 0">Hoje · pedidos acumulados em cada hora</p>
        </div>
        <?php if ($horaPico !== null): ?>
            <span class="etiqueta badge-neutro">
                Pico às <?= (int) $horaPico ?>h · <?= (int) ($porHora[$horaPico]['n'] ?? 0) ?> pedidos
            </span>
        <?php endif; ?>
    </div>

    <div class="barras-hora" role="img" aria-label="Gráfico de pedidos por hora de hoje">
        <?php foreach ($horasGrafico as $h): ?>
            <?php
            $n   = $porHora[$h]['n'] ?? 0;
            $v   = $porHora[$h]['v'] ?? 0.0;
            $alt = $n > 0 ? max(6, (int) round(($n / $maxHora) * 100)) : 0;
            ?>
            <div class="barra-col<?= $n > 0 ? ' com-dados' : '' ?>"
                 title="<?= $h ?>h00 · <?= $n ?> pedido(s) · <?= dinheiro((float) $v) ?>">
                <span class="barra-valor"><?= $n > 0 ? $n : '' ?></span>
                <div class="barra-trilho">
                    <div class="barra-preenchida" style="height:<?= $alt ?>%"></div>
                </div>
                <span class="barra-hora"><?= $h ?>h</span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($pedidosHoje === 0): ?>
        <p class="vazio" style="padding:14px 0 0">Ainda não existem pedidos hoje.</p>
    <?php endif; ?>
</div>

<div class="grelha g-2 mt-2">
    <!-- Ocupação da sala -->
    <div class="cartao grafico-cartao">
        <div class="entre mb-2">
            <div>
                <h3 class="mb-0" style="font-size:.98rem">Ocupação da sala</h3>
                <p class="pequeno texto-3 mb-0" style="margin:2px 0 0">Mesas com pedido ativo</p>
            </div>
            <span class="etiqueta <?= $pctOcupacao >= 80 ? 'estado-pendente' : 'estado-pronto' ?>">
                <span class="ponto"></span><?= $pctOcupacao ?>%
            </span>
        </div>

        <div class="donut-envolvente">
            <svg viewBox="0 0 160 160" class="donut" role="img" aria-label="Ocupação da sala: <?= $pctOcupacao ?>%">
                <circle cx="80" cy="80" r="<?= $raioDonut ?>" class="donut-trilho"/>
                <circle cx="80" cy="80" r="<?= $raioDonut ?>" class="donut-segmento"
                        style="stroke:#059669;stroke-dasharray:<?= round($circunferencia * $pctOcupacao / 100, 2) ?> <?= round($circunferencia, 2) ?>"/>
                <text x="80" y="76" class="donut-num"><?= $pctOcupacao ?>%</text>
                <text x="80" y="95" class="donut-mini">ocupação</text>
            </svg>

            <div class="donut-legenda">
                <div class="donut-legenda-item">
                    <span class="ponto-cor" style="background:#059669"></span>
                    <span>Ocupadas</span>
                    <strong><?= (int) $mesasResumo['ocupadas'] ?></strong>
                </div>
                <div class="donut-legenda-item">
                    <span class="ponto-cor" style="background:#D97706"></span>
                    <span>Reservadas</span>
                    <strong><?= (int) $mesasResumo['reservadas'] ?></strong>
                </div>
                <div class="donut-legenda-item">
                    <span class="ponto-cor" style="background:#CBD5E1"></span>
                    <span>Livres</span>
                    <strong><?= (int) $mesasResumo['livres'] ?></strong>
                </div>
                <p class="pequeno texto-3" style="margin:8px 0 0">
                    <?= $totalMesas ?> mesas na sala · capacidade para
                    <?= $capacidadeTotal ?> pessoas
                </p>
            </div>
        </div>
    </div>

    <!-- Top pratos -->
    <div class="cartao grafico-cartao">
        <div class="entre mb-2">
            <div>
                <h3 class="mb-0" style="font-size:.98rem">Pratos mais vendidos</h3>
                <p class="pequeno texto-3 mb-0" style="margin:2px 0 0">Últimos 30 dias · distribuição</p>
            </div>
            <span class="etiqueta badge-neutro"><?= (int) $totalTopPratos ?> doses</span>
        </div>

        <?php if (!$topPratos): ?>
            <div class="vazio" style="padding:32px 10px">Sem vendas nos últimos 30 dias.</div>
        <?php else: ?>
            <div class="donut-envolvente">
                <svg viewBox="0 0 160 160" class="donut" role="img" aria-label="Distribuição dos pratos mais vendidos">
                    <circle cx="80" cy="80" r="<?= $raioDonut ?>" class="donut-trilho"/>
                    <?php foreach ($topPratos as $idx => $tp): ?>
                        <?php
                        $pct  = $totalTopPratos > 0 ? ($tp['qtd'] / $totalTopPratos) * 100 : 0;
                        $len  = ($pct / 100) * $circunferencia;
                        $cor  = $coresPratos[$idx % count($coresPratos)];
                        $off  = -(($acumulado / 100) * $circunferencia);
                        $acumulado += $pct;
                        ?>
                        <circle cx="80" cy="80" r="<?= $raioDonut ?>"
                                class="donut-segmento"
                                style="stroke:<?= $cor ?>;stroke-dasharray:<?= round(max(0, $len - 2.4), 2) ?> <?= round($circunferencia, 2) ?>;stroke-dashoffset:<?= round($off, 2) ?>"/>
                    <?php endforeach; ?>
                    <text x="80" y="76" class="donut-num"><?= (int) $totalTopPratos ?></text>
                    <text x="80" y="95" class="donut-mini">doses</text>
                </svg>

                <div class="donut-legenda">
                    <?php foreach ($topPratos as $idx => $tp): ?>
                        <?php $pct = $totalTopPratos > 0 ? round(($tp['qtd'] / $totalTopPratos) * 100) : 0; ?>
                        <div class="donut-legenda-item">
                            <span class="ponto-cor" style="background:<?= $coresPratos[$idx % count($coresPratos)] ?>"></span>
                            <span class="txt-corte"><?= e($tp['nome']) ?></span>
                            <strong><?= $pct ?>%</strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===================== AVALIAÇÕES (MODERAÇÃO) ===================== -->
<div class="cartao mt-2" id="painelAvaliacoes">
    <div class="entre mb-2">
        <div>
            <h3 class="mb-0" style="font-size:.98rem">Avaliações dos clientes</h3>
            <p class="pequeno texto-3 mb-0" style="margin:2px 0 0">
                Modere o que é publicado no site público
            </p>
        </div>
        <div class="d-flex-wrap" style="align-items:center">
            <span class="etiqueta badge-neutro"><?= estrelas($avalMedia, 12) ?> <?= number_format($avalMedia, 1, ',', '.') ?></span>
            <?php if ($avalPendentes > 0): ?>
                <span class="etiqueta estado-pendente">
                    <span class="ponto"></span><?= $avalPendentes ?> por moderar
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="tabela-envolvente" style="border:0">
        <table class="tabela tabela-compacta" style="min-width:700px">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Nota</th>
                    <th>Comentário</th>
                    <th>Data</th>
                    <th>Estado</th>
                    <th class="num">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$avaliacoesAdmin): ?>
                <tr><td colspan="6" class="texto-centro texto-3" style="padding:24px">Ainda não existem avaliações.</td></tr>
            <?php else: ?>
                <?php foreach ($avaliacoesAdmin as $a): ?>
                    <tr data-aval-id="<?= (int) $a['id'] ?>">
                        <td>
                            <div class="negrito" style="font-size:.82rem"><?= e($a['nome_cliente']) ?></div>
                            <div class="texto-3" style="font-size:.7rem">#<?= (int) $a['id'] ?></div>
                        </td>
                        <td><?= estrelas((float) $a['classificacao'], 12) ?></td>
                        <td style="max-width:340px">
                            <div class="txt-corte-2" style="font-size:.8rem"><?= e($a['comentario']) ?></div>
                        </td>
                        <td class="texto-3" style="font-size:.74rem;white-space:nowrap">
                            <?= date('d/m/Y H:i', strtotime((string) $a['criado_em'])) ?>
                        </td>
                        <td>
                            <span class="etiqueta <?= (int) $a['visivel'] === 1 ? 'estado-pronto' : 'estado-pendente' ?>"
                                  data-aval-estado>
                                <span class="ponto"></span><?= (int) $a['visivel'] === 1 ? 'Publicada' : 'Em moderação' ?>
                            </span>
                        </td>
                        <td class="num">
                            <div style="display:flex;gap:6px;justify-content:flex-end">
                                <button type="button" class="btn btn-fantasma btn-sm"
                                        data-aval-visivel="<?= (int) $a['id'] ?>"
                                        data-estado="<?= (int) $a['visivel'] ?>"
                                        title="<?= (int) $a['visivel'] === 1 ? 'Ocultar do site' : 'Publicar no site' ?>">
                                    <?= (int) $a['visivel'] === 1 ? icone('olho', '', 14) : icone('ok', '', 14) ?>
                                    <?= (int) $a['visivel'] === 1 ? 'Ocultar' : 'Publicar' ?>
                                </button>
                                <button type="button" class="btn btn-perigo btn-sm"
                                        data-aval-eliminar="<?= (int) $a['id'] ?>"
                                        title="Eliminar avaliação">
                                    <?= icone('lixo', '', 14) ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================== EDIÇÃO RÁPIDA (JS) ===================== -->
<script>
(function () {
    var base = window.saborproBase ? window.saborproBase() : '';

    function guardar(id, campo, valor, concluir) {
        fetch(base + '/api/ementa_rapida.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, campo: campo, valor: valor })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) { concluir(res); })
        .catch(function () { concluir({ ok: false }); });
    }

    /* Actualização de preço ao sair do campo / Enter */
    document.querySelectorAll('[data-preco]').forEach(function (input) {
        var original = input.value;

        function aplicar() {
            var v = parseFloat(input.value);
            if (isNaN(v) || v <= 0) { input.value = original; return; }
            if (v === parseFloat(original)) return;

            guardar(input.getAttribute('data-preco'), 'preco', v, function (res) {
                if (res && res.ok) {
                    original = v.toFixed(2);
                    input.value = original;
                    input.style.borderColor = '#059669';
                    setTimeout(function () { input.style.borderColor = ''; }, 1200);
                    if (window.saborproToast) window.saborproToast('Preço actualizado.', v.toFixed(2) + ' €');
                } else {
                    input.value = original;
                    if (window.saborproToast) window.saborproToast('Não foi possível actualizar o preço.');
                }
            });
        }

        input.addEventListener('change', aplicar);
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
        });
    });

    /* Alternar stock */
    document.querySelectorAll('[data-stock]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var novo = btn.getAttribute('data-estado') === '1' ? 0 : 1;
            btn.disabled = true;
            guardar(btn.getAttribute('data-stock'), 'disponivel', novo, function (res) {
                btn.disabled = false;
                if (!res || !res.ok) return;
                btn.setAttribute('data-estado', String(novo));
                btn.classList.toggle('ativo', novo === 1);
                btn.lastChild.textContent = novo === 1 ? 'Disponível' : 'Esgotado';
                if (window.saborproToast) {
                    window.saborproToast(novo === 1 ? 'Item reposto em stock.' : 'Item marcado como esgotado.');
                }
            });
        });
    });
})();

/* ================= MODERAÇÃO DE AVALIAÇÕES ================= */
(function () {
    var base = window.saborproBase ? window.saborproBase() : '';

    function pedido(dados, concluir) {
        fetch(base + '/api/avaliacao_admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) { concluir(res); })
        .catch(function () { concluir({ ok: false }); });
    }

    document.querySelectorAll('[data-aval-visivel]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-aval-visivel');
            var novo = btn.getAttribute('data-estado') === '1' ? 0 : 1;
            btn.disabled = true;

            pedido({ id: Number(id), acao: 'visivel', valor: novo }, function (res) {
                btn.disabled = false;
                if (!res || !res.ok) {
                    if (window.saborproToast) window.saborproToast('Não foi possível actualizar a avaliação.');
                    return;
                }

                btn.setAttribute('data-estado', String(novo));
                btn.textContent = novo === 1 ? 'Ocultar' : 'Publicar';
                btn.title = novo === 1 ? 'Ocultar do site' : 'Publicar no site';

                var linha = btn.closest('tr');
                var tag = linha ? linha.querySelector('[data-aval-estado]') : null;
                if (tag) {
                    tag.className = 'etiqueta ' + (novo === 1 ? 'estado-pronto' : 'estado-pendente');
                    tag.innerHTML = '<span class="ponto"></span>' + (novo === 1 ? 'Publicada' : 'Em moderação');
                }

                if (window.saborproToast) {
                    window.saborproToast(novo === 1 ? 'Avaliação publicada no site.' : 'Avaliação ocultada do site.');
                }
            });
        });
    });

    document.querySelectorAll('[data-aval-eliminar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!window.confirm('Eliminar definitivamente esta avaliação?')) return;

            var id = btn.getAttribute('data-aval-eliminar');
            btn.disabled = true;

            pedido({ id: Number(id), acao: 'eliminar' }, function (res) {
                if (!res || !res.ok) {
                    btn.disabled = false;
                    if (window.saborproToast) window.saborproToast('Não foi possível eliminar a avaliação.');
                    return;
                }
                var linha = btn.closest('tr');
                if (linha) linha.remove();
                if (window.saborproToast) window.saborproToast('Avaliação eliminada.');
            });
        });
    });
})();
</script>
