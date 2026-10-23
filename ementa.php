<?php
/**
 * SaborPro :: ementa.php
 * -------------------------------------------------------------
 * Compatibilidade: a gestão da ementa e das categorias passou a
 * viver em gestao_dados.php, em conjunto com a moderação de
 * comentários. Mantém-se este endereço como redireccionamento.
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';

exigir_login(['admin', 'empregado']);

header('Location: gestao_dados.php');
exit;
