<?php
/**
 * SaborPro :: index.php
 * -------------------------------------------------------------
 * SITE PÚBLICO DO RESTAURANTE (tema claro e acolhedor)
 *  - Hero, A Nossa História, Ementa digital, Carrinho de pedidos
 *    (sem login) e acesso discreto à área de gestão.
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

/* Se o utilizador já tem sessão ativa, segue para o portal de gestão. */
if (autenticado()) {
    header('Location: gestao.php');
    exit;
}

$erroLogin = '';
$loginOk   = false;

/* ------------------ LOGIN DA ÁREA RESERVADA (POST) ------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'login') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['palavra_passe'] ?? '';

    if ($email === '' || $pass === '') {
        $erroLogin = 'Indique o e-mail e a palavra-passe.';
    } else {
        $stmt = $bd->prepare('SELECT id, nome, email, palavra_passe, tipo FROM utilizadores WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$u || !password_verify($pass, $u['palavra_passe'])) {
            $erroLogin = 'Credenciais inválidas.';
        } else {
            session_regenerate_id(true);
            $_SESSION['utilizador'] = [
                'id'    => (int) $u['id'],
                'nome'  => $u['nome'],
                'email' => $u['email'],
                'tipo'  => $u['tipo'],
            ];
            header('Location: gestao.php');
            exit;
        }
    }
}

/* ----------------------------- DADOS DA EMENTA ------------------------ */
$categorias = [];
$r = $bd->query('SELECT id, nome, icone_svg FROM categorias ORDER BY id');
while ($c = $r->fetch_assoc()) {
    $c['cor'] = cor_categoria((string) $c['nome']);
    $categorias[] = $c;
}
$r->free();

$pratos = [];
$r = $bd->query("SELECT e.id, e.nome, e.descricao, e.preco, e.etiquetas, e.destaque, e.categoria_id,
                         c.nome AS categoria, c.icone_svg
                   FROM ementa e JOIN categorias c ON c.id = e.categoria_id
                  WHERE e.disponivel = 1
                  ORDER BY c.id, e.destaque DESC, e.nome");
while ($p = $r->fetch_assoc()) {
    $p['cor'] = cor_categoria((string) $p['categoria']);
    $pratos[] = $p;
}
$r->free();

/* Mesas disponíveis para pedido "Na Mesa" */
$mesas = [];
$r = $bd->query('SELECT id, numero, capacidade, estado FROM mesas WHERE numero > 0 ORDER BY numero');
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
<title>SaborPro · Tábua &amp; Ordem — Restaurante e Cafetaria</title>
<meta name="description" content="Tábua &amp; Ordem — cozinha de autor, produtos do campo e um lugar aconchegante no centro. Peça online sem registo.">
<link rel="stylesheet" href="css/estilo.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><text y='19' font-size='20'>&#127860;</text></svg>">
</head>
<body class="tema-publico">

<!-- ===================== CABEÇALHO PÚBLICO ===================== -->
<header class="nav">
    <div class="container nav-inner">
        <a href="index.php" class="marca">
            <span class="logo"><?= icone('talheres') ?></span>
            <span>Tábua &amp; Ordem<small>restaurante &amp; cafetaria</small></span>
        </a>

        <nav class="nav-links" id="navLinks">
            <a href="#inicio" class="ativo">Início</a>
            <a href="#ementa">Ementa</a>
            <a href="#historia">A Nossa História</a>
            <a href="#contacto">Contacto</a>
        </nav>

        <div class="nav-util">
            <a href="#ementa" class="btn btn-primario btn-sm">Pedir agora</a>
            <a href="gestao.php" class="btn btn-fantasma btn-sm" title="Acesso exclusivo à área de gestão">
                <?= icone('chave', '', 15) ?> Acesso Reservado
            </a>
            <button class="menu-hamburguer" id="btnMenu" aria-label="Menu"><?= icone('menu') ?></button>
        </div>
    </div>
</header>

<main>
    <!-- ===================== HERO ===================== -->
    <section class="hero-publico" id="inicio">
        <div class="container hero-grelha">
            <div>
                <span class="selo"><span class="ponto"></span> Aberto hoje até às 23h00</span>

                <h1 class="titulo">Cozinha honesta,<br><em>mesa posta com carinho.</em></h1>

                <p class="legenda">
                    Desde 1998 servimos comida feita à mão, com produto local e receita de família.
                    Uma sala pequena, uma carta que muda com as estações e o tempo que se quer perder.
                </p>

                <div class="hero-acoes d-flex-wrap mt-3">
                    <a href="#ementa" class="btn btn-primario btn-lg">
                        <?= icone('carrinho-mais') ?> Ver Ementa &amp; Pedir
                    </a>
                    <a href="#historia" class="btn btn-fantasma btn-lg">
                        <?= icone('coracao') ?> A Nossa História
                    </a>
                </div>

                <div class="info-rapida">
                    <div class="bloco">
                        <?= icone('relogio') ?>
                        <span><strong>Seg a Sáb · 12h00–23h00</strong>Domingo · 12h00–15h00</span>
                    </div>
                    <div class="bloco">
                        <?= icone('pin') ?>
                        <span><strong>Rua das Flores, 24 — Porto</strong>A 5 min da Estação de São Bento</span>
                    </div>
                    <div class="bloco">
                        <?= icone('telefone') ?>
                        <span><strong>+351 220 145 900</strong>Reservas e eventos</span>
                    </div>
                </div>
            </div>

            <div class="montra">
                <div class="montra-capa"><?= icone('talheres', '', 54) ?></div>
                <div class="entre mb-2">
                    <h3 class="mb-0">Destaques do chef</h3>
                    <span class="tag tag-chef"><?= icone('estrela', '', 12) ?> Recomendação</span>
                </div>
                <?php foreach (array_slice($pratos, 0, 3) as $d): ?>
                    <div class="linha">
                        <span class="nome"><?= e($d['nome']) ?></span>
                        <span class="preco"><?= dinheiro((float) $d['preco']) ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="linha">
                    <span class="nome negrito">Bebida da casa</span>
                    <span class="preco">4,20 €</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== A NOSSA HISTÓRIA ===================== -->
    <section class="historia" id="historia">
        <div class="container seccao">
            <div class="historia-grelha">
                <div class="historia-texto">
                    <span class="selo"><span class="ponto"></span> Desde 1998</span>
                    <h2>A nossa história</h2>
                    <p>
                        A <strong>Tábua &amp; Ordem</strong> nasceu numa cozinha pequena, atrás de uma
                        barraca de madeira no mercado do Bolhão. Hoje são vinte e oito anos, duas salas
                        e a mesma convicção: <em>cozinhar o que se comeria em casa, com o produto de quem conhecemos.</em>
                    </p>
                    <p>
                        Trabalhamos com cinco produtores da região, o peixe chega da lota da Foz de
                        Douro de manhã e o pão é da Panificação da Rua de Cedofeita, assado todas
                        as manhãs às 7h. Nada de congelados, nada de atalhos — só tempo e técnica.
                    </p>

                    <ul class="destaques">
                        <li>
                            <?= icone('ok') ?>
                            <span><strong>Produto local e sazonal</strong>Recebemos 3 vezes por semana de pequenos produtores da região.</span>
                        </li>
                        <li>
                            <?= icone('ok') ?>
                            <span><strong>Receita de casa, técnica de chef</strong>Cada prato é pensado para ser partilhado à mesa.</span>
                        </li>
                        <li>
                            <?= icone('ok') ?>
                            <span><strong>Carta curta e mutante</strong>Muda com o mercado, nunca com a moda.</span>
                        </li>
                    </ul>
                </div>

                <div class="valores">
                    <div class="valor-cartao">
                        <?= icone('fogo') ?>
                        <strong>28 anos</strong>
                        <span>de cozinha sem pausa</span>
                    </div>
                    <div class="valor-cartao">
                        <?= icone('terra') ?>
                        <strong>5 produtores</strong>
                        <span>do mercado do dia</span>
                    </div>
                    <div class="valor-cartao">
                        <?= icone('estrela') ?>
                        <strong>4,8 / 5</strong>
                        <span>+1.200 avaliações</span>
                    </div>
                    <div class="valor-cartao">
                        <?= icone('talheres') ?>
                        <strong>2 salas</strong>
                        <span>uma delas com varanda</span>
                    </div>
                    <div class="valor-cartao">
                        <?= icone('relogio') ?>
                        <strong>23h00</strong>
                        <span>última entrada</span>
                    </div>
                    <div class="valor-cartao">
                        <?= icone('coracao') ?>
                        <strong>+20 anos</strong>
                        <span>a mesma família à mesa</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== EMENTA DIGITAL ===================== -->
    <section class="ementa-publica" id="ementa">
        <div class="container">
            <div class="seccao-titulo">
                <h2>A nossa ementa</h2>
                <p>Escolha, adicione ao carrinho e receba em casa ou consuma na mesa — sem necessidade de conta.</p>
            </div>

            <!-- Filtro por categoria -->
            <div class="entre-topo mb-2" style="align-items:center">
                <div class="abas-categoria" id="abasCategoria" style="margin-bottom:0;justify-content:flex-start">
                    <button type="button" class="ativo" data-categoria="todos" style="--cat: var(--primario)">
                        <?= icone('grelha') ?> Tudo
                    </button>
                    <?php foreach ($categorias as $c): ?>
                        <button type="button" data-categoria="<?= (int) $c['id'] ?>" style="--cat: <?= e($c['cor']) ?>">
                            <?= icone_categoria((string) $c['icone_svg']) ?> <?= e($c['nome']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <label style="max-width:260px;width:100%">
                    <span class="sr-only">Procurar prato</span>
                    <input type="search" id="procuraPublica" placeholder="Procurar um prato…">
                </label>
            </div>

            <!-- Grelha de pratos -->
            <div class="grelha g-auto" id="grelhaPratos">
                <?php foreach ($pratos as $i => $p): ?>
                    <article class="prato-cartao d<?= ($i % 8) + 1 ?>"
                             data-categoria="<?= (int) $p['categoria_id'] ?>"
                             data-pesquisa="<?= e(mb_strtolower($p['nome'] . ' ' . ($p['descricao'] ?? ''))) ?>"
                             style="--cat: <?= e($p['cor']) ?>; animation-delay: <?= $i * 0.05 ?>s">

                        <div class="prato-topo">
                            <div class="prato-icone"><?= icone_categoria((string) $p['icone_svg']) ?></div>
                            <span class="prato-preco"><?= dinheiro((float) $p['preco']) ?></span>
                        </div>

                        <h3><?= e($p['nome']) ?></h3>
                        <p><?= e($p['descricao']) ?></p>

                        <div class="prato-tags">
                            <?php if ((int) $p['destaque'] === 1): ?>
                                <span class="tag tag-chef"><?= icone('estrela', '', 12) ?> Escolha do Chef</span>
                            <?php endif; ?>
                            <?php foreach (etiquetas_ementa($p['etiquetas']) as $et): ?>
                                <span class="tag <?= classe_etiqueta($et) ?>">
                                    <?= $et === 'vegetariano' ? icone('folha', '', 12) : '' ?>
                                    <?= e(rotulo_etiqueta($et)) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <div class="prato-rodape">
                            <span class="pequeno texto-3"><?= e($p['categoria']) ?></span>
                            <button type="button" class="btn-mais" title="Adicionar ao carrinho"
                                    data-add="<?= (int) $p['id'] ?>"
                                    data-nome="<?= e($p['nome']) ?>"
                                    data-preco="<?= e((string) $p['preco']) ?>"><?= icone('mais') ?></button>
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
                <div class="marca" style="margin-bottom:14px">
                    <span class="logo"><?= icone('talheres') ?></span>
                    <span>Tábua &amp; Ordem<small>restaurante &amp; cafetaria</small></span>
                </div>
                <p class="pequeno texto-2" style="max-width:320px">
                    Uma cozinha de família no centro do Porto. Servimos almoço, jantar e pequeno-almoço
                    todos os dias, com reservas para grupos e eventos privados.
                </p>
                <a href="#ementa" class="btn btn-primario btn-sm mt-1"><?= icone('carrinho-mais', '', 15) ?> Pedir agora</a>
            </div>

            <div>
                <h3 class="mb-2">Contactos</h3>
                <div class="contacto-item">
                    <?= icone('pin') ?>
                    <span><strong>Morada</strong>Rua das Flores, 24<br>4050-262 Porto, Portugal</span>
                </div>
                <div class="contacto-item">
                    <?= icone('telefone') ?>
                    <span><strong>Telefone</strong>+351 220 145 900</span>
                </div>
                <div class="contacto-item">
                    <?= icone('email') ?>
                    <span><strong>Email</strong>geral@tabuaeordem.pt</span>
                </div>
            </div>

            <div>
                <h3 class="mb-2">Horários</h3>
                <div class="contacto-item">
                    <?= icone('relogio') ?>
                    <span><strong>Segunda a Sábado</strong>12h00 – 15h00 / 19h00 – 23h00</span>
                </div>
                <div class="contacto-item">
                    <?= icone('relogio') ?>
                    <span><strong>Domingo</strong>12h00 – 15h00 (brunch)</span>
                </div>
                <div class="contacto-item">
                    <?= icone('saco') ?>
                    <span><strong>Encomendas</strong>Telemóvel ou no site, sem custo adicional</span>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- ===================== RODAPÉ ===================== -->
<footer class="rodape">
    <div class="container rodape-grelha">
        <p>© <?= date('Y') ?> Tábua &amp; Ordem · SaborPro — Sistema Inteligente de Pedidos e Gestão</p>
        <div class="d-flex-wrap">
            <a href="gestao.php" class="btn btn-fantasma btn-sm"><?= icone('chave', '', 14) ?> Acesso Reservado / Gestão</a>
        </div>
    </div>
</footer>

<!-- ===================== CARRINHO FLUTUANTE ===================== -->
<button class="botao-carrinho-flutuante vazio" id="btnCarrinho" aria-label="Abrir carrinho">
    <?= icone('carrinho') ?>
    <span class="contador" id="contadorCarrinho">0</span>
    <span class="sr-only">Carrinho</span>
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
            <p class="painel-titulo">Como prefere?</p>
            <div class="opcoes-pedido">
                <label class="opcao-pedido" data-opcao="takeaway">
                    <input type="radio" name="tipo_pedido" value="Takeaway" checked>
                    <?= icone('saco') ?>
                    <span>
                        <span class="tit">Takeaway</span>
                        <span class="desc">Para levar · cerca de 20 min</span>
                    </span>
                </label>
                <label class="opcao-pedido" data-opcao="mesa">
                    <input type="radio" name="tipo_pedido" value="Na Mesa">
                    <?= icone('mesa') ?>
                    <span>
                        <span class="tit">Na Mesa</span>
                        <span class="desc">Consumir no restaurante</span>
                    </span>
                </label>
            </div>

            <label class="campo d-none" id="blocoMesa">
                <span>Escolha a mesa</span>
                <select name="mesa_numero" id="mesaPublica">
                    <?php foreach ($mesas as $m): ?>
                        <option value="<?= (int) $m['numero'] ?>">Mesa <?= (int) $m['numero'] ?> · <?= (int) $m['capacidade'] ?> lugares</option>
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
                <textarea id="clienteObs" placeholder="Sem allergies, pedido especial…" maxlength="255"></textarea>
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

<!-- ===================== MODAL DE ACESSO RESERVADO ===================== -->
<div class="modal" id="modalLogin" role="dialog" aria-modal="true">
    <div class="modal-caixa">
        <button class="modal-fechar" data-modal-fechar aria-label="Fechar"><?= icone('x') ?></button>

        <h3>Acesso Reservado · Gestão</h3>
        <p class="sub-modal">Área exclusiva para administração e equipa do restaurante.</p>

        <?php if ($erroLogin !== ''): ?>
            <div class="alerta alerta-erro"><?= icone('alerta') ?><div><?= e($erroLogin) ?></div></div>
        <?php endif; ?>

        <form method="post" action="index.php" data-validar novalidate>
            <input type="hidden" name="acao" value="login">

            <label class="campo">
                <span>E-mail</span>
                <input type="email" name="email" required placeholder="admin@saborpro.pt"
                       value="<?= e($_POST['email'] ?? '') ?>" autocomplete="username">
            </label>

            <label class="campo">
                <span>Palavra-passe</span>
                <input type="password" name="palavra_passe" required data-min="4" placeholder="••••••" autocomplete="current-password">
            </label>

            <button type="submit" class="btn btn-primario btn-bloco btn-lg">
                <?= icone('sair') ?> Entrar na gestão
            </button>
        </form>

        <div class="cartao mt-3" style="padding:16px">
            <p class="rotulo mb-1">Contas de demonstração (palavra-passe: <code>123456</code>)</p>
            <p class="pequeno mb-0 texto-2">
                <strong>admin@saborpro.pt</strong> — Administração<br>
                <strong>empregado@saborpro.pt</strong> — Serviço de mesa<br>
                <strong>cozinha@saborpro.pt</strong> — Ecrã de cozinha
            </p>
        </div>
    </div>
</div>

<!-- ===================== TOAST DE CONFIRMAÇÃO ===================== -->
<div class="toast" id="toastPedido">
    <span class="icone-toast"><?= icone('ok') ?></span>
    <span>
        <strong>Pedido confirmado!</strong>
        <span id="toastTexto">A cozinha já recebeu o seu pedido.</span>
    </span>
</div>

<script src="js/main.js"></script>
<script>
/* Abre o modal de acesso reservado (ligado ao botão do cabeçalho) */
(function () {
    document.querySelectorAll('a[href="gestao.php"]').forEach(function (a) {
        a.addEventListener('click', function (ev) {
            ev.preventDefault();
            if (window.saborproAbrirModal) window.saborproAbrirModal('modalLogin');
        });
    });
})();
</script>
</body>
</html>
