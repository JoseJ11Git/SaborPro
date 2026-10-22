/* =============================================================
   SaborPro :: js/main.js
   JavaScript puro (Vanilla) — sem bibliotecas externas.
   ============================================================= */
(function () {
    'use strict';

    /* ---------------------- UTILITÁRIOS ---------------------- */

    const $  = (sel, ctx) => (ctx || document).querySelector(sel);
    const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));

    /** Formata um número no formato monetário português: 12,50 € */
    function money(v) {
        return (Number(v) || 0).toFixed(2).replace('.', ',') + ' €';
    }
    window.saborproMoney = money;

    /** Ícone SVG inline (usado nos botões dinâmicos do carrinho). */
    function svg(paths, w) {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            + 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" width="' + (w || 16) + '" height="'
            + (w || 16) + '">' + paths + '</svg>';
    }
    const SVG_MAIS   = '<path d="M12 5v14M5 12h14"/>';
    const SVG_MENOS  = '<path d="M5 12h14"/>';
    const SVG_LIXO   = '<path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13"/>';

    /* ---------------------- MODAIS -------------------------- */

    function abrirModal(id) {
        const m = typeof id === 'string' ? $('#' + id) : id;
        if (!m) return;
        m.classList.add('aberto');
        document.body.style.overflow = 'hidden';
        const foco = m.querySelector('input, select, textarea, button');
        if (foco) setTimeout(() => foco.focus(), 120);
    }

    function fecharModal(m) {
        const alvo = typeof m === 'string' ? $('#' + m) : m;
        if (!alvo) return;
        alvo.classList.remove('aberto');
        document.body.style.overflow = '';
    }
    window.saborproAbrirModal = abrirModal;
    window.saborproFecharModal = fecharModal;

    function initModais() {
        $$('[data-modal-abrir]').forEach(function (b) {
            b.addEventListener('click', function (ev) {
                ev.preventDefault();
                abrirModal(b.getAttribute('data-modal-abrir'));
            });
        });

        $$('[data-modal-fechar]').forEach(function (b) {
            b.addEventListener('click', function (ev) {
                ev.preventDefault();
                fecharModal(b.closest('.modal'));
            });
        });

        // Clique no fundo (fora da caixa) fecha o modal
        $$('.modal').forEach(function (m) {
            m.addEventListener('mousedown', function (ev) {
                if (ev.target === m) fecharModal(m);
            });
        });

        // Tecla ESC fecha o modal aberto
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                const aberto = $('.modal.aberto');
                if (aberto) fecharModal(aberto);
            }
        });
    }

    /* ---------------------- ALERTAS ------------------------- */

    function initAlertas() {
        $$('.alerta .alerta-fechar').forEach(function (b) {
            b.addEventListener('click', function () {
                const a = b.closest('.alerta');
                a.style.transition = 'opacity .25s ease, transform .25s ease';
                a.style.opacity = '0';
                a.style.transform = 'translateY(-8px)';
                setTimeout(function () { a.remove(); }, 250);
            });
        });

        // Remove alertas de sucesso ao fim de 6s
        $$('.alerta[data-autoclose]').forEach(function (a) {
            setTimeout(function () {
                a.style.transition = 'opacity .3s ease';
                a.style.opacity = '0';
                setTimeout(function () { a.remove(); }, 300);
            }, 6000);
        });
    }

    /* ---------------------- VALIDAÇÃO ------------------------ */

    function mostrarErro(campo, msg) {
        campo.classList.add('invalido');
        let p = campo.parentNode.querySelector('.erro-campo');
        if (!p) {
            p = document.createElement('div');
            p.className = 'erro-campo';
            campo.parentNode.appendChild(p);
        }
        p.textContent = msg;
        p.classList.add('visivel');
    }

    function limparErro(campo) {
        campo.classList.remove('invalido');
        const p = campo.parentNode.querySelector('.erro-campo');
        if (p) p.classList.remove('visivel');
    }

    function validarCampo(campo) {
        const valor = (campo.value || '').trim();
        if (campo.hasAttribute('required') && valor === '') {
            mostrarErro(campo, 'Este campo é obrigatório.');
            return false;
        }
        if (campo.type === 'email' && valor !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(valor)) {
            mostrarErro(campo, 'Introduza um e-mail válido.');
            return false;
        }
        if (campo.type === 'number' && valor !== '') {
            const min = campo.getAttribute('min');
            const max = campo.getAttribute('max');
            const n = Number(valor);
            if (min !== null && n < Number(min)) { mostrarErro(campo, 'Valor mínimo: ' + min + '.'); return false; }
            if (max !== null && n > Number(max)) { mostrarErro(campo, 'Valor máximo: ' + max + '.'); return false; }
        }
        if (campo.type === 'password' && campo.dataset.min !== undefined
            && valor.length < Number(campo.dataset.min)) {
            mostrarErro(campo, 'A palavra-passe deve ter pelo menos ' + campo.dataset.min + ' caracteres.');
            return false;
        }
        limparErro(campo);
        return true;
    }

    function initValidacao() {
        $$('form[data-validar]').forEach(function (form) {
            form.setAttribute('novalidate', 'novalidate');

            $$('input, select, textarea', form).forEach(function (campo) {
                campo.addEventListener('blur', function () { validarCampo(campo); });
                campo.addEventListener('input', function () {
                    if (campo.classList.contains('invalido')) validarCampo(campo);
                });
            });

            form.addEventListener('submit', function (ev) {
                let ok = true;
                $$('input, select, textarea', form).forEach(function (campo) {
                    if (!validarCampo(campo)) ok = false;
                });
                if (!ok) {
                    ev.preventDefault();
                    const primeiro = form.querySelector('.invalido');
                    if (primeiro) primeiro.focus();
                }
            });
        });
    }

    /* ---------------------- MENU MOBILE ---------------------- */

    function initMenu() {
        const btn = $('#btnMenu');
        const links = $('#navLinks');
        if (!btn || !links) return;
        btn.addEventListener('click', function () {
            links.classList.toggle('aberto');
        });
    }

    /* ---------------------- FILTRO DE ARTIGOS ---------------- */

    function initFiltro() {
        const botoes = $$('[data-filtro-categoria]');
        if (!botoes.length) return;

        botoes.forEach(function (btn) {
            btn.addEventListener('click', function () {
                botoes.forEach(function (b) { b.classList.remove('ativo'); });
                btn.classList.add('ativo');
                const cat = btn.getAttribute('data-filtro-categoria');
                $$('[data-artigo-categoria]').forEach(function (art) {
                    const visivel = cat === 'todos' || art.getAttribute('data-artigo-categoria') === cat;
                    art.style.display = visivel ? '' : 'none';
                });
                // Reinicia a animação de entrada
                $$('[data-artigo-categoria]').forEach(function (art) {
                    art.style.animation = 'none';
                    void art.offsetWidth;
                    art.style.animation = '';
                });
            });
        });
    }

    /* ---------------------- CARRINHO (POS) -------------------- */

    function initCarrinho() {
        const lista = $('#carrinhoLista');
        if (!lista) return;

        const totalEl   = $('#carrinhoTotal');
        const qtdEl     = $('#carrinhoQtd');
        const subEl     = $('#carrinhoSubtotal');
        const btnFinal  = $('#btnFinalizar');
        const selectMesa = $('#selectMesa');
        const aviso     = $('#carrinhoAviso');

        let itens = [];   // {id, nome, preco, qtd}

        function guardar() {
            try { sessionStorage.setItem('saborpro_carrinho', JSON.stringify(itens)); }
            catch (e) { /* modo privado */ }
        }
        function recuperar() {
            try {
                const s = sessionStorage.getItem('saborpro_carrinho');
                if (s) itens = JSON.parse(s) || [];
            } catch (e) { itens = []; }
        }

        function totalItens() {
            return itens.reduce(function (a, i) { return a + i.qtd; }, 0);
        }
        function totalValor() {
            return itens.reduce(function (a, i) { return a + (i.qtd * i.preco); }, 0);
        }

        function desenhar() {
            lista.innerHTML = '';

            if (!itens.length) {
                const vazio = document.createElement('div');
                vazio.className = 'vazio';
                vazio.innerHTML = svg('<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>', 34)
                    + 'O carrinho está vazio.<br>Selecione artigos no menu.';
                lista.appendChild(vazio);
            } else {
                itens.forEach(function (item, idx) {
                    const li = document.createElement('li');
                    li.setAttribute('data-idx', idx);
                    li.innerHTML =
                        '<div class="qtd">' +
                            '<button type="button" data-acao="menos" title="Diminuir">' + svg(SVG_MENOS, 14) + '</button>' +
                            '<span>' + item.qtd + '</span>' +
                            '<button type="button" data-acao="mais" title="Aumentar">' + svg(SVG_MAIS, 14) + '</button>' +
                        '</div>' +
                        '<div class="info"><div class="nome"></div><div class="sub"></div></div>' +
                        '<div class="preco">' + money(item.qtd * item.preco) + '</div>' +
                        '<button type="button" class="btn btn-perigo btn-sm" data-acao="remover" title="Remover">' + svg(SVG_LIXO, 14) + '</button>';
                    li.querySelector('.nome').textContent = item.nome;
                    li.querySelector('.sub').textContent = money(item.preco) + ' / un.';
                    lista.appendChild(li);
                });
            }

            const qtd = totalItens();
            const val = totalValor();
            if (totalEl) totalEl.textContent = money(val);
            if (subEl) subEl.textContent = money(val);
            if (qtdEl) qtdEl.textContent = qtd + (qtd === 1 ? ' artigo' : ' artigos');
            if (btnFinal) btnFinal.disabled = !itens.length;
            if (aviso) aviso.textContent = '';
            guardar();
        }

        function adicionar(id, nome, preco) {
            const existente = itens.find(function (i) { return i.id === id; });
            if (existente) {
                existente.qtd += 1;
            } else {
                itens.push({ id: id, nome: nome, preco: Number(preco), qtd: 1 });
            }
            desenhar();
            const ultimo = lista.querySelector('li:last-child');
            if (ultimo) ultimo.classList.add('sai') && setTimeout(function () { ultimo.classList.remove('sai'); }, 20);
        }

        function alterar(idx, delta) {
            if (!itens[idx]) return;
            itens[idx].qtd += delta;
            if (itens[idx].qtd <= 0) {
                const li = lista.querySelector('li[data-idx="' + idx + '"]');
                if (li) li.classList.add('sai');
                setTimeout(function () {
                    itens.splice(idx, 1);
                    desenhar();
                }, 260);
                return;
            }
            desenhar();
        }

        function remover(idx) {
            const li = lista.querySelector('li[data-idx="' + idx + '"]');
            if (li) li.classList.add('sai');
            setTimeout(function () {
                itens.splice(idx, 1);
                desenhar();
            }, 260);
        }

        // Clique nos artigos do menu
        $$('[data-artigo]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.classList.contains('indisponivel')) return;
                adicionar(btn.getAttribute('data-artigo'),
                          btn.getAttribute('data-nome'),
                          btn.getAttribute('data-preco'));
            });
        });

        // Ações dentro do carrinho (delegação de eventos)
        lista.addEventListener('click', function (ev) {
            const b = ev.target.closest('[data-acao]');
            if (!b) return;
            const li = b.closest('li[data-idx]');
            if (!li) return;
            const idx = Number(li.getAttribute('data-idx'));
            const acao = b.getAttribute('data-acao');
            if (acao === 'mais') alterar(idx, 1);
            else if (acao === 'menos') alterar(idx, -1);
            else if (acao === 'remover') remover(idx);
        });

        // Finalizar pedido (POST assíncrono)
        if (btnFinal) {
            btnFinal.addEventListener('click', function () {
                if (!itens.length) return;
                if (selectMesa && !selectMesa.value) {
                    aviso.textContent = 'Escolha a mesa do pedido.';
                    aviso.className = 'erro-campo visivel';
                    return;
                }
                btnFinal.disabled = true;
                btnFinal.textContent = 'A registar...';

                const corpo = {
                    mesa_id: selectMesa ? selectMesa.value : '',
                    itens: itens.map(function (i) { return { id: i.id, quantidade: i.qtd }; })
                };

                fetch(basePath() + '/api/pedido.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(corpo)
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.ok) {
                        itens = [];
                        desenhar();
                        window.location.href = basePath() + '/cozinha.php?novo=' + res.pedido_id;
                    } else {
                        aviso.textContent = (res && res.erro) ? res.erro : 'Não foi possível registar o pedido.';
                        aviso.className = 'erro-campo visivel';
                        btnFinal.disabled = false;
                        btnFinal.textContent = 'Finalizar pedido';
                    }
                })
                .catch(function () {
                    aviso.textContent = 'Erro de comunicação com o servidor.';
                    aviso.className = 'erro-campo visivel';
                    btnFinal.disabled = false;
                    btnFinal.textContent = 'Finalizar pedido';
                });
            });
        }

        // Limpar carrinho
        const btnLimpar = $('#btnLimpar');
        if (btnLimpar) {
            btnLimpar.addEventListener('click', function () {
                itens = [];
                desenhar();
            });
        }

        recuperar();
        desenhar();
    }

    /* ---------------------- ATUALIZAÇÃO KDS -------------------- */

    function initAutoRefresh() {
        const alvo = document.body.getAttribute('data-auto-refresh');
        if (!alvo) return;
        setTimeout(function () { window.location.reload(); }, Number(alvo) * 1000);
    }

    /* ---------------------- BASE PATH -------------------------- */

    function basePath() {
        const dir = window.location.pathname.replace(/\\/g, '/').replace(/\/[^/]*$/, '');
        return dir === '/' ? '' : dir;
    }
    window.saborproBase = basePath;

    /* ---------------------- ARRANQUE --------------------------- */

    document.addEventListener('DOMContentLoaded', function () {
        initModais();
        initAlertas();
        initValidacao();
        initMenu();
        initFiltro();
        initCarrinho();
        initAutoRefresh();
    });
})();
