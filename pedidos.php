<?php
/**
 * SaborPro :: pedidos.php
 * Ecrã POS — tomada de pedidos com filtro de categorias e carrinho.
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

exigir_login(['admin', 'empregado']);

/* ----------------------------- DADOS -------------------------------- */
$categorias = [];
$r = $bd->query('SELECT id, nome, icone_svg FROM categorias ORDER BY id');
while ($c = $r->fetch_assoc()) {
    $categorias[] = $c;
}
$r->free();

$artigos = [];
$r = $bd->query("SELECT e.id, e.nome, e.descricao, e.preco, e.disponivel, e.categoria_id, c.nome AS categoria
                   FROM ementa e JOIN categorias c ON c.id = e.categoria_id
                  ORDER BY c.id, e.nome");
while ($a = $r->fetch_assoc()) {
    $artigos[] = $a;
}
$r->free();

// Contagem de itens por categoria
$contagem = [];
foreach ($artigos as $a) {
    $contagem[$a['categoria_id']] = ($contagem[$a['categoria_id']] ?? 0) + 1;
}

// Mesas disponíveis
$mesas = [];
$r = $bd->query('SELECT id, numero, capacidade, estado FROM mesas ORDER BY numero');
while ($m = $r->fetch_assoc()) {
    $mesas[] = $m;
}
$r->free();

// Mesa pré-selecionada (vinda do mapa de mesas)
$mesaPre = (int) ($_GET['mesa'] ?? 0);

$paginaTitulo = 'Novo Pedido';
$paginaAtiva  = 'pedidos';

require_once __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina entre-topo">
    <div>
        <h1 class="mb-0">Novo pedido</h1>
        <p class="subtitulo">Toque nos artigos para montar a conta. O pedido é enviado para a cozinha.</p>
    </div>
    <div class="d-flex-wrap">
        <a href="mesas.php" class="btn btn-fantasma"><?= icone('mesa') ?> Mapa de mesas</a>
        <a href="cozinha.php" class="btn btn-fantasma"><?= icone('cozinha') ?> Ecrã de cozinha</a>
    </div>
</div>

<div class="pos">

    <!-- ================== COLUNA 1 — CATEGORIAS ================== -->
    <aside class="painel">
        <p class="painel-titulo">Categorias</p>
        <div class="categoria-lista">
            <button type="button" class="ativo" data-filtro-categoria="todos">
                <?= icone('grelha') ?> Todos os artigos
                <span class="conta"><?= count($artigos) ?></span>
            </button>
            <?php foreach ($categorias as $c): ?>
                <button type="button" data-filtro-categoria="<?= (int) $c['id'] ?>">
                    <?= icone_categoria((string) $c['icone_svg']) ?> <?= e($c['nome']) ?>
                    <span class="conta"><?= (int) ($contagem[$c['id']] ?? 0) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="cartao mt-3" style="padding:14px">
            <p class="painel-titulo">Mesa</p>
            <label class="campo mb-0">
                <span class="sr-only">Selecionar mesa</span>
                <select id="selectMesa">
                    <option value="">— Escolher mesa —</option>
                    <?php foreach ($mesas as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= $mesaPre === (int) $m['numero'] ? 'selected' : '' ?>>
                            Mesa <?= (int) $m['numero'] ?> (<?= (int) $m['capacidade'] ?> lug.) — <?= e($m['estado']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </aside>

    <!-- ================== COLUNA 2 — ARTIGOS ================== -->
    <section class="painel">
        <div class="entre mb-2">
            <p class="painel-titulo mb-0">Artigos</p>
            <input type="search" id="procuraArtigo" placeholder="Procurar artigo..."
                   style="max-width:220px" aria-label="Procurar artigo">
        </div>

        <div class="grade-artigos" id="gradeArtigos">
            <?php foreach ($artigos as $i => $a): ?>
                <button type="button"
                        class="artigo <?= (int) $a['disponivel'] === 1 ? '' : 'indisponivel' ?>"
                        data-artigo="<?= (int) $a['id'] ?>"
                        data-artigo-categoria="<?= (int) $a['categoria_id'] ?>"
                        data-nome="<?= e($a['nome']) ?>"
                        data-preco="<?= e((string) $a['preco']) ?>"
                        data-pesquisa="<?= e(mb_strtolower($a['nome'] . ' ' . ($a['descricao'] ?? ''))) ?>"
                        style="animation-delay: <?= $i * 0.03 ?>s">
                    <div class="cat"><?= e($a['categoria']) ?></div>
                    <h4><?= e($a['nome']) ?></h4>
                    <div class="preco"><?= dinheiro((float) $a['preco']) ?></div>
                    <?php if ((int) $a['disponivel'] === 0): ?>
                        <div class="pequeno perigo-txt mt-1">Indisponível</div>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php if (!$artigos): ?>
            <div class="vazio"><?= icone('receita', '', 34) ?>A ementa ainda não tem artigos.</div>
        <?php endif; ?>
    </section>

    <!-- ================== COLUNA 3 — CARRINHO ================== -->
    <aside class="painel coluna-carrinho">
        <div class="entre mb-2">
            <p class="painel-titulo mb-0">Conta corrente</p>
            <span class="etiqueta badge-neutro" id="carrinhoQtd">0 artigos</span>
        </div>

        <ul class="carrinho-lista" id="carrinhoLista"></ul>

        <div class="totais">
            <div class="linha"><span>Subtotal</span><span id="carrinhoSubtotal">0,00 €</span></div>
            <div class="linha"><span>IVA (13%)</span><span>incluído</span></div>
            <div class="linha grande"><span>Total</span><span id="carrinhoTotal">0,00 €</span></div>
        </div>

        <div class="erro-campo" id="carrinhoAviso"></div>

        <div class="d-flex-wrap mt-2">
            <button class="btn btn-primario btn-bloco" id="btnFinalizar">
                <?= icone('ok') ?> Finalizar pedido
            </button>
            <button class="btn btn-fantasma btn-bloco" id="btnLimpar">
                <?= icone('lixo') ?> Limpar conta
            </button>
        </div>
    </aside>
</div>

<script>
/* Procura de artigos (filtro adicional ao das categorias) */
(function () {
    var campo = document.getElementById('procuraArtigo');
    if (!campo) return;
    campo.addEventListener('input', function () {
        var termo = campo.value.trim().toLowerCase();
        document.querySelectorAll('[data-artigo-categoria]').forEach(function (a) {
            var visivel = termo === '' || a.getAttribute('data-pesquisa').indexOf(termo) !== -1;
            if (visivel) { a.style.display = ''; }
            else { a.style.display = 'none'; }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/rodape.php'; ?>
