<?php
/**
 * SaborPro :: gestao.php
 * -------------------------------------------------------------
 * PORTAL DE GESTÃO (Área Administrativa do Restaurante)
 *  - Sem sessão  -> ecrã de login exclusivo (tema escuro)
 *  - Com sessão  -> painel principal de gestão
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

$erroLogin = '';

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
            $erroLogin = 'Credenciais inválidas. Tente novamente.';
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

/* ===================== SEM SESSÃO -> ECRÃ DE LOGIN ===================== */
if (!autenticado()) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-PT">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Área de Gestão · SaborPro</title>
    <link rel="stylesheet" href="css/estilo.css">
    </head>
    <body class="tema-gestao">
    <div class="pagina">
        <header class="nav">
            <div class="container nav-inner">
                <a href="index.php" class="marca">
                    <span class="logo"><?= icone('talheres') ?></span>
                    <span>SaborPro<small>área de gestão</small></span>
                </a>
                <div class="nav-util">
                    <a href="index.php" class="btn btn-fantasma btn-sm"><?= icone('seta-direita', '', 15) ?> Voltar ao site</a>
                </div>
            </div>
        </header>

        <main class="conteudo">
            <div class="container" style="max-width:980px">
                <div class="grelha" style="grid-template-columns:1fr 1fr;align-items:center;gap:40px;min-height:70vh">

                    <!-- Lado esquerdo: apresentação -->
                    <div>
                        <span class="selo"><span class="ponto"></span> Área reservada à equipa</span>
                        <h1 class="titulo" style="margin-bottom:16px">Gestão completa<br>do restaurante.</h1>
                        <p class="legenda">
                            Monitore pedidos em tempo real, organize a ementa, acompanhe a cozinha
                            exibir o mapa da sala — tudo num só lugar.
                        </p>
                        <ul class="destaques">
                            <li><?= icone('ok') ?><span><strong>Pedidos em tempo real</strong>Site público e mesas ligados à mesma cozinha.</span></li>
                            <li><?= icone('ok') ?><span><strong>Planta de sala 2D</strong>Estado e conta de cada mesa num clique.</span></li>
                            <li><?= icone('ok') ?><span><strong>Ecrã de cozinha (KDS)</strong>Fluxo Pendente → Preparação → Pronto → Entregue.</span></li>
                        </ul>
                    </div>

                    <!-- Lado direito: login -->
                    <div class="cartao" style="padding:32px">
                        <h2 class="mb-1">Iniciar sessão</h2>
                        <p class="sub-modal mb-3">Acesso exclusivo a administração e equipa.</p>

                        <?php if ($erroLogin !== ''): ?>
                            <div class="alerta alerta-erro"><?= icone('alerta') ?><div><?= e($erroLogin) ?></div></div>
                        <?php endif; ?>

                        <form method="post" action="gestao.php" data-validar novalidate>
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
            </div>
        </main>

        <footer class="rodape">
            <div class="container rodape-grelha">
                <p>© <?= date('Y') ?> SaborPro · Área de Gestão Reservada</p>
                <a href="index.php" class="btn btn-fantasma btn-sm">Site público</a>
            </div>
        </footer>
    </div>
    <script src="js/main.js"></script>
    </body>
    </html>
    <?php
    exit;
}

/* ===================== COM SESSÃO -> PAINEL DE GESTÃO ===================== */
$paginaTitulo = 'Área de Gestão';
$paginaAtiva  = 'gestao';
$alertas = [];

if (isset($_GET['erro']) && $_GET['erro'] === 'permissao') {
    $alertas[] = ['aviso', 'Não tem permissão para aceder a essa área.'];
}
if (isset($_GET['msg']) && $_GET['msg'] === 'sucesso') {
    $alertas[] = ['ok', 'Operação concluída com sucesso.'];
}

require_once __DIR__ . '/includes/cabecalho.php';
require_once __DIR__ . '/includes/painel_resumo.php';
require_once __DIR__ . '/includes/rodape.php';
