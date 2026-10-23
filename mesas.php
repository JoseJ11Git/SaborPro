<?php
/**
 * SaborPro :: mesas.php
 * -------------------------------------------------------------
 * VISTA DE MESAS — desenho mínimo em quadrados
 *  · Cada mesa é um quadrado castanho-escuro uniforme
 *  · Número ao centro + indicador circular de estado
 *  · Modal simples: resumo, alteração rápida de estado e
 *    botão "+ Adicionar Produtos" que abre a lista de itens
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

exigir_login();

/* ----------------------------- DADOS --------------------------------- */
$mesas = [];
$r = $bd->query("SELECT m.id, m.numero, m.estado,
                        (SELECT p.id FROM pedidos p
                          WHERE p.mesa_id = m.id AND p.estado <> 'Entregue'
                          ORDER BY p.criado_em DESC LIMIT 1) AS pedido_ativo,
                        (SELECT COALESCE(SUM(p.total),0) FROM pedidos p
                          WHERE p.mesa_id = m.id AND p.estado <> 'Entregue') AS total_ativo,
                        (SELECT MIN(p.criado_em) FROM pedidos p
                          WHERE p.mesa_id = m.id AND p.estado <> 'Entregue') AS desde
                   FROM mesas m
                  WHERE m.numero > 0
                  ORDER BY m.numero");
while ($m = $r->fetch_assoc()) {
    $mesas[] = $m;
}
$r->free();

$resumo = ['Livre' => 0, 'Ocupada' => 0, 'Reservada' => 0];
foreach ($mesas as $m) {
    $estado = (string) $m['estado'];
    if (isset($resumo[$estado])) {
        $resumo[$estado]++;
    }
}

$itensMenu = [];
$r = $bd->query("SELECT e.id, e.nome, e.preco, c.nome AS categoria
                   FROM ementa e JOIN categorias c ON c.id = e.categoria_id
                  WHERE e.disponivel = 1
                  ORDER BY c.nome, e.nome");
while ($i = $r->fetch_assoc()) {
    $itensMenu[] = $i;
}
$r->free();

$paginaTitulo = 'Mesas';
$paginaAtiva  = 'mesas';

require_once __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina entre-topo">
    <div>
        <h1 class="mb-0">Mesas</h1>
        <p class="subtitulo">Clique numa mesa para ver o resumo e alterar o estado.</p>
    </div>
    <div class="d-flex-wrap">
        <span class="etiqueta estado-pronto"><span class="ponto"></span><span data-contagem="Livre"><?= $resumo['Livre'] ?></span> livres</span>
        <span class="etiqueta estado-ocupado"><span class="ponto"></span><span data-contagem="Ocupada"><?= $resumo['Ocupada'] ?></span> ocupadas</span>
        <span class="etiqueta estado-reservado"><span class="ponto"></span><span data-contagem="Reservada"><?= $resumo['Reservada'] ?></span> reservadas</span>
        <a href="pedidos.php" class="btn btn-primario"><?= icone('mais') ?> Novo pedido</a>
    </div>
</div>

<div class="mesas-painel">
    <div class="mesas-legenda">
        <span><i class="indicador livre"></i> Livre</span>
        <span><i class="indicador ocupada"></i> Ocupada</span>
        <span><i class="indicador reservada"></i> Reservada</span>
    </div>

    <div class="mesas-grelha" id="grelhaMesas">
        <?php if (!$mesas): ?>
            <p class="texto-3">Ainda não existem mesas configuradas.</p>
        <?php else: ?>
            <?php foreach ($mesas as $m): ?>
                <?php
                $estado = (string) $m['estado'];
                $classeEstado = match ($estado) {
                    'Ocupada'   => 'estado-ocupada',
                    'Reservada' => 'estado-reservada',
                    default     => 'estado-livre',
                };
                $totalAtivo = (float) $m['total_ativo'];
                ?>
                <button type="button"
                        class="mesa-quadrada <?= $classeEstado ?>"
                        data-mesa-id="<?= (int) $m['id'] ?>"
                        data-mesa-numero="<?= (int) $m['numero'] ?>"
                        data-mesa-estado="<?= e($estado) ?>"
                        data-mesa-total="<?= e(number_format($totalAtivo, 2, '.', '')) ?>"
                        data-mesa-pedido="<?= (int) ($m['pedido_ativo'] ?? 0) ?>"
                        aria-label="Mesa <?= (int) $m['numero'] ?> — <?= e($estado) ?>">
                    <span class="mesa-numero"><?= (int) $m['numero'] ?></span>
                    <span class="mesa-indicador" title="<?= e($estado) ?>"></span>
                </button>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ===================== MODAL DA MESA ===================== -->
<div class="modal" id="modalMesa" role="dialog" aria-modal="true" aria-labelledby="tituloMesa">
    <div class="modal-caixa modal-mesa">
        <button class="modal-fechar" data-modal-fechar aria-label="Fechar"><?= icone('x') ?></button>

        <div class="modal-mesa-topo">
            <div>
                <span class="eyebrow">Mesa</span>
                <h3 id="tituloMesa" class="mb-0">Mesa —</h3>
            </div>
            <span class="etiqueta estado-pronto" id="mesaEstadoTag">
                <span class="ponto"></span><span id="mesaEstadoTexto">Livre</span>
            </span>
        </div>

        <div class="mesa-resumo">
            <div class="mesa-resumo-item">
                <span>Estado actual</span>
                <strong id="mesaResumoEstado">Livre</strong>
            </div>
            <div class="mesa-resumo-item">
                <span>Consumo</span>
                <strong id="mesaResumoTotal">0,00 €</strong>
            </div>
            <div class="mesa-resumo-item">
                <span>Pedido</span>
                <strong id="mesaResumoPedido">Sem pedido activo</strong>
            </div>
        </div>

        <div class="mesa-estados" role="group" aria-label="Alterar estado da mesa">
            <span class="mesa-estados-rotulo">Alteração rápida</span>
            <div class="mesa-estados-botoes">
                <button type="button" class="mesa-estado-btn" data-novo-estado="Livre">
                    <i class="indicador livre"></i> Livre
                </button>
                <button type="button" class="mesa-estado-btn" data-novo-estado="Ocupada">
                    <i class="indicador ocupada"></i> Ocupada
                </button>
                <button type="button" class="mesa-estado-btn" data-novo-estado="Reservada">
                    <i class="indicador reservada"></i> Reservada
                </button>
            </div>
        </div>

        <div class="d-flex-wrap mt-2">
            <button type="button" class="btn btn-primario" id="btnAdicionarProdutos">
                <?= icone('mais') ?> Adicionar Produtos
            </button>
            <button type="button" class="btn btn-fantasma" id="btnFecharConta">
                <?= icone('ok') ?> Fechar conta
            </button>
            <a href="pedidos.php" class="btn btn-fantasma"><?= icone('carrinho') ?> Abrir POS</a>
        </div>

        <!-- Lista de produtos, escondida até clicar em "+ Adicionar Produtos" -->
        <div class="mesa-adicionar d-none" id="mesaAdicionarPainel">
            <div class="entre mb-1">
                <h4 class="mb-0" style="font-size:.9rem">Escolher produto</h4>
                <button type="button" class="btn btn-fantasma btn-sm" id="btnFecharAdicionar">Fechar</button>
            </div>

            <?php if (!$itensMenu): ?>
                <p class="texto-3 pequeno mb-0">Não existem produtos disponíveis na ementa.</p>
            <?php else: ?>
                <div class="mesa-produtos-lista">
                    <?php foreach ($itensMenu as $item): ?>
                        <div class="mesa-produto">
                            <div>
                                <strong><?= e($item['nome']) ?></strong>
                                <span><?= e($item['categoria']) ?> · <?= dinheiro((float) $item['preco']) ?></span>
                            </div>
                            <button type="button" class="btn btn-fantasma btn-sm"
                                    data-adicionar-item="<?= (int) $item['id'] ?>">
                                <?= icone('mais', '', 14) ?> Adicionar
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <p class="mesa-nota">O consumo é actualizado automaticamente ao adicionar produtos.</p>
    </div>
</div>

<script>
(function () {
    'use strict';

    /* A base é injectada pelo PHP no <head>. O fallback cobre qualquer
       inclusão directa e resolve correctamente subpastas (/SaborPro/…). */
    var base = (typeof window.SABORPRO_BASE_URL === 'string')
        ? window.SABORPRO_BASE_URL.replace(/\/+$/, '')
        : window.location.pathname.replace(/\/[^/]*$/, '').replace(/\/+$/, '');
    var apiMesa = base + '/api/mesa.php';

    var grelha = document.getElementById('grelhaMesas');
    var modal = document.getElementById('modalMesa');
    if (!grelha || !modal) return;

    var mesaActual = null;
    var titulo = document.getElementById('tituloMesa');
    var estadoTag = document.getElementById('mesaEstadoTag');
    var estadoTexto = document.getElementById('mesaEstadoTexto');
    var resumoEstado = document.getElementById('mesaResumoEstado');
    var resumoTotal = document.getElementById('mesaResumoTotal');
    var resumoPedido = document.getElementById('mesaResumoPedido');
    var painelAdicionar = document.getElementById('mesaAdicionarPainel');
    var btnAdicionar = document.getElementById('btnAdicionarProdutos');
    var btnFecharConta = document.getElementById('btnFecharConta');

    var rotulos = {
        Livre: 'Livre',
        Ocupada: 'Ocupada',
        Reservada: 'Reservada'
    };

    function dinheiro(v) {
        return (Number(v) || 0).toFixed(2).replace('.', ',') + ' €';
    }

    function chaveClasse(estado) {
        if (estado === 'Ocupada') return 'estado-ocupada';
        if (estado === 'Reservada') return 'estado-reservada';
        return 'estado-livre';
    }

    function classeEtiqueta(estado) {
        if (estado === 'Ocupada') return 'estado-ocupado';
        if (estado === 'Reservada') return 'estado-reservado';
        return 'estado-pronto';
    }

    function quadrado(id) {
        return grelha.querySelector('[data-mesa-id="' + id + '"]');
    }

    function lerJson(resposta) {
        var tipo = resposta.headers.get('content-type') || '';
        if (tipo.indexOf('application/json') === -1) {
            throw new Error('O servidor devolveu uma resposta inválida.');
        }
        return resposta.json();
    }

    function pedir(dados, concluir) {
        fetch(apiMesa, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(lerJson)
        .then(function (res) { concluir(res); })
        .catch(function (erro) {
            concluir({
                ok: false,
                erro: erro && erro.message ? erro.message : 'Sem ligação ao servidor.'
            });
        });
    }

    function actualizarContagens(estadoAntigo, estadoNovo) {
        if (estadoAntigo === estadoNovo) return;
        var a = document.querySelector('[data-contagem="' + estadoAntigo + '"]');
        var n = document.querySelector('[data-contagem="' + estadoNovo + '"]');
        if (a) a.textContent = Math.max(0, Number(a.textContent) - 1);
        if (n) n.textContent = Number(n.textContent) + 1;
    }

    function pintarQuadrado(el, estado) {
        if (!el) return;
        el.classList.remove('estado-livre', 'estado-ocupada', 'estado-reservada');
        el.classList.add(chaveClasse(estado));
        el.setAttribute('data-mesa-estado', estado);
        var ponto = el.querySelector('.mesa-indicador');
        if (ponto) ponto.title = rotulos[estado] || estado;
    }

    function pintarModal(estado, total, numero) {
        titulo.textContent = 'Mesa ' + numero;
        estadoTexto.textContent = rotulos[estado] || estado;
        estadoTag.className = 'etiqueta ' + classeEtiqueta(estado);
        resumoEstado.textContent = rotulos[estado] || estado;
        resumoTotal.textContent = dinheiro(total);
        resumoPedido.textContent = Number(total) > 0 ? 'Pedido activo' : 'Sem pedido activo';

        modal.querySelectorAll('[data-novo-estado]').forEach(function (b) {
            b.classList.toggle('activo', b.getAttribute('data-novo-estado') === estado);
        });
    }

    function abrirMesa(el) {
        var id = Number(el.getAttribute('data-mesa-id'));
        var numero = el.getAttribute('data-mesa-numero');
        var estado = el.getAttribute('data-mesa-estado') || 'Livre';
        var total = Number(el.getAttribute('data-mesa-total') || 0);

        mesaActual = id;
        pintarModal(estado, total, numero);
        painelAdicionar.classList.add('d-none');

        if (window.saborproAbrirModal) window.saborproAbrirModal('modalMesa');

        fetch(apiMesa + '?mesa=' + encodeURIComponent(id), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(lerJson)
            .then(function (res) {
                if (!res || !res.ok || !res.mesa) {
                    throw new Error((res && res.erro) || 'Não foi possível carregar a mesa.');
                }
                var totalApi = res.pedido ? Number(res.pedido.total) : 0;
                el.setAttribute('data-mesa-total', totalApi.toFixed(2));
                el.setAttribute('data-mesa-pedido', res.pedido ? res.pedido.id : 0);
                pintarQuadrado(el, res.mesa.estado);
                pintarModal(res.mesa.estado, totalApi, res.mesa.numero);
            })
            .catch(function (erro) {
                if (window.saborproToast) {
                    window.saborproToast((erro && erro.message) || 'Não foi possível carregar a mesa.');
                }
            });
    }

    grelha.addEventListener('click', function (ev) {
        var mesa = ev.target.closest('.mesa-quadrada');
        if (mesa) abrirMesa(mesa);
    });

    btnAdicionar.addEventListener('click', function () {
        painelAdicionar.classList.toggle('d-none');
        if (!painelAdicionar.classList.contains('d-none')) {
            painelAdicionar.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });

    var btnFecharAdicionar = document.getElementById('btnFecharAdicionar');
    if (btnFecharAdicionar) {
        btnFecharAdicionar.addEventListener('click', function () {
            painelAdicionar.classList.add('d-none');
        });
    }

    modal.querySelectorAll('[data-novo-estado]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!mesaActual) return;
            var novo = btn.getAttribute('data-novo-estado');
            var el = quadrado(mesaActual);
            var antigo = el ? el.getAttribute('data-mesa-estado') : 'Livre';

            btn.disabled = true;
            pedir({ acao: 'estado', mesa: mesaActual, estado: novo }, function (res) {
                btn.disabled = false;
                if (!res || !res.ok) {
                    if (window.saborproToast) {
                        window.saborproToast((res && res.erro) || 'Não foi possível alterar o estado.');
                    }
                    return;
                }
                pintarQuadrado(el, novo);
                actualizarContagens(antigo, novo);
                pintarModal(novo, Number(el ? el.getAttribute('data-mesa-total') : 0), el ? el.getAttribute('data-mesa-numero') : '');
                if (window.saborproToast) window.saborproToast('Mesa ' + (el ? el.getAttribute('data-mesa-numero') : '') + ' · ' + novo);
            });
        });
    });

    painelAdicionar.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-adicionar-item]');
        if (!btn || !mesaActual) return;

        var itemId = Number(btn.getAttribute('data-adicionar-item'));
        btn.disabled = true;

        pedir({ acao: 'adicionar', mesa: mesaActual, item: itemId, qtd: 1 }, function (res) {
            btn.disabled = false;
            if (!res || !res.ok) {
                if (window.saborproToast) {
                    window.saborproToast((res && res.erro) || 'Não foi possível adicionar o produto.');
                }
                return;
            }

            var el = quadrado(mesaActual);
            var total = Number(res.total || 0);
            if (el) {
                el.setAttribute('data-mesa-total', total.toFixed(2));
                if (el.getAttribute('data-mesa-estado') !== 'Ocupada') {
                    var antigo = el.getAttribute('data-mesa-estado');
                    pintarQuadrado(el, 'Ocupada');
                    actualizarContagens(antigo, 'Ocupada');
                }
            }

            pintarModal(el ? el.getAttribute('data-mesa-estado') : 'Ocupada', total, el ? el.getAttribute('data-mesa-numero') : '');
            if (window.saborproToast) window.saborproToast('Produto adicionado · ' + dinheiro(total));
        });
    });

    btnFecharConta.addEventListener('click', function () {
        if (!mesaActual) return;
        if (!window.confirm('Fechar a conta desta mesa?')) return;

        btnFecharConta.disabled = true;
        pedir({ acao: 'fechar', mesa: mesaActual }, function (res) {
            btnFecharConta.disabled = false;
            if (!res || !res.ok) {
                if (window.saborproToast) {
                    window.saborproToast((res && res.erro) || 'Não foi possível fechar a conta.');
                }
                return;
            }

            var el = quadrado(mesaActual);
            var antigo = el ? el.getAttribute('data-mesa-estado') : 'Ocupada';
            if (el) {
                pintarQuadrado(el, 'Livre');
                el.setAttribute('data-mesa-total', '0.00');
                el.setAttribute('data-mesa-pedido', '0');
            }
            actualizarContagens(antigo, 'Livre');
            pintarModal('Livre', 0, el ? el.getAttribute('data-mesa-numero') : '');
            painelAdicionar.classList.add('d-none');
            if (window.saborproToast) window.saborproToast('Conta encerrada. Mesa livre.');
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/rodape.php'; ?>
