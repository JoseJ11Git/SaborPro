<?php
/**
 * SaborPro :: menu.php
 * -------------------------------------------------------------
 * EMENTA PÚBLICA DEDICADA (PT/EN)
 *  · Lista limpa e focada no cliente, com fotografia e preços
 *  · Nomes e descrições traduzidos (colunas nome_en / descricao_en)
 *  · Pedido integrado: quantidades, Na Mesa (com número) ou Takeaway
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

if (autenticado()) {
    header('Location: gestao.php');
    exit;
}

/* ----------------------------- DADOS ----------------------------- */
$categorias = [];
$r = $bd->query('SELECT id, nome FROM categorias ORDER BY id');
while ($c = $r->fetch_assoc()) {
    $categorias[] = $c;
}
$r->free();

$pratos = [];
$r = $bd->query("SELECT e.id, e.nome, e.nome_en, e.descricao, e.descricao_en,
                        e.preco, e.etiquetas, e.destaque, e.imagem_url,
                        e.categoria_id, c.nome AS categoria
                   FROM ementa e
                   JOIN categorias c ON c.id = e.categoria_id
                  WHERE e.disponivel = 1
                  ORDER BY c.id, e.destaque DESC, e.nome");
while ($p = $r->fetch_assoc()) {
    $pratos[] = $p;
}
$r->free();

$mesas = [];
$r = $bd->query('SELECT numero, capacidade FROM mesas WHERE numero > 0 ORDER BY numero');
while ($m = $r->fetch_assoc()) {
    $mesas[] = $m;
}
$r->free();
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ementa · SaborPro</title>
<meta name="description" content="A ementa completa do SaborPro — entradas, pratos, sobremesas e bebidas. Peça online para takeaway ou para a mesa.">
<link rel="stylesheet" href="css/estilo.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><rect width='24' height='24' rx='5' fill='%23991B1B'/><path d='M4 17h16M6.5 17a5.5 5.5 0 0 1 11 0' stroke='%23F59E0B' stroke-width='1.6' fill='none' stroke-linecap='round'/><circle cx='12' cy='8' r='1.3' fill='%23F59E0B'/></svg>">
</head>
<body class="tema-publico pagina-menu">

<!-- ===================== CABEÇALHO ===================== -->
<header class="nav">
    <div class="container nav-inner">
        <?= logo_saborpro('claro', 'restaurante · porto', 'index.php') ?>

        <nav class="nav-links" id="navLinks">
            <a href="index.php" data-i18n="nav_home">Início</a>
            <a href="index.php#historia" data-i18n="nav_story">A Nossa História</a>
            <a href="menu.php" class="ativo" data-i18n="nav_menu">Ementa</a>
            <a href="index.php#avaliacoes" data-i18n="nav_reviews">Avaliações</a>
            <a href="index.php#contacto" data-i18n="nav_contact">Contacto</a>
        </nav>

        <div class="nav-util">
            <div class="idioma" role="group" aria-label="Idioma / Language">
                <button type="button" data-lang="pt" class="ativo" aria-pressed="true">PT</button>
                <span aria-hidden="true">|</span>
                <button type="button" data-lang="en" aria-pressed="false">EN</button>
            </div>
            <a href="gestao.php" class="btn btn-fantasma btn-sm nav-admin" title="Área reservada à equipa">
                <?= icone('chave', '', 14) ?> <span data-i18n="nav_admin">Acesso Reservado</span>
            </a>
            <button class="menu-hamburguer" id="btnMenu" aria-label="Abrir menu"><?= icone('menu') ?></button>
        </div>
    </div>
</header>

<main>
    <!-- ===================== INTRODUÇÃO ===================== -->
    <section class="menu-hero">
        <div class="container">
            <span class="eyebrow" data-i18n="menu_page_eyebrow">Ementa · Menu</span>
            <h1 data-i18n-html="menu_page_title">Escolha à mesa,<br><em>nós levamos.</em></h1>
            <p class="menu-hero-texto" data-i18n="menu_page_sub">
                Produto da estação, receitas de família e preços claros. Escolha os pratos,
                ajuste as quantidades e finalize o pedido em menos de um minuto.
            </p>
            <div class="menu-hero-meta">
                <span><?= icone('relogio', '', 14) ?> <span data-i18n="menu_page_hours">Seg–Sáb · 12h–23h</span></span>
                <span><?= icone('saco', '', 14) ?> <span data-i18n="menu_page_takeaway">Takeaway em ~20 min</span></span>
                <span><?= icone('mesa', '', 14) ?> <span data-i18n="menu_page_dinein">Consumo na sala</span></span>
            </div>
        </div>
    </section>

    <!-- ===================== EMENTA + PEDIDO ===================== -->
    <section class="seccao menu-seccao">
        <div class="container menu-layout">

            <!-- Lista de pratos -->
            <div class="menu-conteudo">
                <div class="menu-barra">
                    <div class="abas-categoria" id="abasCategoria">
                        <button type="button" class="ativo" data-categoria="todos" data-i18n="menu_all">Tudo</button>
                        <?php foreach ($categorias as $c): ?>
                            <button type="button" data-categoria="<?= (int) $c['id'] ?>">
                                <span data-cat-nome="<?= e($c['nome']) ?>"><?= e($c['nome']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="procura-publica">
                        <?= icone('buscar') ?>
                        <label class="sr-only" for="procuraPublica">Procurar prato</label>
                        <input type="search" id="procuraPublica" placeholder="Procurar um prato…" data-i18n-placeholder="menu_search">
                    </div>
                </div>

                <div class="menu-lista" id="grelhaPratos">
                    <?php foreach ($pratos as $p): ?>
                        <?php
                        $nomeEn = trim((string) ($p['nome_en'] ?? '')) !== '' ? $p['nome_en'] : $p['nome'];
                        $descEn = trim((string) ($p['descricao_en'] ?? '')) !== '' ? $p['descricao_en'] : $p['descricao'];
                        ?>
                        <article class="menu-item"
                                 data-categoria="<?= (int) $p['categoria_id'] ?>"
                                 data-pesquisa="<?= e(mb_strtolower($p['nome'] . ' ' . $nomeEn . ' ' . ($p['descricao'] ?? '') . ' ' . $descEn)) ?>">

                            <div class="menu-item-foto">
                                <img src="<?= e(imagem_prato($p['imagem_url'])) ?>"
                                     alt="<?= e($p['nome']) ?>" loading="lazy">
                                <?php if ((int) $p['destaque'] === 1): ?>
                                    <span class="menu-item-chef" title="Recomendação do Chef"><?= icone('estrela', '', 11) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="menu-item-info">
                                <div class="menu-item-topo">
                                    <h3 data-pt="<?= e($p['nome']) ?>" data-en="<?= e($nomeEn) ?>"><?= e($p['nome']) ?></h3>
                                    <span class="menu-item-preco"><?= dinheiro((float) $p['preco']) ?></span>
                                </div>

                                <p class="menu-item-desc"
                                   data-pt="<?= e($p['descricao']) ?>"
                                   data-en="<?= e($descEn) ?>"><?= e($p['descricao']) ?></p>

                                <div class="menu-item-rodape">
                                    <div class="menu-item-tags">
                                        <?php
                                        $rotulosEn = [
                                            'vegetariano' => 'Vegetarian',
                                            'novidade'    => 'New',
                                            'sem-gluten'  => 'Gluten-free',
                                            'picante'     => 'Spicy',
                                        ];
                                        foreach (etiquetas_ementa($p['etiquetas']) as $et):
                                            if ($et === 'chef') { continue; }
                                            $rotuloPt = rotulo_etiqueta($et);
                                            $rotuloEn = $rotulosEn[$et] ?? $rotuloPt;
                                            ?>
                                            <span class="tag <?= classe_etiqueta($et) ?>">
                                                <span data-pt="<?= e($rotuloPt) ?>" data-en="<?= e($rotuloEn) ?>"><?= e($rotuloPt) ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="menu-qtd"
                                         data-id="<?= (int) $p['id'] ?>"
                                         data-nome="<?= e($p['nome']) ?>"
                                         data-nome-en="<?= e($nomeEn) ?>"
                                         data-preco="<?= e((string) $p['preco']) ?>">
                                        <button type="button" data-qtd="-1" aria-label="Diminuir quantidade">−</button>
                                        <span class="menu-qtd-valor" aria-live="polite">0</span>
                                        <button type="button" data-qtd="1" aria-label="Aumentar quantidade">+</button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Resumo / pedido -->
            <aside class="menu-resumo" id="menuResumo" aria-label="Resumo do pedido">
                <div class="menu-resumo-cab">
                    <div>
                        <span class="eyebrow" data-i18n="menu_order_eyebrow">O seu pedido</span>
                        <h2 data-i18n="menu_order_title">Resumo</h2>
                    </div>
                    <span class="menu-contador" id="menuContador">0</span>
                </div>

                <ul class="menu-resumo-lista" id="menuItens">
                    <li class="vazio" data-i18n="menu_order_empty">Ainda não escolheu nenhum prato.</li>
                </ul>

                <div class="menu-tipo">
                    <label class="opcao-pedido selecionada" data-opcao="takeaway">
                        <input type="radio" name="menu_tipo" value="Takeaway" checked>
                        <?= icone('saco') ?>
                        <span>
                            <span class="tit">Takeaway</span>
                            <span class="desc" data-i18n="cart_takeaway_desc">Pronto em ~20 min</span>
                        </span>
                    </label>
                    <label class="opcao-pedido" data-opcao="mesa">
                        <input type="radio" name="menu_tipo" value="Na Mesa">
                        <?= icone('mesa') ?>
                        <span>
                            <span class="tit" data-i18n="cart_table">Na Mesa</span>
                            <span class="desc" data-i18n="cart_table_desc">Consumir no restaurante</span>
                        </span>
                    </label>
                </div>

                <label class="campo d-none" id="menuBlocoMesa">
                    <span data-i18n="cart_select_table">Escolha a mesa</span>
                    <select id="menuMesa">
                        <?php foreach ($mesas as $m): ?>
                            <option value="<?= (int) $m['numero'] ?>"
                                    data-mesa="<?= (int) $m['numero'] ?>"
                                    data-lugares="<?= (int) $m['capacidade'] ?>">
                                Mesa <?= (int) $m['numero'] ?> · <?= (int) $m['capacidade'] ?> lugares
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="linha-campos">
                    <label class="campo">
                        <span data-i18n="cart_name">O seu nome</span>
                        <input type="text" id="menuNome" maxlength="80" placeholder="Nome" data-i18n-placeholder="cart_name_ph">
                    </label>
                    <label class="campo">
                        <span data-i18n="cart_phone">Telefone</span>
                        <input type="tel" id="menuTelefone" maxlength="30" placeholder="912 345 678">
                    </label>
                </div>

                <label class="campo">
                    <span data-i18n="cart_obs">Observações (opcional)</span>
                    <textarea id="menuObs" maxlength="255" rows="2" placeholder="Alergias, pedido especial…" data-i18n-placeholder="cart_obs_ph"></textarea>
                </label>

                <div class="totais">
                    <div class="linha"><span data-i18n="cart_subtotal">Subtotal</span><span id="menuSubtotal">0,00 €</span></div>
                    <div class="linha"><span data-i18n="cart_vat">IVA (13%)</span><span data-i18n="cart_vat_included">incluído</span></div>
                    <div class="linha grande"><span data-i18n="cart_total">Total</span><span id="menuTotal">0,00 €</span></div>
                </div>

                <div class="erro-campo" id="menuErro"></div>

                <button type="button" class="btn btn-primario btn-bloco btn-lg" id="menuEnviar">
                    <?= icone('ok') ?> <span data-i18n="cart_finish">Finalizar pedido</span>
                </button>
                <p class="menu-nota" data-i18n="menu_order_note">Sem registo. O pedido entra directamente na cozinha.</p>
            </aside>
        </div>
    </section>
</main>

<footer class="rodape">
    <div class="container rodape-grelha">
        <p>© <?= date('Y') ?> SaborPro · <span data-i18n="footer_text">Restaurante &amp; Cafetaria — Sistema de Pedidos e Gestão</span></p>
        <a href="gestao.php"><?= icone('chave', '', 12) ?> <span data-i18n="footer_admin">Acesso Reservado / Gestão</span></a>
    </div>
</footer>

<!-- ===================== TOAST ===================== -->
<div class="toast" id="toastPedido">
    <span class="icone-toast"><?= icone('ok') ?></span>
    <span>
        <strong data-i18n="toast_title">Pedido confirmado</strong>
        <span id="toastTexto" data-i18n="toast_text">A cozinha já recebeu o seu pedido.</span>
    </span>
</div>

<script src="js/main.js"></script>
</body>
</html>
