<?php
/**
 * SaborPro :: includes/cabecalho.php
 * Cabeçalho comum (navegação) das páginas internas.
 * Requer que ligacao.php já tenha sido incluído.
 *
 * Variáveis opcionais:
 *  $paginaTitulo  -> título do documento
 *  $paginaAtiva   -> chave da página ativa (dashboard|mesas|ementa|pedidos|cozinha)
 */
require_once __DIR__ . '/icones.php';

$paginaTitulo = $paginaTitulo ?? 'SaborPro';
$paginaAtiva  = $paginaAtiva  ?? '';
$util         = utilizador_atual();

$itensNav = [
    'gestao'   => ['Gestão', 'painel'],
    'pedidos'  => ['Pedidos', 'carrinho'],
    'mesas'    => ['Sala 2D', 'sala'],
    'ementa'   => ['Ementa', 'receita'],
    'cozinha'  => ['Cozinha', 'cozinha'],
];
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($paginaTitulo) ?> · SaborPro</title>
<link rel="stylesheet" href="<?= base_url() ?>/css/estilo.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><text y='19' font-size='20'>&#127860;</text></svg>">
</head>
<body class="tema-gestao"<?= isset($autoRefresh) ? ' data-auto-refresh="' . (int) $autoRefresh . '"' : '' ?>>
<div class="pagina">

    <header class="nav">
        <div class="container nav-inner">
            <?= logo_saborpro('claro', 'gestão & pedidos', 'dashboard.php') ?>

            <nav class="nav-links" id="navLinks">
                <?php foreach ($itensNav as $chave => [$rotulo, $ic]): ?>
                    <a href="<?= $chave === 'gestao' ? 'gestao' : $chave ?>.php" class="<?= $paginaAtiva === $chave ? 'ativo' : '' ?>">
                        <?= e($rotulo) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="nav-util">
                <a href="index.php" class="btn btn-fantasma btn-sm" title="Ver o site público do restaurante">
                    <?= icone('olho', '', 15) ?> Site
                </a>
                <?php if (autenticado()): ?>
                    <div class="utilizador-chip">
                        <span class="avatar"><?= e(mb_strtoupper(mb_substr($util['nome'] ?? 'U', 0, 1))) ?></span>
                        <span>
                            <strong><?= e($util['nome'] ?? '') ?></strong><br>
                            <span class="pequeno texto-3"><?= e(ucfirst($util['tipo'] ?? '')) ?></span>
                        </span>
                    </div>
                    <a href="logout.php" class="btn btn-fantasma btn-sm" title="Terminar sessão">
                        <?= icone('sair') ?><span class="sr-only">Sair</span>
                    </a>
                <?php else: ?>
                    <a href="index.php" class="btn btn-primario btn-sm">Entrar</a>
                <?php endif; ?>
                <button class="menu-hamburguer" id="btnMenu" aria-label="Menu"><?= icone('menu') ?></button>
            </div>
        </div>
    </header>

    <main class="conteudo">
        <div class="container">
