<?php
/** SaborPro :: includes/rodape.php — Rodapé comum. */
?>
        </div><!-- /.container -->
    </main>

    <footer class="rodape">
        <div class="container rodape-grelha">
            <div>
                <div style="margin-bottom:6px">
                    <?= logo_saborpro('claro', 'sistema inteligente de pedidos', 'dashboard.php') ?>
                </div>
            </div>
            <p>© <?= date('Y') ?> SaborPro · PHP + MySQL · Projeto Laragon</p>
        </div>
    </footer>
</div><!-- /.pagina -->

<!-- Notificações rápidas (comum a todas as páginas de gestão) -->
<div class="toast" id="toastPedido">
    <span class="icone-toast"><?= icone('ok') ?></span>
    <span>
        <strong>Operação concluída</strong>
        <span id="toastTexto"></span>
    </span>
</div>

<script src="<?= base_url() ?>/js/main.js"></script>
</body>
</html>
