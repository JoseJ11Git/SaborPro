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
</script>
