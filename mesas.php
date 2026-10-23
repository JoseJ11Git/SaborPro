<?php
/**
 * SaborPro :: mesas.php
 * -------------------------------------------------------------
 * PLANTA DE SALA 2D REALISTA
 * Mesas desenhadas em vista de cima (SVG inline) com cadeiras,
 * bancos de bar, cores por estado e tempo decorrido.
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

exigir_login();

/* ----------------------------- DADOS --------------------------------- */
$mesas = [];
$r = $bd->query("SELECT m.id, m.numero, m.capacidade, m.estado,
                        (SELECT p.id FROM pedidos p
                          WHERE p.mesa_id = m.id AND p.estado <> 'Entregue'
                          ORDER BY p.criado_em DESC LIMIT 1) AS pedido_ativo,
                        (SELECT COALESCE(SUM(p.total),0) FROM pedidos p
                          WHERE p.mesa_id = m.id AND p.estado <> 'Entregue') AS total_ativo,
                        (SELECT MIN(p.criado_em) FROM pedidos p
                          WHERE p.mesa_id = m.id AND p.estado <> 'Entregue') AS desde
                   FROM mesas m
                  WHERE m.numero > 0
                  ORDER BY m.numero");
while ($m = $r->fetch_assoc()) {
    $mesas[] = $m;
}
$r->free();

$itensMenu = [];
$r = $bd->query("SELECT id, nome, preco FROM ementa WHERE disponivel = 1 ORDER BY nome LIMIT 12");
while ($i = $r->fetch_assoc()) {
    $itensMenu[] = $i;
}
$r->free();

/**
 * Planta: posição central (%) + formato de cada mesa.
 * Vista única da sala, sem divisões por zonas.
 */
$planta = [
    1 => ['forma' => 'redonda',    'x' => 27, 'y' => 27],
    2 => ['forma' => 'redonda',    'x' => 54, 'y' => 25],
    3 => ['forma' => 'redonda',    'x' => 27, 'y' => 61],
    4 => ['forma' => 'redonda',    'x' => 54, 'y' => 59],
    5 => ['forma' => 'retangular', 'x' => 79, 'y' => 35],
    6 => ['forma' => 'retangular', 'x' => 79, 'y' => 72],
];

$resumo = ['Livre' => 0, 'Ocupada' => 0, 'Reservada' => 0];
foreach ($mesas as $m) {
    $resumo[$m['estado']]++;
}

/**
 * Desenha as cadeiras em redor de uma mesa redonda.
 * $cx/$cy = centro, $dist = raio das cadeiras.
 */
function cadeiras_redondas(int $n, float $cx, float $cy, float $dist): string
{
    $svg = '';
    for ($i = 0; $i < $n; $i++) {
        $ang = -90 + ($i * (360 / $n));
        $rad = deg2rad($ang);
        $x   = $cx + cos($rad) * $dist;
        $y   = $cy + sin($rad) * $dist;
        $rot = $ang + 90;
        $svg .= sprintf(
            '<g transform="translate(%.1f %.1f) rotate(%.1f)">'
            . '<rect x="-14" y="-9" width="28" height="18" rx="5" class="cadeira"/>'
            . '<rect x="-10" y="-6" width="20" height="12" rx="3" class="cadeira-encosto"/>'
            . '</g>',
            $x, $y, $rot
        );
    }
    return $svg;
}

/**
 * Desenha cadeiras ao longo dos dois lados de uma mesa retangular.
 */
function cadeiras_retangulares(int $n, float $x1, float $x2, float $ytopo, float $ybase): string
{
    $porLado = (int) ceil($n / 2);
    $svg = '';
    for ($lado = 0; $lado < 2; $lado++) {
        $y  = $lado === 0 ? $ytopo : $ybase;
        $gy = $lado === 0 ? -1 : 1;
        for ($i = 0; $i < $porLado; $i++) {
            $frac = ($i + 0.5) / $porLado;
            $x = $x1 + ($x2 - $x1) * $frac;
            $svg .= sprintf(
                '<g transform="translate(%.1f %.1f)">'
                . '<rect x="-15" y="%s" width="30" height="19" rx="5" class="cadeira"/>'
                . '<rect x="-11" y="%s" width="22" height="13" rx="3" class="cadeira-encosto"/>'
                . '</g>',
                $x, $y,
                $gy < 0 ? '-21' : '2',
                $gy < 0 ? '-17' : '7'
            );
        }
    }
    return $svg;
}

/** Devolve o SVG completo de uma mesa em madeira (vista de cima). */
function desenhar_mesa(array $m, string $forma): string
{
    $numero   = (int) $m['numero'];
    $lugares  = max(2, (int) $m['capacidade']);
    $gid      = 'madeira-' . $numero;

    $defs = '<defs>'
        . '<linearGradient id="' . $gid . '" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="#D9B382"/>'
        . '<stop offset=".42" stop-color="#C39360"/>'
        . '<stop offset=".72" stop-color="#B07C4A"/>'
        . '<stop offset="1" stop-color="#95643A"/>'
        . '</linearGradient>'
        . '</defs>';

    if ($forma === 'redonda') {
        $cx = 95; $cy = 95; $r = 50;
        $cadeiras = cadeiras_redondas(min($lugares, 6), $cx, $cy, 82);

        /* Veios de madeira (linhas suaves) */
        $riscos = '';
        for ($i = 0; $i < 6; $i++) {
            $off = -24 + $i * 9.5;
            $riscos .= sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" class="risco"/>',
                $cx - 34, (int) ($cy + $off), $cx + 34, (int) ($cy + $off)
            );
        }
        $riscos .= '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="30" ry="30" class="risco-aro"/>';
        $riscos .= '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="20" ry="20" class="risco-aro"/>';

        return '<svg class="mesa-svg" viewBox="0 0 190 190" role="img" aria-label="Mesa ' . $numero . '">'
            . $defs
            . '<ellipse cx="95" cy="104" rx="58" ry="15" fill="rgba(60,38,18,.14)"/>'
            . $cadeiras
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" class="tampo" style="fill:url(#' . $gid . ')"/>'
            . $riscos
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . ($r - 11) . '" class="aro-int"/>'
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" class="aro"/>'
            . '<text x="' . $cx . '" y="' . ($cy - 6) . '" class="num" font-size="30">' . $numero . '</text>'
            . '<text x="' . $cx . '" y="' . ($cy + 16) . '" class="lug" font-size="8.5">' . min($lugares, 6) . ' LUGARES</text>'
            . '</svg>';
    }

    // Mesa retangular / familiar
    $x = 62; $y = 52; $w = 136; $h = 66;
    $cadeiras = cadeiras_retangulares($lugares, $x + 6, $x + $w - 6, $y, $y + $h);

    $riscos = '';
    for ($i = 0; $i < 5; $i++) {
        $off = -22 + $i * 11;
        $riscos .= sprintf(
            '<line x1="%d" y1="%d" x2="%d" y2="%d" class="risco"/>',
            $x + 14, (int) ($y + $h / 2 + $off), $x + $w - 14, (int) ($y + $h / 2 + $off)
        );
    }

    return '<svg class="mesa-svg" viewBox="0 0 260 170" role="img" aria-label="Mesa ' . $numero . '">'
        . $defs
        . '<ellipse cx="130" cy="96" rx="76" ry="17" fill="rgba(60,38,18,.14)"/>'
        . $cadeiras
        . '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="9" class="tampo" style="fill:url(#' . $gid . ')"/>'
        . $riscos
        . '<rect x="' . ($x + 11) . '" y="' . ($y + 9) . '" width="' . ($w - 22) . '" height="' . ($h - 18) . '" rx="6" class="aro-int"/>'
        . '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="9" class="aro"/>'
        . '<text x="130" y="82" class="num" font-size="30">' . $numero . '</text>'
        . '<text x="130" y="101" class="lug" font-size="8.5">' . $lugares . ' LUGARES</text>'
        . '</svg>';
}

$paginaTitulo = 'Mesas';
$paginaAtiva  = 'mesas';

require_once __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina entre-topo">
    <div>
        <h1 class="mb-0">Mesas</h1>
        <p class="subtitulo">Vista única da sala · clique numa mesa para abrir a conta, adicionar pratos ou fechar.</p>
    </div>
    <div class="d-flex-wrap">
        <span class="etiqueta estado-pronto"><span class="ponto"></span><?= (int) $resumo['Livre'] ?> livres</span>
        <span class="etiqueta estado-pendente"><span class="ponto"></span><?= (int) $resumo['Ocupada'] ?> ocupadas</span>
        <span class="etiqueta estado-preparacao"><span class="ponto"></span><?= (int) $resumo['Reservada'] ?> reservadas</span>
        <a href="pedidos.php" class="btn btn-primario"><?= icone('mais') ?> Novo pedido</a>
    </div>
</div>

<div class="sala-envolvente">
    <div class="sala-topo">
        <div class="sala-legenda">
            <span><i class="livre"></i> Livre</span>
            <span><i class="ocupada"></i> Ocupada com pedido ativo</span>
            <span><i class="reservada"></i> Reservada</span>
        </div>
        <span class="pequeno texto-3">Actualizado às <?= date('H:i') ?> · <?= count($mesas) ?> mesas</span>
    </div>

    <!-- ===================== MESAS ===================== -->
    <div class="sala" id="sala">
        <?php foreach ($mesas as $i => $m): ?>
            <?php
            $p        = $planta[(int) $m['numero']] ?? ['forma' => 'redonda', 'x' => 50, 'y' => 50];
            $estado   = (string) $m['estado'];
            $classes  = $estado === 'Ocupada' ? 'estado-ocupada' : ($estado === 'Reservada' ? 'estado-reservada' : 'estado-livre');
            $total    = (float) $m['total_ativo'];
            ?>
            <button type="button"
                    class="mesa-planta <?= $classes ?>"
                    data-mesa="<?= (int) $m['id'] ?>"
                    data-numero="<?= (int) $m['numero'] ?>"
                    data-forma="<?= e($p['forma']) ?>"
                    data-lugares="<?= (int) $m['capacidade'] ?>"
                    style="left:<?= (float) $p['x'] ?>%;top:<?= (float) $p['y'] ?>%;animation-delay:<?= $i * 0.07 ?>s"
                    aria-label="Mesa <?= (int) $m['numero'] ?> — <?= e($estado) ?>">

                <?php if ($total > 0): ?>
                    <span class="mesa-valor"><?= dinheiro($total) ?></span>
                <?php endif; ?>

                <?= desenhar_mesa($m, $p['forma']) ?>

                <span class="mesa-indicador" title="<?= e($estado) ?>" aria-hidden="true"></span>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===================== MODAL DA MESA ===================== -->
<div class="modal" id="modalMesa" role="dialog" aria-modal="true">
    <div class="modal-caixa modal-largo">
        <button class="modal-fechar" data-modal-fechar aria-label="Fechar"><?= icone('x') ?></button>

        <div class="mesa-detalhe">
            <div class="cab-info">
                <div>
                    <h3 id="mesaTitulo" class="mb-0">Mesa</h3>
                    <span class="etiqueta badge-neutro" id="mesaEstado">—</span>
                </div>
                <div style="text-align:right">
                    <div class="pequeno texto-3">Total da conta</div>
                    <div class="negrito" id="mesaTotal" style="font-size:1.4rem;color:var(--primario)">0,00 €</div>
                </div>
            </div>

            <div>
                <p class="painel-titulo">Consumo da mesa</p>
                <ul class="lista-itens" id="mesaItens">
                    <li class="texto-3">A carregar…</li>
                </ul>
            </div>

            <div>
                <p class="painel-titulo">Adicionar à conta</p>
                <div class="adicionar-rapido" id="mesaAdicionar">
                    <?php foreach ($itensMenu as $it): ?>
                        <button type="button" data-item="<?= (int) $it['id'] ?>">
                            <span><?= e($it['nome']) ?></span>
                            <span><?= dinheiro((float) $it['preco']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="d-flex-wrap">
                <button class="btn btn-sucesso" id="mesaFechar"><?= icone('ok') ?> Fechar conta</button>
                <a href="#" class="btn btn-fantasma" id="mesaPos"><?= icone('carrinho') ?> Abrir no POS</a>
                <button class="btn btn-fantasma" id="mesaEstadoBtn"><?= icone('edicao') ?> Alterar estado</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var sala = document.getElementById('sala');
    var estadoAtual = 0;
    var estados = ['Livre', 'Ocupada', 'Reservada'];

    function baseApp() {
        var p = window.location.pathname.replace(/\\/g, '/');
        return p.replace(/\/[^/]*$/, '');
    }

    function abrirMesa(idMesa) {
        fetch(baseApp() + '/api/mesa.php?mesa=' + idMesa)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) return;
                estadoAtual = res.mesa.id;

                document.getElementById('mesaTitulo').textContent = 'Mesa ' + res.mesa.numero;
                var tag = document.getElementById('mesaEstado');
                tag.textContent = res.mesa.estado + ' · ' + res.mesa.capacidade + ' lugares';
                tag.className = 'etiqueta ' + (res.mesa.estado === 'Ocupada'
                    ? 'estado-pendente'
                    : res.mesa.estado === 'Reservada' ? 'estado-preparacao' : 'estado-pronto');

                document.getElementById('mesaTotal').textContent = window.saborproMoney(res.pedido ? res.pedido.total : 0);
                document.getElementById('mesaPos').href = 'pedidos.php?mesa=' + res.mesa.numero;

                var ul = document.getElementById('mesaItens');
                ul.innerHTML = '';
                if (!res.itens.length) {
                    var li = document.createElement('li');
                    li.className = 'texto-3';
                    li.textContent = 'Sem consumo registado nesta mesa.';
                    ul.appendChild(li);
                } else {
                    res.itens.forEach(function (i) {
                        var li = document.createElement('li');
                        var esq = document.createElement('span');
                        esq.textContent = i.quantidade + '× ' + i.nome;
                        var dir = document.createElement('span');
                        dir.className = 'texto-2';
                        dir.textContent = window.saborproMoney(i.quantidade * i.preco_unitario);
                        li.appendChild(esq);
                        li.appendChild(dir);
                        ul.appendChild(li);
                    });
                }

                document.getElementById('mesaAdicionar').onclick = function (ev) {
                    var b = ev.target.closest('[data-item]');
                    if (!b) return;
                    b.disabled = true;
                    acao({ acao: 'adicionar', mesa: estadoAtual, item: parseInt(b.getAttribute('data-item'), 10), qtd: 1 }, function () {
                        abrirMesa(estadoAtual);
                    });
                };

                document.getElementById('mesaFechar').onclick = function () {
                    acao({ acao: 'fechar', mesa: estadoAtual }, function () {
                        window.saborproFecharModal('modalMesa');
                        window.location.reload();
                    });
                };

                document.getElementById('mesaEstadoBtn').onclick = function () {
                    var proximo = estados[(estados.indexOf(res.mesa.estado) + 1) % estados.length];
                    acao({ acao: 'estado', mesa: estadoAtual, estado: proximo }, function () {
                        window.location.reload();
                    });
                };

                window.saborproAbrirModal('modalMesa');
            });
    }

    function acao(dados, ok) {
        fetch(baseApp() + '/api/mesa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) { if (res.ok) ok(res); });
    }

    if (sala) {
        sala.addEventListener('click', function (ev) {
            var m = ev.target.closest('.mesa-planta');
            if (m) abrirMesa(m.getAttribute('data-mesa'));
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/rodape.php'; ?>
