<?php
/**
 * SaborPro :: gestao_dados.php
 * -------------------------------------------------------------
 * MÓDULO DE DADOS DO RESTAURANTE
 *  · CRUD da ementa (itens, preços, disponibilidade, traduções)
 *  · Gestão de categorias
 *  · Moderação de comentários / avaliações
 *
 * As alterações rápidas de disponibilidade e a moderação de
 * avaliações são feitas por AJAX (api/ementa_rapida.php e
 * api/avaliacao_admin.php), com actualização imediata do ecrã.
 */
declare(strict_types=1);

require_once __DIR__ . '/ligacao.php';
require_once __DIR__ . '/includes/icones.php';

exigir_login(['admin', 'empregado']);

$aviso = '';
$erro  = '';

/* ----------------------------- AÇÕES ------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    /* ---- Itens da ementa ---- */
    if ($acao === 'guardar_item') {
        $id         = (int) ($_POST['id'] ?? 0);
        $catId      = (int) ($_POST['categoria_id'] ?? 0);
        $nome       = trim($_POST['nome'] ?? '');
        $nomeEn     = trim($_POST['nome_en'] ?? '');
        $descricao  = trim($_POST['descricao'] ?? '');
        $descricaoEn = trim($_POST['descricao_en'] ?? '');
        $preco      = (float) ($_POST['preco'] ?? 0);
        $disp       = isset($_POST['disponivel']) ? 1 : 0;
        $destaque   = isset($_POST['destaque']) ? 1 : 0;
        $etiquetas  = trim($_POST['etiquetas'] ?? '');
        $imagem     = trim($_POST['imagem_url'] ?? '');

        if ($imagem !== '' && !preg_match('~^https?://~i', $imagem)) {
            $imagem = '';
        }

        if ($nome === '' || $catId <= 0 || $preco <= 0) {
            $erro = 'Preencha nome, categoria e um preço válido.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $bd->prepare('UPDATE ementa SET nome=?, nome_en=?, descricao=?, descricao_en=?, categoria_id=?, preco=?, disponivel=?, destaque=?, etiquetas=?, imagem_url=? WHERE id=?');
                    $stmt->bind_param('ssssidiissi', $nome, $nomeEn, $descricao, $descricaoEn, $catId, $preco, $disp, $destaque, $etiquetas, $imagem, $id);
                } else {
                    $stmt = $bd->prepare('INSERT INTO ementa (nome, nome_en, descricao, descricao_en, categoria_id, preco, disponivel, destaque, etiquetas, imagem_url) VALUES (?,?,?,?,?,?,?,?,?,?)');
                    $stmt->bind_param('ssssidiiss', $nome, $nomeEn, $descricao, $descricaoEn, $catId, $preco, $disp, $destaque, $etiquetas, $imagem);
                }
                $stmt->execute();
                $stmt->close();
                $aviso = $id > 0 ? 'Item actualizado com sucesso.' : 'Item adicionado à ementa.';
            } catch (Throwable $e) {
                error_log('SaborPro guardar_item: ' . $e->getMessage());
                $erro = 'Não foi possível guardar o item.';
            }
        }
    }

    if ($acao === 'eliminar_item') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = $bd->prepare('DELETE FROM ementa WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $aviso = 'Item removido da ementa.';
        } catch (Throwable $e) {
            error_log('SaborPro eliminar_item: ' . $e->getMessage());
            $erro = 'Não foi possível remover o item.';
        }
    }

    /* Fallback sem JavaScript — o botão normal usa AJAX. */
    if ($acao === 'alternar') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = $bd->prepare('UPDATE ementa SET disponivel = 1 - disponivel WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $aviso = 'Disponibilidade actualizada.';
        } catch (Throwable $e) {
            error_log('SaborPro alternar: ' . $e->getMessage());
            $erro = 'Não foi possível actualizar a disponibilidade.';
        }
    }

    /* ---- Categorias ---- */
    if ($acao === 'guardar_categoria') {
        $id    = (int) ($_POST['id'] ?? 0);
        $nome  = trim($_POST['nome'] ?? '');
        $icone = trim($_POST['icone_svg'] ?? 'prato');

        if ($nome === '') {
            $erro = 'Indique o nome da categoria.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $bd->prepare('UPDATE categorias SET nome=?, icone_svg=? WHERE id=?');
                    $stmt->bind_param('ssi', $nome, $icone, $id);
                } else {
                    $stmt = $bd->prepare('INSERT INTO categorias (nome, icone_svg) VALUES (?,?)');
                    $stmt->bind_param('ss', $nome, $icone);
                }
                $stmt->execute();
                $stmt->close();
                $aviso = $id > 0 ? 'Categoria actualizada.' : 'Categoria criada.';
            } catch (Throwable $e) {
                error_log('SaborPro guardar_categoria: ' . $e->getMessage());
                $erro = 'Não foi possível guardar a categoria.';
            }
        }
    }

    if ($acao === 'eliminar_categoria') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = $bd->prepare('DELETE FROM categorias WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $aviso = 'Categoria removida (itens associados também foram eliminados).';
        } catch (Throwable $e) {
            error_log('SaborPro eliminar_categoria: ' . $e->getMessage());
            $erro = 'Não foi possível remover a categoria.';
        }
    }
}

/* ----------------------------- DADOS -------------------------------- */
$categorias = [];
$r = $bd->query('SELECT id, nome, icone_svg FROM categorias ORDER BY nome');
while ($c = $r->fetch_assoc()) {
    $categorias[] = $c;
}
$r->free();

$itens = [];
$r = $bd->query("SELECT e.id, e.nome, e.nome_en, e.descricao, e.descricao_en, e.preco,
                        e.disponivel, e.etiquetas, e.destaque, e.imagem_url,
                        e.categoria_id, c.nome AS categoria, c.icone_svg
                   FROM ementa e JOIN categorias c ON c.id = e.categoria_id
                  ORDER BY c.nome, e.destaque DESC, e.nome");
while ($i = $r->fetch_assoc()) {
    $itens[] = $i;
}
$r->free();

$avaliacoes = [];
$r = $bd->query("SELECT id, nome_cliente, classificacao, comentario, criado_em, visivel
                   FROM avaliacoes
                  ORDER BY visivel ASC, criado_em DESC
                  LIMIT 60");
while ($a = $r->fetch_assoc()) {
    $avaliacoes[] = $a;
}
$r->free();

$r = $bd->query("SELECT COUNT(*) AS total, COALESCE(AVG(classificacao),0) AS media,
                        SUM(visivel = 0) AS pendentes
                   FROM avaliacoes");
$resAval = $r->fetch_assoc();
$r->free();

$avalTotal     = (int) ($resAval['total'] ?? 0);
$avalMedia     = $avalTotal > 0 ? round((float) $resAval['media'], 1) : 0.0;
$avalPendentes = (int) ($resAval['pendentes'] ?? 0);

$totalItens      = count($itens);
$itensDisponiveis = count(array_filter($itens, fn($i) => (int) $i['disponivel'] === 1));

$catIcons = ['entrada', 'prato', 'doce', 'bebida', 'cafe'];

$paginaTitulo = 'Ementa & Dados';
$paginaAtiva  = 'gestao_dados';

require_once __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina entre-topo">
    <div>
        <h1 class="mb-0">Ementa &amp; Dados</h1>
        <p class="subtitulo">Pratos, categorias, disponibilidade e moderação de avaliações.</p>
    </div>
    <div class="d-flex-wrap">
        <a href="dashboard.php" class="btn btn-fantasma"><?= icone('seta-esquerda') ?> Dashboard</a>
        <button class="btn btn-fantasma" data-modal-abrir="modalCategoria"><?= icone('grelha') ?> Categorias</button>
        <button class="btn btn-primario" data-modal-abrir="modalItem" id="btnNovoItem"><?= icone('mais') ?> Novo item</button>
    </div>
</div>

<?php if ($aviso !== ''): ?>
    <div class="alerta alerta-ok" data-autoclose><?= icone('ok') ?><div><?= e($aviso) ?></div></div>
<?php endif; ?>
<?php if ($erro !== ''): ?>
    <div class="alerta alerta-erro" data-autoclose><?= icone('alerta') ?><div><?= e($erro) ?></div></div>
<?php endif; ?>

<!-- ===================== INDICADORES ===================== -->
<div class="grelha g-4 mb-2">
    <div class="cartao estatistica" style="--cor:#1D4ED8">
        <div class="icone-estat"><?= icone('receita') ?></div>
        <p class="rotulo">Itens na ementa</p>
        <div class="valor"><?= $totalItens ?></div>
        <p class="nota"><?= count($categorias) ?> categorias activas</p>
    </div>
    <div class="cartao estatistica" style="--cor:#059669">
        <div class="icone-estat"><?= icone('ok') ?></div>
        <p class="rotulo">Disponíveis</p>
        <div class="valor"><?= $itensDisponiveis ?></div>
        <p class="nota"><?= $totalItens - $itensDisponiveis ?> esgotado(s)</p>
    </div>
    <div class="cartao estatistica" style="--cor:#D97706">
        <div class="icone-estat"><?= icone('estrela') ?></div>
        <p class="rotulo">Nota média</p>
        <div class="valor"><?= number_format($avalMedia, 1, ',', '.') ?></div>
        <p class="nota"><?= $avalTotal ?> avaliação(ões)</p>
    </div>
    <div class="cartao estatistica" style="--cor:#7C3AED">
        <div class="icone-estat"><?= icone('olho') ?></div>
        <p class="rotulo">Por moderar</p>
        <div class="valor" data-aval-pendentes><?= $avalPendentes ?></div>
        <p class="nota">Comentários aguardam decisão</p>
    </div>
</div>

<!-- ===================== EMENTA ===================== -->
<div class="cartao mb-2" id="dadosEmenta">
    <div class="entre mb-2">
        <div>
            <h2 class="mb-0" style="font-size:1.05rem">Itens da ementa</h2>
            <p class="pequeno texto-3 mb-0" style="margin:2px 0 0">
                O interruptor de disponibilidade grava imediatamente na base de dados.
            </p>
        </div>
    </div>

    <div class="tabela-envolvente" style="border:0">
        <table class="tabela" id="tabelaEmenta">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Categoria</th>
                    <th class="num">Preço</th>
                    <th>Disponibilidade</th>
                    <th class="num">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$itens): ?>
                <tr><td colspan="5" class="texto-centro texto-3" style="padding:30px">Nenhum item na ementa.</td></tr>
            <?php else: ?>
                <?php foreach ($itens as $it): ?>
                    <tr data-item="<?= (int) $it['id'] ?>">
                        <td>
                            <div class="entre" style="align-items:flex-start;gap:12px">
                                <img src="<?= e(imagem_prato($it['imagem_url'])) ?>"
                                     alt="" loading="lazy" width="54" height="54"
                                     style="width:54px;height:54px;object-fit:cover;border-radius:6px;border:1px solid var(--borda);flex:none">
                                <div style="min-width:0">
                                    <div class="negrito"><?= e($it['nome']) ?></div>
                                    <div class="pequeno texto-3"><?= e($it['descricao'] ?? '') ?></div>
                                    <div class="prato-tags mt-1">
                                        <?php if ((int) $it['destaque'] === 1): ?>
                                            <span class="tag tag-chef"><?= icone('estrela', '', 12) ?> Escolha do Chef</span>
                                        <?php endif; ?>
                                        <?php foreach (etiquetas_ementa($it['etiquetas']) as $et): ?>
                                            <span class="tag <?= classe_etiqueta($et) ?>"><?= e(rotulo_etiqueta($et)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><span class="etiqueta badge-neutro"><?= icone_categoria((string) $it['icone_svg']) ?> <?= e($it['categoria']) ?></span></td>
                        <td class="num negrito"><?= dinheiro((float) $it['preco']) ?></td>
                        <td>
                            <button type="button"
                                    class="stock-toggle <?= (int) $it['disponivel'] === 1 ? 'ativo' : '' ?>"
                                    data-stock="<?= (int) $it['id'] ?>"
                                    data-estado="<?= (int) $it['disponivel'] ?>"
                                    aria-pressed="<?= (int) $it['disponivel'] === 1 ? 'true' : 'false' ?>"
                                    title="Alternar disponibilidade">
                                <span class="stock-interruptor"></span>
                                <span class="stock-rotulo" data-stock-rotulo><?= (int) $it['disponivel'] === 1 ? 'Disponível' : 'Esgotado' ?></span>
                            </button>
                        </td>
                        <td class="num">
                            <div style="display:flex;gap:6px;justify-content:flex-end">
                                <button class="btn btn-fantasma btn-sm btn-editar"
                                        data-id="<?= (int) $it['id'] ?>"
                                        data-nome="<?= e($it['nome']) ?>"
                                        data-nome-en="<?= e((string) ($it['nome_en'] ?? '')) ?>"
                                        data-descricao="<?= e($it['descricao'] ?? '') ?>"
                                        data-descricao-en="<?= e((string) ($it['descricao_en'] ?? '')) ?>"
                                        data-categoria="<?= (int) $it['categoria_id'] ?>"
                                        data-preco="<?= e((string) $it['preco']) ?>"
                                        data-disponivel="<?= (int) $it['disponivel'] ?>"
                                        data-etiquetas="<?= e((string) ($it['etiquetas'] ?? '')) ?>"
                                        data-destaque="<?= (int) $it['destaque'] ?>"
                                        data-imagem="<?= e((string) ($it['imagem_url'] ?? '')) ?>"
                                        title="Editar"><?= icone('edicao', '', 14) ?></button>
                                <form method="post" style="display:inline" onsubmit="return confirm('Eliminar este item da ementa?')">
                                    <input type="hidden" name="acao" value="eliminar_item">
                                    <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                                    <button class="btn btn-perigo btn-sm" title="Eliminar"><?= icone('lixo', '', 14) ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================== AVALIAÇÕES ===================== -->
<div class="cartao" id="dadosAvaliacoes">
    <div class="entre mb-2">
        <div>
            <h2 class="mb-0" style="font-size:1.05rem">Comentários e avaliações</h2>
            <p class="pequeno texto-3 mb-0" style="margin:2px 0 0">
                Publique, oculte ou elimine comentários do site público.
            </p>
        </div>
        <div class="d-flex-wrap" style="align-items:center">
            <span class="etiqueta badge-neutro"><?= estrelas($avalMedia, 12) ?> <?= number_format($avalMedia, 1, ',', '.') ?></span>
            <span class="etiqueta estado-pendente">
                <span class="ponto"></span><span data-aval-pendentes><?= $avalPendentes ?></span> por moderar
            </span>
        </div>
    </div>

    <div class="tabela-envolvente" style="border:0">
        <table class="tabela tabela-compacta" style="min-width:700px">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Nota</th>
                    <th>Comentário</th>
                    <th>Data</th>
                    <th>Estado</th>
                    <th class="num">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$avaliacoes): ?>
                <tr><td colspan="6" class="texto-centro texto-3" style="padding:24px">Ainda não existem avaliações.</td></tr>
            <?php else: ?>
                <?php foreach ($avaliacoes as $a): ?>
                    <tr data-aval-id="<?= (int) $a['id'] ?>" data-aval-visivel-estado="<?= (int) $a['visivel'] ?>">
                        <td>
                            <div class="negrito" style="font-size:.82rem"><?= e($a['nome_cliente']) ?></div>
                            <div class="texto-3" style="font-size:.7rem">#<?= (int) $a['id'] ?></div>
                        </td>
                        <td><?= estrelas((float) $a['classificacao'], 12) ?></td>
                        <td style="max-width:340px">
                            <div class="txt-corte-2" style="font-size:.8rem"><?= e($a['comentario']) ?></div>
                        </td>
                        <td class="texto-3" style="font-size:.74rem;white-space:nowrap">
                            <?= date('d/m/Y H:i', strtotime((string) $a['criado_em'])) ?>
                        </td>
                        <td>
                            <span class="etiqueta <?= (int) $a['visivel'] === 1 ? 'estado-pronto' : 'estado-pendente' ?>"
                                  data-aval-estado>
                                <span class="ponto"></span><?= (int) $a['visivel'] === 1 ? 'Publicada' : 'Em moderação' ?>
                            </span>
                        </td>
                        <td class="num">
                            <div style="display:flex;gap:6px;justify-content:flex-end">
                                <button type="button" class="btn btn-fantasma btn-sm"
                                        data-aval-visivel="<?= (int) $a['id'] ?>"
                                        data-estado="<?= (int) $a['visivel'] ?>"
                                        title="<?= (int) $a['visivel'] === 1 ? 'Ocultar do site' : 'Publicar no site' ?>">
                                    <span data-aval-acao><?= (int) $a['visivel'] === 1 ? 'Ocultar' : 'Publicar' ?></span>
                                </button>
                                <button type="button" class="btn btn-perigo btn-sm"
                                        data-aval-eliminar="<?= (int) $a['id'] ?>"
                                        title="Eliminar avaliação">
                                    <?= icone('lixo', '', 14) ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================== MODAL ITEM ===================== -->
<div class="modal" id="modalItem" role="dialog" aria-modal="true">
    <div class="modal-caixa modal-largo">
        <button class="modal-fechar" data-modal-fechar aria-label="Fechar"><?= icone('x') ?></button>
        <h3 id="tituloModalItem">Novo item</h3>
        <p class="sub-modal">Adicione ou edite um prato da ementa.</p>

        <form method="post" id="formItem" data-validar novalidate>
            <input type="hidden" name="acao" value="guardar_item">
            <input type="hidden" name="id" id="itemId" value="0">

            <div class="linha-campos">
                <label class="campo">
                    <span>Nome (PT) *</span>
                    <input type="text" name="nome" id="itemNome" required placeholder="Ex.: Bacalhau à Lagareiro">
                </label>
                <label class="campo">
                    <span>Nome (EN)</span>
                    <input type="text" name="nome_en" id="itemNomeEn" placeholder="Ex.: Lagareiro Salt Cod">
                </label>
            </div>

            <div class="linha-campos">
                <label class="campo">
                    <span>Descrição (PT)</span>
                    <textarea name="descricao" id="itemDescricao" placeholder="Ingredientes e detalhes do prato"></textarea>
                </label>
                <label class="campo">
                    <span>Descrição (EN)</span>
                    <textarea name="descricao_en" id="itemDescricaoEn" placeholder="Ingredients and dish details"></textarea>
                </label>
            </div>

            <div class="linha-campos">
                <label class="campo">
                    <span>Categoria *</span>
                    <select name="categoria_id" id="itemCategoria" required>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="campo">
                    <span>Preço (€) *</span>
                    <input type="number" name="preco" id="itemPreco" step="0.01" min="0.01" required placeholder="0.00">
                </label>
            </div>

            <label class="campo">
                <span>Fotografia do prato (URL)</span>
                <div class="entre" style="align-items:stretch">
                    <input type="url" name="imagem_url" id="itemImagemUrl"
                           placeholder="https://images.unsplash.com/photo-…" style="flex:1">
                    <img id="itemImagemPreview" alt="" width="48" height="48"
                         src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=200&q=70&auto=format&fit=crop"
                         style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--borda);flex:none">
                </div>
                <small class="texto-3">Ligação directa para a imagem (JPG/PNG/WebP). Vazio = imagem genérica.</small>
            </label>

            <label class="campo">
                <span>Etiquetas (separadas por vírgula)</span>
                <input type="text" name="etiquetas" id="itemEtiquetas" placeholder="vegetariano, novidade, chef">
                <small class="texto-3">Opções: vegetariano, novidade, chef, picante, sem-gluten</small>
            </label>

            <label class="checkbox mb-2">
                <input type="checkbox" name="disponivel" id="itemDisponivel" checked>
                Item disponível para pedido
            </label>

            <label class="checkbox mb-2">
                <input type="checkbox" name="destaque" id="itemDestaque">
                Marcar como escolha do chef
            </label>

            <div class="d-flex-wrap mt-2">
                <button type="submit" class="btn btn-primario"><?= icone('ok') ?> Guardar item</button>
                <button type="button" class="btn btn-fantasma" data-modal-fechar>Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== MODAL CATEGORIAS ===================== -->
<div class="modal" id="modalCategoria" role="dialog" aria-modal="true">
    <div class="modal-caixa modal-largo">
        <button class="modal-fechar" data-modal-fechar aria-label="Fechar"><?= icone('x') ?></button>
        <h3>Categorias</h3>
        <p class="sub-modal">Organize a ementa por categorias.</p>

        <form method="post" class="mb-3" data-validar novalidate>
            <input type="hidden" name="acao" value="guardar_categoria">
            <input type="hidden" name="id" value="0">
            <div class="linha-campos">
                <label class="campo">
                    <span>Nova categoria *</span>
                    <input type="text" name="nome" required placeholder="Ex.: Entradas">
                </label>
                <label class="campo">
                    <span>Ícone</span>
                    <select name="icone_svg">
                        <?php foreach ($catIcons as $ic): ?>
                            <option value="<?= $ic ?>"><?= ucfirst($ic) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <button type="submit" class="btn btn-primario"><?= icone('mais') ?> Adicionar categoria</button>
        </form>

        <div class="tabela-envolvente">
            <table class="tabela" style="min-width:420px">
                <thead><tr><th>Categoria</th><th>Ícone</th><th class="num">Ações</th></tr></thead>
                <tbody>
                <?php foreach ($categorias as $c): ?>
                    <tr>
                        <td class="negrito"><?= e($c['nome']) ?></td>
                        <td><?= icone_categoria((string) $c['icone_svg']) ?></td>
                        <td class="num">
                            <form method="post" style="display:inline" onsubmit="return confirm('Eliminar esta categoria e os seus itens?')">
                                <input type="hidden" name="acao" value="eliminar_categoria">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button class="btn btn-perigo btn-sm"><?= icone('lixo', '', 14) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
/* Preenchimento do modal de edição (comportamento específico desta página) */
(function () {
    var btnNovo = document.getElementById('btnNovoItem');
    var f = {
        id: document.getElementById('itemId'),
        nome: document.getElementById('itemNome'),
        nomeEn: document.getElementById('itemNomeEn'),
        desc: document.getElementById('itemDescricao'),
        descEn: document.getElementById('itemDescricaoEn'),
        cat: document.getElementById('itemCategoria'),
        preco: document.getElementById('itemPreco'),
        disp: document.getElementById('itemDisponivel'),
        etiquetas: document.getElementById('itemEtiquetas'),
        destaque: document.getElementById('itemDestaque'),
        imagem: document.getElementById('itemImagemUrl'),
        preview: document.getElementById('itemImagemPreview')
    };
    var titulo = document.getElementById('tituloModalItem');
    var fallback = 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=200&q=70&auto=format&fit=crop';

    if (!f.id) return;

    function actualizarPreview() {
        var url = f.imagem.value.trim();
        f.preview.src = url !== '' ? url : fallback;
    }

    f.imagem.addEventListener('input', actualizarPreview);
    f.imagem.addEventListener('change', actualizarPreview);

    function limpar() {
        f.id.value = '0';
        f.nome.value = '';
        f.nomeEn.value = '';
        f.desc.value = '';
        f.descEn.value = '';
        f.preco.value = '';
        f.disp.checked = true;
        f.etiquetas.value = '';
        f.destaque.checked = false;
        f.imagem.value = '';
        actualizarPreview();
        titulo.textContent = 'Novo item';
    }

    if (btnNovo) {
        btnNovo.addEventListener('click', limpar);
    }

    document.querySelectorAll('.btn-editar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            f.id.value = btn.getAttribute('data-id');
            f.nome.value = btn.getAttribute('data-nome');
            f.nomeEn.value = btn.getAttribute('data-nome-en') || '';
            f.desc.value = btn.getAttribute('data-descricao');
            f.descEn.value = btn.getAttribute('data-descricao-en') || '';
            f.cat.value = btn.getAttribute('data-categoria');
            f.preco.value = btn.getAttribute('data-preco');
            f.disp.checked = btn.getAttribute('data-disponivel') === '1';
            f.etiquetas.value = btn.getAttribute('data-etiquetas') || '';
            f.destaque.checked = btn.getAttribute('data-destaque') === '1';
            f.imagem.value = btn.getAttribute('data-imagem') || '';
            actualizarPreview();
            titulo.textContent = 'Editar item';
            window.saborproAbrirModal('modalItem');
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/rodape.php'; ?>
