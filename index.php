<?php
/**
 * SaborPro :: index.php
 * Landing page pública + modal de login para funcionários.
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

/* Se já existe sessão ativa, leva direto ao painel. */
if (autenticado()) {
    header('Location: dashboard.php');
    exit;
}

$erroLogin = '';
$aviso = '';
$saiu = isset($_GET['saiu']);

/* ------------------------- PROCESSAR LOGIN ------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['palavra_passe'] ?? '';

    if ($email === '' || $pass === '') {
        $erroLogin = 'Preencha o e-mail e a palavra-passe.';
    } else {
        $stmt = $bd->prepare('SELECT id, nome, email, palavra_passe, tipo FROM utilizadores WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$u || !password_verify($pass, $u['palavra_passe'])) {
            $erroLogin = 'Credenciais inválidas. Verifique os dados de acesso.';
        } else {
            // Regenerar o ID de sessão (segurança contra fixação de sessão)
            session_regenerate_id(true);
            $_SESSION['utilizador'] = [
                'id'    => (int) $u['id'],
                'nome'  => $u['nome'],
                'email' => $u['email'],
                'tipo'  => $u['tipo'],
            ];
            $_SESSION['login_em'] = time();
            header('Location: dashboard.php');
            exit;
        }
    }
}

/* --------------------------- DADOS DA EMENTA ------------------------ */
$pratos = [];
$catStmt = $bd->prepare(
    'SELECT e.id, e.nome, e.descricao, e.preco, e.disponivel, c.nome AS categoria, c.icone_svg
       FROM ementa e
       JOIN categorias c ON c.id = e.categoria_id
      WHERE e.disponivel = 1
      ORDER BY c.id, e.nome
      LIMIT 8'
);
$catStmt->execute();
$resultado = $catStmt->get_result();
while ($linha = $resultado->fetch_assoc()) {
    $pratos[] = $linha;
}
$catStmt->close();

$categorias = [];
$resCat = $bd->query('SELECT id, nome, icone_svg FROM categorias ORDER BY id');
while ($c = $resCat->fetch_assoc()) {
    $categorias[] = $c;
}
$resCat->free();
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SaborPro · Sistema Inteligente de Pedidos e Gestão para Restaurantes</title>
<meta name="description" content="SaborPro — plataforma de gestão de pedidos, mesas e ementa para restaurantes e cafetarias.">
<link rel="stylesheet" href="css/estilo.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><text y='19' font-size='20'>&#127860;</text></svg>">
</head>
<body>
<div class="pagina">

    <!-- ===================== NAVEGAÇÃO ===================== -->
    <header class="nav">
        <div class="container nav-inner">
            <a href="index.php" class="marca">
                <span class="logo"><?= icone('garfo') ?></span>
                <span>SaborPro<small>gestão &amp; pedidos</small></span>
            </a>
            <nav class="nav-links" id="navLinks">
                <a href="#inicio" class="ativo">Início</a>
                <a href="#ementa">Ementa</a>
                <a href="#como-funciona">Como funciona</a>
            </nav>
            <div class="nav-util">
                <button class="btn btn-primario" data-modal-abrir="modalLogin">
                    <?= icone('utilizadores') ?> Área de acesso
                </button>
                <button class="menu-hamburguer" id="btnMenu" aria-label="Menu"><?= icone('menu') ?></button>
            </div>
        </div>
    </header>

    <main>
        <!-- ===================== HERO ===================== -->
        <section class="hero" id="inicio">
            <div class="container hero-grelha">
                <div class="hero-texto">
                    <span class="selo"><span class="ponto"></span> Sistema ativo · Service em curso</span>
                    <h1 class="titulo">Gestão de restaurante<br><em>simples, rápida e inteligente.</em></h1>
                    <p class="legenda">
                        O SaborPro une pedidos, mesas, ementa e cozinha num só ecrã.
                        Do toque na mesa ao prato pronto em segundos — sem papel, sem atrasos,
                        sem erros de conta.
                    </p>
                    <div class="hero-acoes">
                        <button class="btn btn-primario btn-lg" data-modal-abrir="modalLogin">
                            <?= icone('utilizadores') ?> Entrar no sistema
                        </button>
                        <a href="#como-funciona" class="btn btn-fantasma btn-lg">
                            <?= icone('info') ?> Como funciona
                        </a>
                    </div>

                    <div class="d-flex-wrap mt-3">
                        <span class="etiqueta badge-neutro"><?= icone('mesa', '', 14) ?> Mapa de mesas</span>
                        <span class="etiqueta badge-neutro"><?= icone('carrinho', '', 14) ?> POS integrado</span>
                        <span class="etiqueta badge-neutro"><?= icone('cozinha', '', 14) ?> Ecrã de cozinha</span>
                    </div>
                </div>

                <div class="hero-visual">
                    <span class="hero-forma forma-1"></span>
                    <span class="hero-forma forma-2"></span>
                    <div class="hero-cartao">
                        <div class="entre mb-2">
                            <h3 class="mb-0">Pedido em curso · Mesa 4</h3>
                            <span class="etiqueta estado-preparacao"><span class="ponto"></span>Em Preparação</span>
                        </div>
                        <div class="linha"><span class="nome">Bacalhau à Lagareiro</span><span class="preco">16,50 €</span></div>
                        <div class="linha"><span class="nome">Croquetes de Camarão</span><span class="preco">7,90 €</span></div>
                        <div class="linha"><span class="nome">Limonada de Hortelã</span><span class="preco">3,20 €</span></div>
                        <div class="linha">
                            <span class="nome negrito">Total</span>
                            <span class="preco" style="font-size:1.1rem">27,60 €</span>
                        </div>
                        <div class="mt-2 d-flex-wrap">
                            <span class="etiqueta estado-pronto"><span class="ponto"></span>Cozinha a avisar</span>
                            <span class="etiqueta badge-neutro">Serviço rápido</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== DESTAQUES DA EMENTA ===================== -->
        <section class="seccao" id="ementa">
            <div class="container">
                <div class="seccao-titulo">
                    <h2>Destaques da ementa</h2>
                    <p>Pratos Defendants no nosso cardápio, servidos todos os dias com ingredientes selecionados.</p>
                </div>

                <div class="grelha g-auto">
                    <?php foreach ($pratos as $i => $p): ?>
                        <article class="item-ementa d<?= ($i % 8) + 1 ?>" style="animation-delay: <?= $i * 0.05 ?>s">
                            <div class="topo">
                                <h3><?= e($p['nome']) ?></h3>
                                <span class="preco"><?= dinheiro((float) $p['preco']) ?></span>
                            </div>
                            <p><?= e($p['descricao']) ?></p>
                            <span class="etiqueta badge-neutro"><?= icone_categoria((string) $p['icone_svg']) ?> <?= e($p['categoria']) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ===================== COMO FUNCIONA ===================== -->
        <section class="seccao" id="como-funciona" style="background:linear-gradient(180deg,transparent,rgba(30,41,59,.45),transparent)">
            <div class="container">
                <div class="seccao-titulo">
                    <h2>Um fluxo de trabalho sem atritos</h2>
                    <p>Quatro passos entre a mesa e o prato pronto.</p>
                </div>

                <div class="grelha g-4">
                    <?php
                    $passos = [
                        ['mesa',    '1. Mesa',     'O mapa visual mostra de imediato o estado de cada mesa em tempo real.'],
                        ['carrinho','2. Pedido',   'Construa a conta em poucos cliques: toque nos artigos e finalize.'],
                        ['cozinha', '3. Cozinha',  'O chef recebe o pedido no ecrã da cozinha e avança o estado com 1 clique.'],
                        ['painel',  '4. Gestão',   'Faturamento, ocupação e pratos mais vendidos sempre atualizados.'],
                    ];
                    foreach ($passos as $p): ?>
                        <div class="cartao d2">
                            <div class="icone-estat" style="--cor: var(--primario)"><?= icone($p[0]) ?></div>
                            <h3 class="cartao-titulo"><?= e($p[1]) ?></h3>
                            <p class="mb-0 pequeno"><?= e($p[2]) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ===================== CTA ===================== -->
        <section class="seccao">
            <div class="container">
                <div class="cartao texto-centro" style="padding:44px 24px">
                    <h2>Pronto para servir?</h2>
                    <p class="mb-2">Aceda ao sistema com as credenciais de demonstração e comece a tomar pedidos.</p>
                    <button class="btn btn-primario btn-lg" data-modal-abrir="modalLogin">
                        <?= icone('utilizadores') ?> Entrar na área de gestão
                    </button>
                </div>
            </div>
        </section>
    </main>

    <!-- ===================== MODAL LOGIN ===================== -->
    <div class="modal" id="modalLogin" role="dialog" aria-modal="true" aria-labelledby="tituloLogin">
        <div class="modal-caixa">
            <button class="modal-fechar" data-modal-fechar aria-label="Fechar"><?= icone('x') ?></button>

            <h3 id="tituloLogin">Acesso ao sistema</h3>
            <p class="sub-modal">Introduza as credenciais atribuídas pela administração.</p>

            <?php if ($saiu): ?>
                <div class="alerta alerta-ok"><?= icone('ok') ?><div>Sessão terminada com sucesso. Até breve!</div></div>
            <?php endif; ?>

            <?php if ($erroLogin !== ''): ?>
                <div class="alerta alerta-erro">
                    <?= icone('alerta') ?>
                    <div><?= e($erroLogin) ?></div>
                </div>
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
                    <input type="password" name="palavra_passe" required data-min="4"
                           placeholder="••••••" autocomplete="current-password">
                </label>

                <button type="submit" class="btn btn-primario btn-bloco btn-lg">
                    <?= icone('sair') ?> Entrar
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

    <footer class="rodape">
        <div class="container rodape-grelha">
            <div class="marca">
                <span class="logo"><?= icone('garfo') ?></span>
                <span>SaborPro<small>sistema inteligente de pedidos</small></span>
            </div>
            <p>© <?= date('Y') ?> SaborPro · PHP + MySQL · Projeto Laragon</p>
        </div>
    </footer>
</div>

<script src="js/main.js"></script>
<script>
/* Abre automaticamente o modal de login quando há pedido de acesso/erro */
(function () {
    var abrir = <?= ($erroLogin !== '' || $saiu) ? 'true' : 'false' ?>;
    if (abrir && window.saborproAbrirModal) {
        window.saborproAbrirModal('modalLogin');
    }
})();
</script>
</body>
</html>
