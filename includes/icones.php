<?php
/**
 * SaborPro :: includes/icones.php
 * ---------------------------------------------------------
 * Ícones SVG 100% inline (sem bibliotecas externas).
 * Uso: <?= icone('prato', 'classe-opcional') ?>
 */

declare(strict_types=1);

function icone(string $nome, string $classe = '', int $tamanho = 0): string
{
    // Os traços usam currentColor para herdar a cor do contexto.
    $caminhos = [
        'prato'     => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.5"/>',
        'entrada'   => '<path d="M4 13h16a8 8 0 0 1-16 0Z"/><path d="M9 4c-.6.9-.6 1.7 0 2.6M13 3c-.6.9-.6 1.7 0 2.6"/>',
        'prato-almoco' => '<path d="M3 12h18"/><path d="M5 12a7 7 0 0 1 14 0Z"/><path d="M2 19h20"/>',
        'doce'      => '<path d="M4 13h16l-1.5 6h-13Z"/><path d="M6 13a6 6 0 0 1 12 0"/><path d="M12 7V4"/><circle cx="12" cy="3" r="1"/>',
        'bebida'    => '<path d="M7 4h10l-1 16H8Z"/><path d="M8 9h8"/><path d="M14 4 17 2"/>',
        'cafe'      => '<path d="M4 9h12v4a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5Z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16"/><path d="M4 21h16"/><path d="M7 3v2M11 3v2"/>',
        'acompanhamento' => '<path d="M12 21c-5.5 0-8.5-3-8.5-8 5.5 0 8.5 3 8.5 8Z"/><path d="M12 21c5.5 0 8.5-3 8.5-8-5.5 0-8.5 3-8.5 8Z"/><path d="M12 21V11"/>',
        'mesa'      => '<path d="M3 10h18"/><path d="M5 10 3 21M19 10l2 11"/><path d="M12 10v11"/>',
        'utilizadores' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.5a3 3 0 0 1 0 5"/><path d="M17.5 20a6 6 0 0 0-2.2-4.6"/>',
        'carrinho'  => '<path d="M3 4h2l2.2 11h10.6L20 7H6"/><circle cx="9" cy="19" r="1.6"/><circle cx="17" cy="19" r="1.6"/>',
        'painel'    => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'cozinha'   => '<path d="M6 3v8a3 3 0 0 0 6 0V3"/><path d="M9 11v10"/><path d="M17 3c-1.5 2-2 4-2 6s.8 3 2 3 2-1 2-3-.5-4-2-6Z"/><path d="M17 12v9"/>',
        'garfo'     => '<path d="M6 3v6a2 2 0 0 0 4 0V3"/><path d="M8 9v12"/><path d="M16 3c-1.2 1.6-1.6 3.4-1.6 5.2 0 1.6.7 2.3 1.6 2.3S17.6 9.8 17.6 8.2C17.6 6.4 17 4.6 16 3Z"/><path d="M16 10.5V21"/>',
        'sair'      => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 16l-4-4 4-4"/><path d="M6 12h11"/>',
        'mais'      => '<path d="M12 5v14M5 12h14"/>',
        'menos'     => '<path d="M5 12h14"/>',
        'lixo'      => '<path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13"/>',
        'x'         => '<path d="M6 6l12 12M18 6L6 18"/>',
        'ok'        => '<path d="M4 12.5 9 17.5 20 6.5"/>',
        'relogio'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/>',
        'euro'      => '<path d="M18 6.5A7 7 0 1 0 18 17.5"/><path d="M4.5 10.5h9M4.5 13.5h9"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'grelha'    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'receita'   => '<path d="M6 3h12v18l-6-3-6 3Z"/>',
        'estrela'   => '<path d="m12 3 2.6 5.6 6 .8-4.4 4.2 1.1 6.1L12 16.8 6.7 19.7l1.1-6.1L3.4 9.4l6-.8Z"/>',
        'tenda'     => '<path d="M3 10h18L12 3Z"/><path d="M5 10v10M19 10v10M9 20v-6h6v6"/>',
        'olho'      => '<path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.8"/>',
        'procurar'  => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'edicao'    => '<path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17Z"/><path d="M14.5 6.5 17.5 9.5"/>',
        'alerta'    => '<path d="M12 4 2.5 20h19Z"/><path d="M12 10v4M12 17h.01"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'relógio'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'tenda-2'   => '<path d="M4 20h16"/><path d="M6 20V9l6-5 6 5v11"/><path d="M10 20v-6h4v6"/>',

        /* ---- Ícones adicionais (site público / sala 2D) ---- */
        'folha'      => '<path d="M4 20c9 1 16-4 16-14a5 5 0 0 0-5-5C8 1 4 6 4 13v7Z"/><path d="M4 20 14 10"/>',
        'saco'       => '<path d="M6 7h12l-1 14H7Z"/><path d="M9 7a3 3 0 0 1 6 0"/><path d="M6 11h12"/>',
        'talheres'   => '<path d="M7 3v7a2 2 0 0 0 4 0V3"/><path d="M9 10v11"/><path d="M16 3c-1.3 1.7-1.7 3.5-1.7 5.4 0 1.6.7 2.4 1.7 2.4s1.7-.8 1.7-2.4c0-1.9-.4-3.7-1.7-5.4Z"/><path d="M16 11v10"/>',
        'pin'        => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
        'telefone'   => '<path d="M4 5c0-1 1-2 2-2h2l2 5-2.5 1.5a12 12 0 0 0 5 5L14 12l5 2v2c0 1-1 2-2 2A15 15 0 0 1 4 5Z"/>',
        'email'      => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        'balcao'     => '<path d="M3 10h18"/><path d="M5 10v9M19 10v9"/><path d="M3 10a9 9 0 0 1 18 0"/>',
        'sala'       => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="10" r="2"/><rect x="12" y="8" width="6" height="4" rx="1"/><circle cx="9" cy="16" r="2"/><rect x="13" y="15" width="5" height="3" rx="1"/>',
        'janela'     => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M12 3v18M4 12h16"/>',
        'fogo'       => '<path d="M12 22c4 0 6-2.7 6-6 0-4.5-4-6.5-4-10 0 0-3 1.5-3 5 0-1.5-1-2.5-1-2.5S7 10 6 13.5C5.4 16 6 22 12 22Z"/>',
        'seta-direita' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'seta-esquerda' => '<path d="M19 12H5"/><path d="m11 18-6-6 6-6"/>',
        'buscar'     => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'carrinho-mais' => '<path d="M3 4h2l2.2 11h10.6L20 7H6"/><circle cx="9" cy="19" r="1.6"/><circle cx="17" cy="19" r="1.6"/><path d="M17 12v6M14 15h6"/>',
        'copo'       => '<path d="M6 3h12l-1.5 17a1.5 1.5 0 0 1-1.5 1.4H9A1.5 1.5 0 0 1 7.5 20Z"/><path d="M6 9h12"/>',
        'terra'      => '<path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/>',
        'coracao'    => '<path d="M12 20s-7-4.4-7-9.3A4 4 0 0 1 12 8a4 4 0 0 1 7 2.7C19 15.6 12 20 12 20Z"/>',
        'chave'      => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8 2 2-2 2 2 2-2 2-2-2-2 2"/>',
    ];

    $conteudo = $caminhos[$nome] ?? $caminhos['prato'];

    $atributos = '';
    if ($classe !== '') {
        $atributos .= ' class="' . htmlspecialchars($classe, ENT_QUOTES) . '"';
    }
    if ($tamanho > 0) {
        $atributos .= ' width="' . $tamanho . '" height="' . $tamanho . '"';
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"'
        . $atributos . '>' . $conteudo . '</svg>';
}

/**
 * Colecção de ícones de categoria disponível no formulário de gestão.
 * A chave é gravada em categorias.icone_svg; o valor é o rótulo humano.
 */
function icones_categoria_opcoes(): array
{
    return [
        'entrada'        => 'Entradas',
        'prato'          => 'Pratos Principais',
        'doce'           => 'Sobremesas',
        'bebida'         => 'Bebidas',
        'cafe'           => 'Cafés',
        'acompanhamento' => 'Acompanhamentos',
    ];
}

/**
 * Normaliza o valor gravado em categorias.icone_svg para uma chave
 * canónica da colecção. Aceita nomes antigos/plurais e valores inválidos.
 */
function icone_categoria_chave(string $chave): string
{
    $mapa = [
        'entrada'           => 'entrada',
        'entradas'          => 'entrada',
        'prato'             => 'prato',
        'pratos'            => 'prato',
        'prato-principal'   => 'prato',
        'pratos-principais' => 'prato',
        'principal'         => 'prato',
        'prato-almoco'      => 'prato',
        'doce'              => 'doce',
        'doces'             => 'doce',
        'sobremesa'         => 'doce',
        'sobremesas'        => 'doce',
        'bebida'            => 'bebida',
        'bebidas'           => 'bebida',
        'copo'              => 'bebida',
        'cafe'              => 'cafe',
        'cafes'             => 'cafe',
        'acompanhamento'    => 'acompanhamento',
        'acompanhamentos'   => 'acompanhamento',
    ];

    $normalizada = strtolower(trim($chave));
    return $mapa[$normalizada] ?? 'prato';
}

/**
 * Converte o nome do ícone gravado na BD (categorias.icone_svg)
 * para SVG inline com a classe de tamanho controlado.
 */
function icone_categoria(string $chave): string
{
    return icone(icone_categoria_chave($chave), 'icone-categoria');
}

/**
 * Logótipo oficial do SaborPro (SVG inline, sem imagens externas).
 *  - Ícone: cloche de restaurante estilizada
 *  - Texto: "Sabor" + etiqueta "Pro"
 */
function logo_saborpro(string $variante = 'claro', string $subtitulo = 'restaurante · porto', string $href = 'index.php'): string
{
    $classe = 'marca' . ($variante === 'escuro' ? ' marca-escuro' : '');

    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . $classe . '" aria-label="SaborPro — página inicial">'
        . '<span class="marca-logo" aria-hidden="true">'
        . '<svg viewBox="0 0 44 44" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
        . '<path d="M4 33h36"/>'
        . '<path d="M8 33a14 14 0 0 1 28 0"/>'
        . '<path d="M13 37.5h18"/>'
        . '<path d="M22 19v-3.2"/>'
        . '<circle cx="22" cy="13.2" r="2.5" fill="currentColor" stroke="none"/>'
        . '</svg>'
        . '</span>'
        . '<span class="marca-texto">'
        . '<span class="marca-nome">Sabor<span class="marca-pro">Pro</span></span>'
        . '<small>' . htmlspecialchars($subtitulo, ENT_QUOTES, 'UTF-8') . '</small>'
        . '</span>'
        . '</a>';
}

/**
 * Estrelas de classificação (0 a 5). Suporta meias estrelas visuais
 * através de uma camada dourada com largura percentual.
 */
function estrelas(float $nota, int $tamanho = 16): string
{
    $nota = max(0.0, min(5.0, $nota));
    $pct  = ($nota / 5) * 100;
    $estrela = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $tamanho . '" height="' . $tamanho . '">'
        . '<path d="m12 2.6 2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.5l-5.9 3.1 1.2-6.5L2.5 9.5l6.6-.9Z"/></svg>';

    return '<span class="estrelas" style="--nota:' . $pct . '%" role="img" aria-label="' . number_format($nota, 1, ',', '.') . ' de 5 estrelas">'
        . '<span class="estrelas-fundo" aria-hidden="true">' . str_repeat($estrela, 5) . '</span>'
        . '<span class="estrelas-ouro" aria-hidden="true">' . str_repeat($estrela, 5) . '</span>'
        . '</span>';
}
