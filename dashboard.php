<?php
/**
 * SaborPro :: dashboard.php
 * -------------------------------------------------------------
 * Atalho directo ao painel de gestão.
 * O conteúdo real vive em includes/painel_resumo.php (partilhado com gestao.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

exigir_login();

$paginaTitulo = 'Painel de Operações';
$paginaAtiva  = 'gestao';
$autoRefresh  = 90;
$alertas = [];

require_once __DIR__ . '/includes/cabecalho.php';
require_once __DIR__ . '/includes/painel_resumo.php';
require_once __DIR__ . '/includes/rodape.php';
