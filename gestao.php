<?php
/**
 * SaborPro :: gestao.php
 * -------------------------------------------------------------
 * PORTAL DE GESTÃO (tema claro técnico)
 *  - Sem sessão -> ecrã de login
 *  - Com sessão -> painel de operações
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

/* ===================== SEM SESSÃO -> LOGIN ===================== */
if (!autenticado()) {
    $fotoLogin = 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1600&q=80&auto=format&fit=crop';
    ?>
    <!DOCTYPE html>
    <html lang="pt-PT">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso Reservado · SaborPro</title>
    <link rel="stylesheet" href="css/estilo.css">
    </head>
    <body class="tema-gestao">
    <div class="pagina">
        <header class="nav">
            <div class="container nav-inner">
                <?= logo_saborpro('claro', 'portal de gestão', 'index.php') ?>
                <div class="nav-util">
                    <a href="index.php" class="btn btn-fantasma btn-sm"><?= icone('seta-direita', '', 14) ?> Voltar ao site</a>
                </div>
            </div>
        </header>

        <main class="conteudo">
            <div class="container" style="max-width:920px">
                <div class="grelha" style="grid-template-columns:1fr 380px;gap:40px;align-items:center;min-height:64vh">

                    <div>
                        <span class="etiqueta estado-preparacao" style="margin-bottom:16px">
                            <span class="ponto"></span> Área reservada à equipa
                        </span>
                        <h1 style="font-size:2.1rem;margin-bottom:14px">Operação do restaurante,<br>num só painel.</h1>
                        <p style="max-width:440px">
                            Faturação em tempo real, ocupação da sala, pedidos do site e controlo
                            de ementa — com uma planta de sala desenhada à escala.
                        </p>

                        <div class="grelha g-2 mt-3" style="max-width:430px">
                            <div class="cartao" style="padding:14px">
                                <div class="icone-estat" style="width:32px;height:32px;border-radius:6px;display:grid;place-items:center;background:#EFF6FF;color:#1D4ED8;margin-bottom:8px"><?= icone('sala') ?></div>
                                <div class="negrito" style="font-size:.84rem">Planta 2D</div>
                                <div class="pequeno texto-3" style="font-size:.72rem">Mesas, cadeiras e balcão</div>
                            </div>
                            <div class="cartao" style="padding:14px">
                                <div class="icone-estat" style="width:32px;height:32px;border-radius:6px;display:grid;place-items:center;background:#ECFDF5;color:#059669;margin-bottom:8px"><?= icone('cozinha') ?></div>
                                <div class="negrito" style="font-size:.84rem">Cozinha (KDS)</div>
                                <div class="pequeno texto-3" style="font-size:.72rem">Fluxo em 1 clique</div>
                            </div>
                        </div>
                    </div>

                    <div class="login-caixa">
                        <h2 style="font-size:1.3rem;margin-bottom:4px">Iniciar sessão</h2>
                        <p class="pequeno texto-3 mb-3" style="margin-top:0">Acesso exclusivo a administração.</p>

                        <?php if ($erroLogin !== ''): ?>
                            <div class="alerta alerta-erro"><?= icone('alerta') ?><div><?= e($erroLogin) ?></div></div>
                        <?php endif; ?>

                        <form method="post" action="gestao.php">
                            <input type="hidden" name="acao" value="login">

                            <label class="campo">
                                <span>E-mail</span>
                                <input type="email" name="email" required placeholder="admin@saborpro.pt"
                                       value="<?= e($_POST['email'] ?? '') ?>" autocomplete="username">
                            </label>

                            <label class="campo">
                                <span>Palavra-passe</span>
                                <input type="password" name="palavra_passe" required placeholder="••••••"
                                       autocomplete="current-password">
                            </label>

                            <button type="submit" class="btn btn-primario btn-bloco">
                                <?= icone('sair') ?> Entrar
                            </button>
                        </form>

                        <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--borda)">
                            <p class="pequeno texto-3 mb-1" style="margin-bottom:4px">Conta de demonstração</p>
                            <p class="pequeno" style="margin:0;font-size:.78rem">
                                <strong>admin@saborpro.pt</strong> · <code>123456</code>
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </main>

        <footer class="rodape">
            <div class="container rodape-grelha">
                <p>© <?= date('Y') ?> SaborPro · Área de gestão reservada</p>
                <a href="index.php">Site público</a>
            </div>
        </footer>
    </div>
    <script src="js/main.js"></script>
    </body>
    </html>
    <?php
    exit;
}

/* ===================== COM SESSÃO -> PAINEL ===================== */
$paginaTitulo = 'Painel de Operações';
$paginaAtiva  = 'gestao';
$autoRefresh  = 90; // monitorização dos pedidos do site
$alertas = [];

if (($_GET['erro'] ?? '') === 'permissao') {
    $alertas[] = ['aviso', 'Não tem permissão para aceder a essa área.'];
}
if (($_GET['msg'] ?? '') === 'sucesso') {
    $alertas[] = ['ok', 'Operação concluída com sucesso.'];
}

require_once __DIR__ . '/includes/cabecalho.php';
require_once __DIR__ . '/includes/painel_resumo.php';
require_once __DIR__ . '/includes/rodape.php';
