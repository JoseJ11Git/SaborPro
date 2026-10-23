<?php
/**
 * SaborPro :: index.php
 * -------------------------------------------------------------
 * SITE PÚBLICO — tema claro de luxo (creme + dourado + vinho)
 * Hero fotográfico, história com fotos reais, ementa com imagens,
 * pedido directo sem login e acesso reservado à gestão.
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
$r = $bd->query('SELECT id, nome, icone_svg FROM categorias ORDER BY id');
while ($c = $r->fetch_assoc()) {
    $c['cor'] = cor_categoria((string) $c['nome']);
    $categorias[] = $c;
}
$r->free();

$pratos = [];
$r = $bd->query("SELECT e.id, e.nome, e.descricao, e.preco, e.etiquetas, e.destaque, e.imagem_url,
                        e.categoria_id, c.nome AS categoria, c.icone_svg
                   FROM ementa e JOIN categorias c ON c.id = e.categoria_id
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
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><rect width='24' height='24' rx='4' fill='%23991B1B'/><text x='12' y='17' font-size='13' text-anchor='middle' fill='%23FAFAF9' font-family='Georgia'>S</text></svg>">
</head>
<body class="tema-publico">

<!-- ===================== CABEÇALHO ===================== -->
<header class="nav">
    <div class="container nav-inner">
        <a href="index.php" class="marca">
            <span class="logo"><?= icone('talheres') ?></span>
            <span>SaborPro<small>restaurante · porto</small></span>
        </a>

        <nav class="nav-links" id="navLinks">
            <a href="#inicio" class="ativo">Início</a>
            <a href="#historia">A Nossa História</a>
            <a href="#ementa">Ementa</a>
            <a href="#contacto">Contacto</a>
        </nav>

        <div class="nav-util">
            <a href="gestao.php" class="btn btn-fantasma btn-sm" title="Área reservada à equipa">
                <?= icone('chave', '', 14) ?> Acesso Reservado
            </a>
            <a href="#ementa" class="btn btn-primario btn-sm">Explorar Ementa</a>
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
                <span class="hero-eyebrow">Porto · desde 1998</span>

                <h1>Cozinha honesta,<br><em>mesa posta com alma.</em></h1>

                <p class="legenda">
                    Produto local, receita de família e o tempo certo de cada prato.
                    Uma sala pequena no coração do Porto, onde a carta muda com o mercado.
                </p>

                <div class="hero-acoes">
                    <a href="#ementa" class="btn btn-primario"><?= icone('carrinho-mais') ?> Explorar Ementa</a>
                    <a href="#historia" class="btn btn-fantasma"><?= icone('coracao') ?> A Nossa História</a>
                </div>

                <div class="hero-rodape">
                    <div><strong>Seg–Sáb · 12h00–23h00</strong>Domingo · brunch até às 15h</div>
                    <div><strong>Rua das Flores, 24</strong>4050-262 Porto</div>
                    <div><strong>+351 220 145 900</strong>Reservas e encomendas</div>
                </div>
            </div>
        </div>

        <div class="hero-selo">
            <div>28 anos<br>à mesa<span>cozinha de autor</span></div>
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
                        <span class="eyebrow">A nossa filosofia</span>
                        <h2>Nasceu de uma banca<br>no mercado do Bolhão.</h2>
                    </div>

                    <p>
                        Em 1998, abrimos com quatro mesas, um fogão e a certeza de que
                        <em>cozinhar é servir quem se gosta</em>. Vinte e oito anos depois,
                        a sala cresceu — a forma de fazer as coisas não.
                    </p>
                    <p>
                        Trabalhamos com cinco produtores da região. O peixe chega da lota de
                        manhã, o pão é assado todos os dias às sete e as hortícolas vêm do
                        mercado, não de um catálogo. Nada de congelados, nada de atalhos.
                    </p>

                    <div class="historia-assinatura">
                        <span class="iniciais">AR</span>
                        <div>
                            <strong>Ana Ribeiro</strong>
                            <span>Chef e fundadora</span>
                        </div>
                    </div>

                    <div class="valores">
                        <div class="valor-item">
                            <strong>28</strong>
                            <span>anos de cozinha</span>
                        </div>
                        <div class="valor-item">
                            <strong>5</strong>
                            <span>produtores locais</span>
                        </div>
                        <div class="valor-item">
                            <strong>4,8/5</strong>
                            <span>+1.200 avaliações</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== EMENTA ===================== -->
    <section class="seccao" id="ementa">
        <div class="container">
            <div class="seccao-cabecalho centro">
                <span class="eyebrow">Ementa da estação</span>
                <h2>Escolha, adicione e receba.</h2>
                <p>Pedido directo, sem criação de conta. Takeaway ou consumo na mesa.</p>
            </div>

            <div class="ementa-barra">
                <div class="abas-categoria" id="abasCategoria">
                    <button type="button" class="ativo" data-categoria="todos">Tudo</button>
                    <?php foreach ($categorias as $c): ?>
                        <button type="button" data-categoria="<?= (int) $c['id'] ?>">
                            <?= icone_categoria((string) $c['icone_svg']) ?> <?= e($c['nome']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="procura-publica">
                    <?= icone('buscar') ?>
                    <label class="sr-only" for="procuraPublica">Procurar prato</label>
                    <input type="search" id="procuraPublica" placeholder="Procurar um prato…">
                </div>
            </div>

            <div class="grelha g-auto" id="grelhaPratos">
                <?php foreach ($pratos as $i => $p): ?>
                    <article class="prato-cartao"
                             data-categoria="<?= (int) $p['categoria_id'] ?>"
                             data-pesquisa="<?= e(mb_strtolower($p['nome'] . ' ' . ($p['descricao'] ?? ''))) ?>"
                             style="animation-delay:<?= $i * 0.045 ?>s">

                        <div class="prato-foto">
                            <img src="<?= e(imagem_prato($p['imagem_url'])) ?>"
                                 alt="<?= e($p['nome']) ?>" loading="lazy">
                            <?php if ((int) $p['destaque'] === 1): ?>
                                <span class="tag-foto chef"><?= icone('estrela', '', 10) ?> Recomendação do Chef</span>
                            <?php elseif (in_array('novidade', etiquetas_ementa($p['etiquetas']), true)): ?>
                                <span class="tag-foto">Novidade</span>
                            <?php endif; ?>
                            <span class="preco-foto"><?= dinheiro((float) $p['preco']) ?></span>
                        </div>

                        <div class="prato-corpo">
                            <h3><?= e($p['nome']) ?></h3>
                            <p class="prato-desc"><?= e($p['descricao']) ?></p>

                            <div class="prato-tags">
                                <?php foreach (etiquetas_ementa($p['etiquetas']) as $et): ?>
                                    <?php if ($et === 'chef') { continue; } ?>
                                    <span class="tag <?= classe_etiqueta($et) ?>">
                                        <?= $et === 'vegetariano' ? icone('folha', '', 10) : '' ?>
                                        <?= e(rotulo_etiqueta($et)) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <div class="prato-rodape">
                                <span class="pequeno texto-3"><?= e($p['categoria']) ?></span>
                                <button type="button" class="btn btn-fantasma btn-sm"
                                        data-add="<?= (int) $p['id'] ?>"
                                        data-nome="<?= e($p['nome']) ?>"
                                        data-preco="<?= e((string) $p['preco']) ?>">
                                    <?= icone('mais', '', 14) ?> Adicionar
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===================== CONTACTO ===================== -->
    <section class="contacto" id="contacto">
        <div class="container contacto-grelha">
            <div>
                <div class="marca" style="color:#FAFAF9;margin-bottom:18px">
                    <span class="logo"><?= icone('talheres') ?></span>
                    <span style="color:#FAFAF9">SaborPro<small style="color:rgba(250,250,249,.45)">restaurante · porto</small></span>
                </div>
                <p style="max-width:300px;font-size:.88rem">
                    Cozinha de autor no centro do Porto. Almoço, jantar e brunch de domingo.
                    Reservas para grupos e eventos privados.
                </p>
                <a href="#ementa" class="btn btn-primario btn-sm mt-1"><?= icone('carrinho-mais', '', 14) ?> Pedir agora</a>
            </div>

            <div>
                <h3>Morada</h3>
                <div class="contacto-item">
                    <?= icone('pin') ?>
                    <span><strong>Rua das Flores, 24</strong>4050-262 Porto, Portugal<br>A 5 min de São Bento</span>
                </div>
            </div>

            <div>
                <h3>Contacto</h3>
                <div class="contacto-item">
                    <?= icone('telefone') ?>
                    <span><strong>+351 220 145 900</strong>Reservas e encomendas</span>
                </div>
                <div class="contacto-item">
                    <?= icone('email') ?>
                    <span><strong>geral@saborpro.pt</strong>Resposta em 24 h</span>
                </div>
            </div>

            <div>
                <h3>Horário</h3>
                <div class="contacto-item">
                    <?= icone('relogio') ?>
                    <span><strong>Seg–Sáb</strong>12h00–15h00 · 19h00–23h00</span>
                </div>
                <div class="contacto-item">
                    <?= icone('relogio') ?>
                    <span><strong>Domingo</strong>12h00–15h00 (brunch)</span>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- ===================== RODAPÉ ===================== -->
<footer class="rodape">
    <div class="container rodape-grelha">
        <p>© <?= date('Y') ?> SaborPro · Restaurante &amp; Cafetaria — Sistema de Pedidos e Gestão</p>
        <a href="gestao.php"><?= icone('chave', '', 12) ?> Acesso Reservado / Gestão</a>
    </div>
</footer>

<!-- ===================== CARRINHO FLUTUANTE ===================== -->
<button class="botao-carrinho-flutuante vazio" id="btnCarrinho" aria-label="Abrir carrinho">
    <?= icone('carrinho') ?>
    <span>O meu pedido</span>
    <span class="contador" id="contadorCarrinho">0</span>
</button>

<div class="gaveta-fundo" id="fundoCarrinho"></div>
<aside class="gaveta-carrinho" id="gavetaCarrinho" aria-label="Carrinho de pedidos">
    <div class="gaveta-cabecalho">
        <h3>O seu pedido</h3>
        <button class="modal-fechar" id="fecharCarrinho" aria-label="Fechar"><?= icone('x') ?></button>
    </div>

    <div class="gaveta-corpo">
        <ul class="carrinho-lista" id="listaPublica"></ul>

        <div class="mt-3">
            <p class="painel-titulo">Como prefere receber?</p>
            <div class="opcoes-pedido">
                <label class="opcao-pedido selecionada" data-opcao="takeaway">
                    <input type="radio" name="tipo_pedido" value="Takeaway" checked>
                    <?= icone('saco') ?>
                    <span><span class="tit">Takeaway</span><span class="desc">Pronto em ~20 min</span></span>
                </label>
                <label class="opcao-pedido" data-opcao="mesa">
                    <input type="radio" name="tipo_pedido" value="Na Mesa">
                    <?= icone('mesa') ?>
                    <span><span class="tit">Na Mesa</span><span class="desc">Consumir no restaurante</span></span>
                </label>
            </div>

            <label class="campo d-none" id="blocoMesa">
                <span>Escolha a mesa</span>
                <select name="mesa_numero" id="mesaPublica">
                    <?php foreach ($mesas as $m): ?>
                        <option value="<?= (int) $m['numero'] ?>">
                            Mesa <?= (int) $m['numero'] ?> · <?= (int) $m['capacidade'] ?> lugares
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="linha-campos">
                <label class="campo">
                    <span>O seu nome</span>
                    <input type="text" id="clienteNome" placeholder="Nome" maxlength="80">
                </label>
                <label class="campo">
                    <span>Telefone</span>
                    <input type="tel" id="clienteTel" placeholder="912 345 678" maxlength="30">
                </label>
            </div>

            <label class="campo">
                <span>Observações (opcional)</span>
                <textarea id="clienteObs" placeholder="Alergias, pedido especial…" maxlength="255"></textarea>
            </label>
        </div>
    </div>

    <div class="gaveta-rodape">
        <div class="totais">
            <div class="linha"><span>Subtotal</span><span id="publicoSubtotal">0,00 €</span></div>
            <div class="linha"><span>IVA (13%)</span><span>incluído</span></div>
            <div class="linha grande"><span>Total</span><span id="publicoTotal">0,00 €</span></div>
        </div>
        <div class="erro-campo" id="publicoErro"></div>
        <button class="btn btn-primario btn-bloco btn-lg mt-1" id="btnEnviarPublico">
            <?= icone('ok') ?> Finalizar pedido
        </button>
    </div>
</aside>

<!-- ===================== TOAST ===================== -->
<div class="toast" id="toastPedido">
    <span class="icone-toast"><?= icone('ok') ?></span>
    <span>
        <strong>Pedido confirmado</strong>
        <span id="toastTexto">A cozinha já recebeu o seu pedido.</span>
    </span>
</div>

<script src="js/main.js"></script>
</body>
</html>
