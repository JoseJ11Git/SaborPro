<?php
/**
 * SaborPro :: mesas.php
 * -------------------------------------------------------------
 * SIMULADOR DE PLANTA DE SALA EM 2D
 * Mesas redondas e retangulares dispostas no espaço físico da sala.
 * Clicar numa mesa abre um modal com a conta, opção de adicionar
 * itens e de fechar a conta.
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
                         (SELECT COUNT(*) FROM itens_pedido i JOIN pedidos p2 ON p2.id = i.pedido_id
                           WHERE p2.mesa_id = m.id AND p2.estado <> 'Entregue') AS nr_itens
                    FROM mesas m
                   WHERE m.numero > 0
                   ORDER BY m.numero");
while ($m = $r->fetch_assoc()) {
    $mesas[] = $m;
}
$r->free();

// Ementa disponível para adicionar à mesa (atalho rápido)
$itensMenu = [];
$r = $bd->query("SELECT id, nome, preco FROM ementa WHERE disponivel = 1 ORDER BY nome LIMIT 12");
while ($i = $r->fetch_assoc()) {
    $itensMenu[] = $i;
}
$r->free();

/**
 * Planta da sala: posição (x, y em %) e formato de cada mesa.
 * Substitua estes valores para descrever a sala real do restaurante.
 */
$planta = [
    1 => ['forma' => 'retangular', 'x' => 30, 'y' => 22],
    2 => ['forma' => 'redonda',    'x' => 52, 'y' => 20],
    3 => ['forma' => 'redonda',    'x' => 52, 'y' => 50],
    4 => ['forma' => 'retangular', 'x' => 30, 'y' => 52],
    5 => ['forma' => 'retangular', 'x' => 52, 'y' => 80],
    6 => ['forma' => 'redonda',    'x' => 30, 'y' => 82],
];

$resumo = ['Livre' => 0, 'Ocupada' => 0, 'Reservada' => 0];
foreach ($mesas as $m) {
    $resumo[$m['estado']]++;
}

$paginaTitulo = 'Planta da Sala 2D';
$paginaAtiva  = 'mesas';

require_once __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina entre-topo">
    <div>
        <h1 class="mb-0">Planta da Sala</h1>
        <p class="subtitulo">Clique numa mesa para ver a conta, adicionar itens ou fechar o pedido.</p>
    </div>
    <div class="d-flex-wrap">
        <span class="etiqueta estado-pronto"><span class="ponto"></span><?= (int) $resumo['Livre'] ?> Livre(s)</span>
        <span class="etiqueta estado-pendente"><span class="ponto"></span><?= (int) $resumo['Ocupada'] ?> Ocupada(s)</span>
        <span class="etiqueta badge-neutro"><?= (int) $resumo['Reservada'] ?> Reservada(s)</span>
        <a href="pedidos.php" class="btn btn-primario"><?= icone('mais') ?> Novo pedido</a>
    </div>
</div>

<!-- ===================== PLANTA 2D ===================== -->
<div class="sala" id="sala">
    <!-- Elementos fixos da sala -->
    <div class="sala-elemento" style="left:85%;top:6%;width:13%;height:22%">
        <?= icone('cozinha') ?><br>Cozinha
    </div>
    <div class="sala-elemento" style="left:85%;top:32%;width:13%;height:30%">
        <?= icone('balcao') ?><br>Balcão
    </div>
    <div class="sala-elemento" style="left:3%;top:6%;width:14%;height:24%">
        <?= icone('janela') ?><br>Varanda
    </div>
    <div class="sala-elemento" style="left:4%;top:44%;width:12%;height:18%">
        <?= icone('copo') ?><br>Café
    </div>

    <!-- Mesas -->
    <?php foreach ($mesas as $i => $m): ?>
        <?php
        $p = $planta[(int) $m['numero']] ?? ['forma' => 'redonda', 'x' => 50, 'y' => 50];
        $classeEstado = match ($m['estado']) {
            'Ocupada'   => 'ocupada',
            'Reservada' => 'reservada',
            default     => 'livre',
        };
        ?>
        <div class="mesa2d <?= $p['forma'] ?> <?= $classeEstado ?> d<?= ($i % 8) + 1 ?>"
             style="left:<?= (float) $p['x'] ?>%;top:<?= (float) $p['y'] ?>%;animation-delay:<?= $i * 0.06 ?>s"
             data-mesa="<?= (int) $m['id'] ?>"
             data-numero="<?= (int) $m['numero'] ?>"
             role="button" tabindex="0"
             aria-label="Mesa <?= (int) $m['numero'] ?> — <?= e($m['estado']) ?>">

            <?php if ($classeEstado !== 'livre'): ?>
                <span class="luz"></span>
            <?php endif; ?>

            <span class="num"><?= (int) $m['numero'] ?></span>
            <span class="lugares"><?= (int) $m['capacidade'] ?> LUG</span>

            <?php if ((int) $m['nr_itens'] > 0): ?>
                <span class="info-mesa"><?= dinheiro((float) $m['total_ativo']) ?></span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- Legenda -->
    <div class="sala-legenda">
        <span><i style="background:var(--sucesso)"></i> Livre</span>
        <span><i style="background:var(--perigo)"></i> Ocupada com pedido ativo</span>
        <span><i style="background:var(--aviso)"></i> Reservada</span>
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
                    <div class="pequeno texto-2">Total da conta</div>
                    <div class="negrito" id="mesaTotal" style="font-size:1.35rem;color:var(--primario)">0,00 €</div>
                </div>
            </div>

            <div>
                <p class="painel-titulo">Itens do pedido</p>
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
/* Interação com a planta 2D (delegado ao main.js via API interna) */
(function () {
    var sala = document.getElementById('sala');
    var modal = document.getElementById('modalMesa');
    var estadoAtual = 0;
    var estados = ['Livre', 'Ocupada', 'Reservada'];

    function urlBase() {
        var d = window.location.pathname.replace(/\\/g, '/').replace(/\/[^/]*$/, '');
        return d === '/' ? '' : d;
    }

    function abrirMesa(idMesa) {
        fetch(urlBase() + '/api/mesa.php?mesa=' + idMesa)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) return;
                estadoAtual = res.mesa.id;
                document.getElementById('mesaTitulo').textContent = 'Mesa ' + res.mesa.numero;
                document.getElementById('mesaEstado').textContent = res.mesa.estado;
                document.getElementById('mesaEstado').className = 'etiqueta ' +
                    (res.mesa.estado === 'Ocupada' ? 'estado-pendente' :
                     res.mesa.estado === 'Reservada' ? 'estado-preparacao' : 'estado-pronto');
                document.getElementById('mesaTotal').textContent = window.saborproMoney(res.pedido ? res.pedido.total : 0);
                document.getElementById('mesaPos').href = 'pedidos.php?mesa=' + res.mesa.numero;

                var ul = document.getElementById('mesaItens');
                ul.innerHTML = '';
                if (!res.itens.length) {
                    var li = document.createElement('li');
                    li.className = 'texto-3';
                    li.textContent = 'Sem itens na conta desta mesa.';
                    ul.appendChild(li);
                } else {
                    res.itens.forEach(function (i) {
                        var li = document.createElement('li');
                        li.innerHTML = '<span>' + i.quantidade + '× ' + i.nome + '</span>'
                                     + '<span class="texto-3">' + window.saborproMoney(i.preco_unitario) + '</span>';
                        ul.appendChild(li);
                    });
                }

                document.getElementById('mesaAdicionar').onclick = function (ev) {
                    var b = ev.target.closest('[data-item]');
                    if (!b) return;
                    adicionarItem(b.getAttribute('data-item'));
                };

                document.getElementById('mesaFechar').onclick = function () {
                    acao({ acao: 'fechar', mesa: estadoAtual }, function () {
                        fechar();
                        window.location.reload();
                    });
                };

                document.getElementById('mesaEstadoBtn').onclick = function () {
                    var proximo = estados[(estados.indexOf(res.mesa.estado) + 1) % estados.length];
                    acao({ acao: 'estado', mesa: estadoAtual, estado: proximo }, function () {
                        fechar();
                        window.location.reload();
                    });
                };

                window.saborproAbrirModal('modalMesa');
            });
    }

    function acao(dados, ok) {
        fetch(urlBase() + '/api/mesa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) { if (res.ok) ok(res); });
    }

    function adicionarItem(itemId) {
        acao({ acao: 'adicionar', mesa: estadoAtual, item: parseInt(itemId, 10), qtd: 1 }, function () {
            abrirMesa(estadoAtual);
        });
    }

    function fechar() {
        window.saborproFecharModal('modalMesa');
    }

    if (sala) {
        sala.addEventListener('click', function (ev) {
            var m = ev.target.closest('.mesa2d');
            if (m) abrirMesa(m.getAttribute('data-mesa'));
        });
        sala.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Enter') return;
            var m = ev.target.closest('.mesa2d');
            if (m) abrirMesa(m.getAttribute('data-mesa'));
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/rodape.php'; ?>
