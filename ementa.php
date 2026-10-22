<?php
/**
 * SaborPro :: ementa.php
 * Gestão da ementa: CRUD de itens e categorias, disponibilidade e preços.
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

    // ---- Itens da ementa ----
    if ($acao === 'guardar_item') {
        $id        = (int) ($_POST['id'] ?? 0);
        $catId     = (int) ($_POST['categoria_id'] ?? 0);
        $nome      = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $preco     = (float) ($_POST['preco'] ?? 0);
        $disp      = isset($_POST['disponivel']) ? 1 : 0;

        if ($nome === '' || $catId <= 0 || $preco <= 0) {
            $erro = 'Preencha nome, categoria e um preço válido.';
        } else {
            if ($id > 0) {
                $stmt = $bd->prepare('UPDATE ementa SET nome=?, descricao=?, categoria_id=?, preco=?, disponivel=? WHERE id=?');
                $stmt->bind_param('ssdsii', $nome, $descricao, $catId, $preco, $disp, $id);
            } else {
                $stmt = $bd->prepare('INSERT INTO ementa (nome, descricao, categoria_id, preco, disponivel) VALUES (?,?,?,?,?)');
                $stmt->bind_param('ssdsi', $nome, $descricao, $catId, $preco, $disp);
            }
            $stmt->execute();
            $stmt->close();
            $aviso = $id > 0 ? 'Item atualizado com sucesso.' : 'Item adicionado à ementa.';
        }
    }

    if ($acao === 'eliminar_item') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $bd->prepare('DELETE FROM ementa WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $aviso = 'Item removido da ementa.';
    }

    if ($acao === 'alternar') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $bd->prepare('UPDATE ementa SET disponivel = 1 - disponivel WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $aviso = 'Disponibilidade atualizada.';
    }

    // ---- Categorias ----
    if ($acao === 'guardar_categoria') {
        $id   = (int) ($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $icone = trim($_POST['icone_svg'] ?? 'prato');

        if ($nome === '') {
            $erro = 'Indique o nome da categoria.';
        } else {
            if ($id > 0) {
                $stmt = $bd->prepare('UPDATE categorias SET nome=?, icone_svg=? WHERE id=?');
                $stmt->bind_param('ssi', $nome, $icone, $id);
            } else {
                $stmt = $bd->prepare('INSERT INTO categorias (nome, icone_svg) VALUES (?,?)');
                $stmt->bind_param('ss', $nome, $icone);
            }
            $stmt->execute();
            $stmt->close();
            $aviso = $id > 0 ? 'Categoria atualizada.' : 'Categoria criada.';
        }
    }

    if ($acao === 'eliminar_categoria') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $bd->prepare('DELETE FROM categorias WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $aviso = 'Categoria removida (itens associados também foram eliminados).';
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
$r = $bd->query("SELECT e.id, e.nome, e.descricao, e.preco, e.disponivel, e.categoria_id, c.nome AS categoria, c.icone_svg
                   FROM ementa e JOIN categorias c ON c.id = e.categoria_id
                  ORDER BY c.nome, e.nome");
while ($i = $r->fetch_assoc()) {
    $itens[] = $i;
}
$r->free();

$catIcons = ['entrada', 'prato', 'doce', 'bebida', 'cafe'];

$paginaTitulo = 'Gestão da Ementa';
$paginaAtiva  = 'ementa';

require_once __DIR__ . '/includes/cabecalho.php';
?>

<div class="cabecalho-pagina entre-topo">
    <div>
        <h1 class="mb-0">Ementa</h1>
        <p class="subtitulo">Pratos, bebidas, preços e disponibilidade.</p>
    </div>
    <div class="d-flex-wrap">
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

<!-- ===================== CATEGORIAS (chips) ===================== -->
<div class="d-flex-wrap mb-3">
    <?php foreach ($categorias as $c): ?>
        <span class="etiqueta badge-neutro" style="padding:8px 14px">
            <?= icone_categoria((string) $c['icone_svg']) ?> <?= e($c['nome']) ?>
            <span class="texto-3">· <?= count(array_filter($itens, fn($i) => $i['categoria_id'] == $c['id'])) ?></span>
        </span>
    <?php endforeach; ?>
</div>

<!-- ===================== LISTA DE ITENS ===================== -->
<div class="tabela-envolvente">
    <table class="tabela" id="tabelaEmenta">
        <thead>
            <tr>
                <th>Item</th>
                <th>Categoria</th>
                <th class="num">Preço</th>
                <th>Estado</th>
                <th class="num">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$itens): ?>
            <tr><td colspan="5" class="texto-centro texto-3" style="padding:30px">Nenhum item na ementa.</td></tr>
        <?php else: ?>
            <?php foreach ($itens as $i => $it): ?>
                <tr>
                    <td>
                        <div class="negrito"><?= e($it['nome']) ?></div>
                        <div class="pequeno texto-3"><?= e($it['descricao'] ?? '') ?></div>
                    </td>
                    <td><span class="etiqueta badge-neutro"><?= icone_categoria((string) $it['icone_svg']) ?> <?= e($it['categoria']) ?></span></td>
                    <td class="num negrito"><?= dinheiro((float) $it['preco']) ?></td>
                    <td>
                        <?php if ((int) $it['disponivel'] === 1): ?>
                            <span class="etiqueta estado-pronto"><span class="ponto"></span>Disponível</span>
                        <?php else: ?>
                            <span class="etiqueta estado-entregue"><span class="ponto"></span>Indisponível</span>
                        <?php endif; ?>
                    </td>
                    <td class="num">
                        <div style="display:flex;gap:6px;justify-content:flex-end">
                            <form method="post" style="display:inline">
                                <input type="hidden" name="acao" value="alternar">
                                <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                                <button class="btn btn-fantasma btn-sm" title="Alternar disponibilidade">
                                    <?= icone((int) $it['disponivel'] === 1 ? 'x' : 'ok', '', 14) ?>
                                </button>
                            </form>
                            <button class="btn btn-fantasma btn-sm btn-editar"
                                    data-id="<?= (int) $it['id'] ?>"
                                    data-nome="<?= e($it['nome']) ?>"
                                    data-descricao="<?= e($it['descricao'] ?? '') ?>"
                                    data-categoria="<?= (int) $it['categoria_id'] ?>"
                                    data-preco="<?= e((string) $it['preco']) ?>"
                                    data-disponivel="<?= (int) $it['disponivel'] ?>"
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

<!-- ===================== MODAL ITEM ===================== -->
<div class="modal" id="modalItem" role="dialog" aria-modal="true">
    <div class="modal-caixa modal-largo">
        <button class="modal-fechar" data-modal-fechar aria-label="Fechar"><?= icone('x') ?></button>
        <h3 id="tituloModalItem">Novo item</h3>
        <p class="sub-modal">Adicione ou edite um prato da ementa.</p>

        <form method="post" id="formItem" data-validar novalidate>
            <input type="hidden" name="acao" value="guardar_item">
            <input type="hidden" name="id" id="itemId" value="0">

            <label class="campo">
                <span>Nome do item *</span>
                <input type="text" name="nome" id="itemNome" required placeholder="Ex.: Bacalhau à Lagareiro">
            </label>

            <label class="campo">
                <span>Descrição</span>
                <textarea name="descricao" id="itemDescricao" placeholder="Ingredientes e detalhes do prato"></textarea>
            </label>

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

            <label class="checkbox mb-2">
                <input type="checkbox" name="disponivel" id="itemDisponivel" checked>
                Item disponível para pedido
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
    var modal = document.getElementById('modalItem');
    var btnNovo = document.getElementById('btnNovoItem');
    var f = {
        id: document.getElementById('itemId'),
        nome: document.getElementById('itemNome'),
        desc: document.getElementById('itemDescricao'),
        cat: document.getElementById('itemCategoria'),
        preco: document.getElementById('itemPreco'),
        disp: document.getElementById('itemDisponivel')
    };
    var titulo = document.getElementById('tituloModalItem');

    function limpar() {
        f.id.value = '0';
        f.nome.value = '';
        f.desc.value = '';
        f.preco.value = '';
        f.disp.checked = true;
        titulo.textContent = 'Novo item';
    }

    if (btnNovo) {
        btnNovo.addEventListener('click', function () { limpar(); });
    }

    document.querySelectorAll('.btn-editar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            f.id.value = btn.getAttribute('data-id');
            f.nome.value = btn.getAttribute('data-nome');
            f.desc.value = btn.getAttribute('data-descricao');
            f.cat.value = btn.getAttribute('data-categoria');
            f.preco.value = btn.getAttribute('data-preco');
            f.disp.checked = btn.getAttribute('data-disponivel') === '1';
            titulo.textContent = 'Editar item';
            window.saborproAbrirModal('modalItem');
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/rodape.php'; ?>
