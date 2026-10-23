<?php
/**
 * SaborPro :: index.php
 * -------------------------------------------------------------
 * SITE PÚBLICO — tema claro de luxo (creme + dourado + vinho)
 * Hero fotográfico, história, ementa com fotos, avaliações dos
 * clientes (com moderação), pedido directo sem login e
 * alternância de idioma PT / EN.
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

if (autenticado()) {
    header('Location: dashboard.php');
    exit;
}

/* -------------------------- AVALIAÇÕES --------------------------- */
$avaliacoes = [];
$r = $bd->query("SELECT id, nome_cliente, classificacao, comentario, criado_em
                   FROM avaliacoes
                  WHERE visivel = 1
                  ORDER BY criado_em DESC
                  LIMIT 6");
while ($a = $r->fetch_assoc()) {
    $avaliacoes[] = $a;
}
$r->free();

$r = $bd->query("SELECT COUNT(*) AS total, COALESCE(AVG(classificacao), 0) AS media
                   FROM avaliacoes WHERE visivel = 1");
$avalResumo = $r->fetch_assoc();
$r->free();

$totalAvaliacoes = (int) ($avalResumo['total'] ?? 0);
$notaMedia       = $totalAvaliacoes > 0 ? round((float) $avalResumo['media'], 1) : 0.0;
$notaTexto       = number_format($notaMedia, 1, ',', '.');

$fotoHero     = 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=1900&q=80&auto=format&fit=crop';
$fotoInterior = 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1000&q=80&auto=format&fit=crop';
$fotoCozinha  = 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?w=1000&q=80&auto=format&fit=crop';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SaborPro · Restaurante — Cozinha de autor no Porto</title>
<meta name="description" content="SaborPro — cozinha de autor, produto local e uma sala acolhedora no centro do Porto. Explore a ementa e peça online, sem registo.">
<link rel="stylesheet" href="css/estilo.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><rect width='24' height='24' rx='5' fill='%23991B1B'/><path d='M4 17h16M6.5 17a5.5 5.5 0 0 1 11 0' stroke='%23F59E0B' stroke-width='1.6' fill='none' stroke-linecap='round'/><circle cx='12' cy='8' r='1.3' fill='%23F59E0B'/></svg>">
</head>
<body class="tema-publico">

<!-- ===================== CABEÇALHO ===================== -->
<header class="nav">
    <div class="container nav-inner">
        <?= logo_saborpro('claro', 'restaurante · porto', 'index.php') ?>

        <nav class="nav-links" id="navLinks">
            <a href="#inicio" class="ativo" data-i18n="nav_home">Início</a>
            <a href="menu.php" data-i18n="nav_menu">Ementa</a>
            <a href="#historia" data-i18n="nav_story">A Nossa História</a>
            <a href="#avaliacoes" data-i18n="nav_reviews">Avaliações</a>
            <a href="gestao.php" data-i18n="nav_admin">Acesso Reservado</a>
        </nav>

        <div class="nav-util">
            <div class="idioma" role="group" aria-label="Idioma / Language">
                <button type="button" data-lang="pt" class="ativo" aria-pressed="true">PT</button>
                <span aria-hidden="true">|</span>
                <button type="button" data-lang="en" aria-pressed="false">EN</button>
            </div>
            <button class="menu-hamburguer" id="btnMenu" aria-label="Abrir menu"><?= icone('menu') ?></button>
        </div>
    </div>
</header>

<main>
    <!-- ===================== HERO ===================== -->
    <section class="hero" id="inicio">
        <div class="hero-foto" style="background-image:url('<?= e($fotoHero) ?>')"></div>
        <div class="hero-overlay"></div>

        <div class="container">
            <div class="hero-conteudo">
                <span class="hero-eyebrow" data-i18n="hero_eyebrow">Porto · desde 1998</span>

                <h1 data-i18n-html="hero_title">Cozinha honesta,<br><em>mesa posta com alma.</em></h1>

                <p class="legenda" data-i18n="hero_text">
                    Produto local, receita de família e o tempo certo de cada prato.
                    Uma sala pequena no coração do Porto, onde a carta muda com o mercado.
                </p>

                <div class="hero-acoes">
                    <a href="menu.php" class="btn btn-primario">
                        <?= icone('carrinho-mais') ?> <span data-i18n="hero_btn_menu">Explorar Ementa</span>
                    </a>
                    <a href="#historia" class="btn btn-fantasma">
                        <?= icone('coracao') ?> <span data-i18n="hero_btn_story">A Nossa História</span>
                    </a>
                </div>

                <div class="hero-rodape">
                    <div>
                        <strong data-i18n="hero_hours_label">Seg–Sáb · 12h00–23h00</strong>
                        <span data-i18n="hero_hours_text">Domingo · brunch até às 15h</span>
                    </div>
                    <div>
                        <strong>Rua das Flores, 24</strong>
                        <span>4050-262 Porto</span>
                    </div>
                    <div>
                        <strong>+351 220 145 900</strong>
                        <span data-i18n="hero_phone_text">Reservas e encomendas</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="hero-selo">
            <div>28 anos<br>à mesa<span data-i18n="hero_seal">cozinha de autor</span></div>
        </div>
    </section>

    <!-- ===================== HISTÓRIA ===================== -->
    <section class="seccao seccao-alt" id="historia">
        <div class="container">
            <div class="historia-grelha">
                <div class="historia-fotos">
                    <figure>
                        <img src="<?= e($fotoInterior) ?>" alt="Sala do restaurante SaborPro" loading="lazy">
                    </figure>
                    <figure>
                        <img src="<?= e($fotoCozinha) ?>" alt="Cozinha do SaborPro em serviço" loading="lazy">
                    </figure>
                    <figure>
                        <img src="https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=800&q=80&auto=format&fit=crop"
                             alt="Prato de peixe fresco" loading="lazy">
                    </figure>
                </div>

                <div class="historia-texto">
                    <div class="seccao-cabecalho" style="margin-bottom:22px">
                        <span class="eyebrow" data-i18n="story_eyebrow">A nossa filosofia</span>
                        <h2 data-i18n-html="story_title">Nasceu de uma banca<br>no mercado do Bolhão.</h2>
                    </div>

                    <p data-i18n-html="story_p1">
                        Em 1998, abrimos com quatro mesas, um fogão e a certeza de que
                        <em>cozinhar é servir quem se gosta</em>. Vinte e oito anos depois,
                        a sala cresceu — a forma de fazer as coisas não.
                    </p>
                    <p data-i18n="story_p2">
                        Trabalhamos com cinco produtores da região. O peixe chega da lota de
                        manhã, o pão é assado todos os dias às sete e as hortícolas vêm do
                        mercado, não de um catálogo. Nada de congelados, nada de atalhos.
                    </p>

                    <div class="historia-assinatura">
                        <span class="iniciais">AR</span>
                        <div>
                            <strong>Ana Ribeiro</strong>
                            <span data-i18n="story_role">Chef e fundadora</span>
                        </div>
                    </div>

                    <div class="valores">
                        <div class="valor-item">
                            <strong>28</strong>
                            <span data-i18n="val_years">anos de cozinha</span>
                        </div>
                        <div class="valor-item">
                            <strong>5</strong>
                            <span data-i18n="val_suppliers">produtores locais</span>
                        </div>
                        <div class="valor-item">
                            <strong><?= $totalAvaliacoes > 0 ? $notaTexto . '/5' : '—' ?></strong>
                            <span data-i18n="val_rating">avaliações de clientes</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== AVALIAÇÕES ===================== -->
    <section class="seccao seccao-avaliacoes" id="avaliacoes">
        <div class="container">
            <div class="avaliacoes-cabecalho">
                <div class="seccao-cabecalho" style="margin-bottom:0">
                    <span class="eyebrow" data-i18n="rev_eyebrow">Avaliações</span>
                    <h2 data-i18n="rev_title">Quem se senta à nossa mesa</h2>
                    <p data-i18n="rev_sub">Opiniões reais de quem já provou a nossa cozinha.</p>
                </div>

                <div class="avaliacoes-resumo"
                     data-media="<?= $notaMedia ?>"
                     data-total="<?= $totalAvaliacoes ?>">
                    <span class="nota-media"><?= $notaTexto ?></span>
                    <span class="nota-max">/5</span>
                    <?= estrelas($notaMedia, 19) ?>
                    <span class="pequeno texto-3"
                          data-i18n="rev_count"
                          data-n="<?= $totalAvaliacoes ?>">Baseado em <?= $totalAvaliacoes ?> avaliações</span>
                </div>
            </div>

            <div class="vazio<?= $avaliacoes ? ' d-none' : '' ?>" id="avaliacoesVazio" data-i18n="rev_empty">
                Seja o primeiro a avaliar a nossa cozinha.
            </div>

            <div class="grelha g-auto avaliacoes-grelha" id="grelhaAvaliacoes">
                <?php foreach ($avaliacoes as $i => $a): ?>
                    <article class="avaliacao-cartao" style="animation-delay:<?= $i * 0.06 ?>s">
                        <div class="avaliacao-topo">
                            <?= estrelas((float) $a['classificacao'], 15) ?>
                            <span class="pequeno texto-3"><?= date('d/m/Y', strtotime((string) $a['criado_em'])) ?></span>
                        </div>
                        <p class="avaliacao-texto">“<?= e($a['comentario']) ?>”</p>
                        <div class="avaliacao-autor">
                            <span class="avaliacao-avatar"><?= e(mb_strtoupper(mb_substr((string) $a['nome_cliente'], 0, 1))) ?></span>
                            <div>
                                <strong><?= e($a['nome_cliente']) ?></strong>
                                <span data-i18n="rev_verified">Cliente verificado</span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="texto-centro mt-3">
                <button type="button" class="btn btn-primario btn-lg" data-modal-abrir="modalAvaliacao">
                    <?= icone('estrela', '', 16) ?> <span data-i18n="rev_cta">Deixar avaliação</span>
                </button>
            </div>
        </div>
    </section>

    <!-- ===================== CONTACTO ===================== -->
    <section class="contacto" id="contacto">
        <div class="container contacto-grelha">
            <div>
                <?= logo_saborpro('escuro', 'restaurante · porto', 'index.php') ?>
                <p style="max-width:300px;font-size:.88rem;margin-top:14px" data-i18n="contact_tagline">
                    Cozinha de autor no centro do Porto. Almoço, jantar e brunch de domingo.
                    Reservas para grupos e eventos privados.
                </p>
                <a href="menu.php" class="btn btn-primario btn-sm mt-1">
                    <?= icone('carrinho-mais', '', 14) ?> <span data-i18n="contact_order">Pedir agora</span>
                </a>
            </div>

            <div>
                <h3 data-i18n="contact_address">Morada</h3>
                <div class="contacto-item">
                    <?= icone('pin') ?>
                    <span><strong>Rua das Flores, 24</strong>4050-262 Porto, Portugal<br>
                        <span data-i18n="contact_address_note">A 5 min de São Bento</span></span>
                </div>
            </div>

            <div>
                <h3 data-i18n="contact_contact">Contacto</h3>
                <div class="contacto-item">
                    <?= icone('telefone') ?>
                    <span><strong>+351 220 145 900</strong><span data-i18n="contact_reservations">Reservas e encomendas</span></span>
                </div>
                <div class="contacto-item">
                    <?= icone('email') ?>
                    <span><strong>geral@saborpro.pt</strong><span data-i18n="contact_email_note">Resposta em 24 h</span></span>
                </div>
            </div>

            <div>
                <h3 data-i18n="contact_hours">Horário</h3>
                <div class="contacto-item">
                    <?= icone('relogio') ?>
                    <span><strong data-i18n="contact_week">Seg–Sáb</strong>12h00–15h00 · 19h00–23h00</span>
                </div>
                <div class="contacto-item">
                    <?= icone('relogio') ?>
                    <span><strong data-i18n="contact_sunday">Domingo</strong>12h00–15h00 (brunch)</span>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- ===================== RODAPÉ ===================== -->
<footer class="rodape">
    <div class="container rodape-grelha">
        <p>© <?= date('Y') ?> SaborPro · <span data-i18n="footer_text">Restaurante &amp; Cafetaria — Sistema de Pedidos e Gestão</span></p>
        <a href="gestao.php"><?= icone('chave', '', 12) ?> <span data-i18n="footer_admin">Acesso Reservado / Gestão</span></a>
    </div>
</footer>

<!-- ===================== MODAL DE AVALIAÇÃO ===================== -->
<div class="modal" id="modalAvaliacao" role="dialog" aria-modal="true" aria-labelledby="tituloAvaliacao">
    <div class="modal-caixa">
        <button class="modal-fechar" data-modal-fechar aria-label="Fechar"><?= icone('x') ?></button>

        <h3 id="tituloAvaliacao" data-i18n="rev_form_title">Deixar avaliação</h3>
        <p class="sub-modal" data-i18n="rev_form_sub">A sua opinião ajuda-nos a melhorar. Obrigado!</p>

        <form id="formAvaliacao" novalidate>
            <div class="campo">
                <span data-i18n="rev_form_rating">Classificação</span>
                <div class="avaliacao-estrelas" id="estrelasEscolha" role="radiogroup" aria-label="Classificação de 1 a 5 estrelas">
                    <?php for ($n = 1; $n <= 5; $n++): ?>
                        <label class="estrela-opcao" title="<?= $n ?> <?= $n === 1 ? 'estrela' : 'estrelas' ?>">
                            <input type="radio" name="classificacao" value="<?= $n ?>" <?= $n === 5 ? 'checked' : '' ?>>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m12 2.6 2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.5l-5.9 3.1 1.2-6.5L2.5 9.5l6.6-.9Z"/>
                            </svg>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>

            <label class="campo">
                <span data-i18n="rev_form_name">O seu nome</span>
                <input type="text" id="avaliacaoNome" maxlength="80" placeholder="Nome" required data-i18n-placeholder="rev_form_name_ph">
            </label>

            <label class="campo">
                <span data-i18n="rev_form_comment">O seu comentário</span>
                <textarea id="avaliacaoComentario" maxlength="500" rows="4" required
                          placeholder="Conte-nos como foi a sua experiência…" data-i18n-placeholder="rev_form_comment_ph"></textarea>
                <small class="texto-3" data-i18n="rev_form_note">A sua avaliação será publicada imediatamente no site.</small>
            </label>

            <!-- Honeypot anti-spam (invisível) -->
            <input type="text" id="avaliacaoWebsite" tabindex="-1" autocomplete="off" class="sr-only" aria-hidden="true">

            <div class="erro-campo" id="avaliacaoErro"></div>

            <button type="submit" class="btn btn-primario btn-bloco" id="btnEnviarAvaliacao">
                <?= icone('estrela', '', 15) ?> <span data-i18n="rev_form_submit">Enviar avaliação</span>
            </button>
        </form>
    </div>
</div>

<!-- ===================== TOAST ===================== -->
<div class="toast" id="toastPedido">
    <span class="icone-toast"><?= icone('ok') ?></span>
    <span>
        <strong data-i18n="rev_toast_title">Avaliação recebida</strong>
        <span id="toastTexto" data-i18n="rev_toast_text">Obrigado pelo seu comentário.</span>
    </span>
</div>

<script src="js/main.js"></script>
</body>
</html>
