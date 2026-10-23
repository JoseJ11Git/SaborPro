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

    /* ---------------------- CARRINHO PÚBLICO (site do cliente) -------- */

    function initCarrinhoPublico() {
        const lista  = document.getElementById('listaPublica');
        if (!lista) return;

        const btnFlutuante = document.getElementById('btnCarrinho');
        const contador     = document.getElementById('contadorCarrinho');
        const totalEl      = document.getElementById('publicoTotal');
        const subEl        = document.getElementById('publicoSubtotal');
        const gaveta       = document.getElementById('gavetaCarrinho');
        const fundo        = document.getElementById('fundoCarrinho');
        const fecharBtn    = document.getElementById('fecharCarrinho');
        const enviarBtn    = document.getElementById('btnEnviarPublico');
        const erroEl       = document.getElementById('publicoErro');
        const blocoMesa    = document.getElementById('blocoMesa');

        let itens = [];

        function totalItens() {
            return itens.reduce(function (a, i) { return a + i.qtd; }, 0);
        }
        function totalValor() {
            return itens.reduce(function (a, i) { return a + (i.qtd * i.preco); }, 0);
        }

        function desenhar() {
            lista.innerHTML = '';

            if (!itens.length) {
                lista.innerHTML = '<div class="vazio">'
                    + svg('<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>', 34)
                    + 'Ainda não adicionou nenhum prato.<br>Escolha na ementa acima.</div>';
            } else {
                itens.forEach(function (item, idx) {
                    const li = document.createElement('li');
                    li.className = 'linha-carrinho';
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
            if (contador) contador.textContent = qtd;
            if (btnFlutuante) btnFlutuante.classList.toggle('vazio', qtd === 0);
            if (erroEl) { erroEl.textContent = ''; erroEl.className = 'erro-campo'; }
        }

        function abrir() {
            if (gaveta) gaveta.classList.add('aberto');
            if (fundo) fundo.classList.add('aberto');
            document.body.style.overflow = 'hidden';
        }
        function fechar() {
            if (gaveta) gaveta.classList.remove('aberto');
            if (fundo) fundo.classList.remove('aberto');
            document.body.style.overflow = '';
        }

        if (btnFlutuante) btnFlutuante.addEventListener('click', abrir);
        if (fecharBtn) fecharBtn.addEventListener('click', fechar);
        if (fundo) fundo.addEventListener('click', fechar);

        // Adicionar itens a partir dos cartões da ementa
        $$('[data-add]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = parseInt(btn.getAttribute('data-add'), 10);
                const nome = btn.getAttribute('data-nome');
                const preco = parseFloat(btn.getAttribute('data-preco'));
                const existente = itens.find(function (i) { return i.id === id; });
                if (existente) {
                    existente.qtd += 1;
                } else {
                    itens.push({ id: id, nome: nome, preco: preco, qtd: 1 });
                }
                desenhar();
                // micro-interação no botão
                btn.animate(
                    [{ transform: 'scale(1)' }, { transform: 'scale(1.3)' }, { transform: 'scale(1)' }],
                    { duration: 320, easing: 'cubic-bezier(.34,1.56,.64,1)' }
                );
                if (btnFlutuante) {
                    btnFlutuante.animate(
                        [{ transform: 'scale(1)' }, { transform: 'scale(1.12)' }, { transform: 'scale(1)' }],
                        { duration: 320, easing: 'cubic-bezier(.34,1.56,.64,1)' }
                    );
                }
            });
        });

        // Ações dentro do carrinho
        lista.addEventListener('click', function (ev) {
            const b = ev.target.closest('[data-acao]');
            if (!b) return;
            const li = b.closest('li[data-idx]');
            if (!li) return;
            const idx = Number(li.getAttribute('data-idx'));
            const acao = b.getAttribute('data-acao');

            if (acao === 'mais') { itens[idx].qtd += 1; desenhar(); }
            else if (acao === 'menos') {
                itens[idx].qtd -= 1;
                if (itens[idx].qtd <= 0) {
                    li.classList.add('sai');
                    setTimeout(function () { itens.splice(idx, 1); desenhar(); }, 260);
                } else { desenhar(); }
            } else if (acao === 'remover') {
                li.classList.add('sai');
                setTimeout(function () { itens.splice(idx, 1); desenhar(); }, 260);
            }
        });

        // Tipo de pedido (Takeaway / Na Mesa)
        $$('.opcao-pedido').forEach(function (op) {
            op.addEventListener('click', function () {
                $$('.opcao-pedido').forEach(function (o) { o.classList.remove('selecionada'); });
                op.classList.add('selecionada');
                const takeaway = op.getAttribute('data-opcao') === 'takeaway';
                if (blocoMesa) blocoMesa.classList.toggle('d-none', takeaway);
            });
        });

        // Enviar pedido
        if (enviarBtn) {
            enviarBtn.addEventListener('click', function () {
                if (!itens.length) {
                    if (erroEl) { erroEl.textContent = 'Adicione pelo menos um prato.'; erroEl.className = 'erro-campo visivel'; }
                    return;
                }
                const tipoRadio = document.querySelector('input[name="tipo_pedido"]:checked');
                const tipo = tipoRadio ? tipoRadio.value : 'Takeaway';
                const nome = (document.getElementById('clienteNome') || {}).value || '';
                const tel = (document.getElementById('clienteTel') || {}).value || '';
                const obs = (document.getElementById('clienteObs') || {}).value || '';
                const selMesa = document.getElementById('mesaPublica');

                if (nome.trim().length < 2) {
                    if (erroEl) { erroEl.textContent = 'Indique o seu nome.'; erroEl.className = 'erro-campo visivel'; }
                    return;
                }
                if (tipo === 'Na Mesa' && (!selMesa || !selMesa.value)) {
                    if (erroEl) { erroEl.textContent = 'Escolha a mesa.'; erroEl.className = 'erro-campo visivel'; }
                    return;
                }

                enviarBtn.disabled = true;
                enviarBtn.textContent = 'A enviar...';

                const corpo = {
                    tipo: tipo,
                    mesa_numero: selMesa ? selMesa.value : 0,
                    nome: nome.trim(),
                    telefone: tel.trim(),
                    observacoes: obs.trim(),
                    itens: itens.map(function (i) { return { id: i.id, quantidade: i.qtd }; })
                };

                fetch(basePath() + '/pedidos_publicos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(corpo)
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.ok) {
                        itens = [];
                        desenhar();
                        fechar();
                        mostrarToast(res.mensagem || 'A cozinha já recebeu o seu pedido.', res.total);
                    } else {
                        if (erroEl) {
                            erroEl.textContent = (res && res.erro) ? res.erro : 'Não foi possível enviar o pedido.';
                            erroEl.className = 'erro-campo visivel';
                        }
                    }
                    enviarBtn.disabled = false;
                    enviarBtn.innerHTML = svg('<path d="M4 12.5 9 17.5 20 6.5"/>', 17) + ' Finalizar pedido';
                })
                .catch(function () {
                    if (erroEl) {
                        erroEl.textContent = 'Erro de comunicação com o servidor.';
                        erroEl.className = 'erro-campo visivel';
                    }
                    enviarBtn.disabled = false;
                    enviarBtn.innerHTML = svg('<path d="M4 12.5 9 17.5 20 6.5"/>', 17) + ' Finalizar pedido';
                });
            });
        }

        desenhar();
    }

    /* ---------------------- FILTRO DA EMENTA PÚBLICA --------------- */

    function initFiltroPublico() {
        const abas = document.querySelectorAll('#abasCategoria [data-categoria]');
        if (!abas.length) return;

        abas.forEach(function (btn) {
            btn.addEventListener('click', function () {
                abas.forEach(function (b) { b.classList.remove('ativo'); });
                btn.classList.add('ativo');
                const cat = btn.getAttribute('data-categoria');
                document.querySelectorAll('#grelhaPratos [data-categoria]').forEach(function (cartao) {
                    const visivel = cat === 'todos' || cartao.getAttribute('data-categoria') === cat;
                    cartao.style.display = visivel ? '' : 'none';
                    if (visivel) {
                        cartao.style.animation = 'none';
                        void cartao.offsetWidth;
                        cartao.style.animation = '';
                    }
                });
            });
        });

        // Procura opcional dentro da ementa
        const procura = document.getElementById('procuraPublica');
        if (procura) {
            procura.addEventListener('input', function () {
                const termo = procura.value.trim().toLowerCase();
                document.querySelectorAll('#grelhaPratos [data-pesquisa]').forEach(function (c) {
                    c.style.display = (termo === '' || c.getAttribute('data-pesquisa').indexOf(termo) !== -1) ? '' : 'none';
                });
            });
        }
    }

    /* ---------------------- TOAST (notificação) -------------------- */

    function mostrarToast(texto, extra) {
        const toast = document.getElementById('toastPedido');
        if (!toast) return;
        const t = document.getElementById('toastTexto');
        if (t) t.textContent = texto + (extra ? ' · Total: ' + extra : '');
        toast.classList.add('visivel');
        setTimeout(function () { toast.classList.remove('visivel'); }, 5200);
    }
    window.saborproToast = mostrarToast;

    /* ---------------------- ATUALIZAÇÃO KDS -------------------- */

    function initAutoRefresh() {
        const alvo = document.body.getAttribute('data-auto-refresh');
        if (!alvo) return;

        setTimeout(function () {
            // Não recarrega enquanto o utilizador está a editar ou com um modal aberto
            const foco = document.activeElement;
            const aEditar = foco && /^(INPUT|SELECT|TEXTAREA)$/.test(foco.tagName);
            const modalAberto = document.querySelector('.modal.aberto');
            if (aEditar || modalAberto) {
                initAutoRefresh();
                return;
            }
            window.location.reload();
        }, Number(alvo) * 1000);
    }

    /* ---------------------- BASE PATH -------------------------- */

    function basePath() {
        const dir = window.location.pathname.replace(/\\/g, '/').replace(/\/[^/]*$/, '');
        return dir === '/' ? '' : dir;
    }
    window.saborproBase = basePath;

    /* ------------------ IMAGENS: FALLBACK LOCAL ----------------- */

    function initImagensFallback() {
        const placeholder = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">' +
            '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">' +
            '<stop offset="0" stop-color="#F3E9DA"/><stop offset="1" stop-color="#E2D0B6"/>' +
            '</linearGradient></defs>' +
            '<rect width="400" height="300" fill="url(#g)"/>' +
            '<circle cx="200" cy="138" r="52" fill="none" stroke="#B99A6E" stroke-width="3" opacity=".55"/>' +
            '<circle cx="200" cy="138" r="34" fill="none" stroke="#B99A6E" stroke-width="2" opacity=".35"/>' +
            '<path d="M150 216h100" stroke="#B99A6E" stroke-width="3" opacity=".45" stroke-linecap="round"/>' +
            '</svg>'
        );

        document.addEventListener('error', function (ev) {
            const img = ev.target;
            if (!img || img.tagName !== 'IMG' || img.dataset.falhouImagem === '1') return;
            if (img.id === 'itemImagemPreview') return;
            img.dataset.falhouImagem = '1';
            img.src = placeholder;
        }, true);

        // Imagens que já falharam antes de o script arrancar
        document.querySelectorAll('img').forEach(function (img) {
            if (img.complete && img.naturalWidth === 0 && img.id !== 'itemImagemPreview') {
                img.dataset.falhouImagem = '1';
                img.src = placeholder;
            }
        });
    }

    initImagensFallback();

    /* ---------------------- INTERNACIONALIZAÇÃO (PT/EN) --------- */

    const I18N = {
        pt: {
            nav_home: 'Início', nav_story: 'A Nossa História', nav_menu: 'Ementa',
            nav_reviews: 'Avaliações', nav_contact: 'Contacto',
            nav_admin: 'Acesso Reservado', nav_explore: 'Explorar Ementa',
            hero_eyebrow: 'Porto · desde 1998',
            hero_title: 'Cozinha honesta,<br><em>mesa posta com alma.</em>',
            hero_text: 'Produto local, receita de família e o tempo certo de cada prato. Uma sala pequena no coração do Porto, onde a carta muda com o mercado.',
            hero_btn_menu: 'Explorar Ementa', hero_btn_story: 'A Nossa História',
            hero_hours_label: 'Seg–Sáb · 12h00–23h00', hero_hours_text: 'Domingo · brunch até às 15h',
            hero_phone_text: 'Reservas e encomendas', hero_seal: 'cozinha de autor',
            story_eyebrow: 'A nossa filosofia',
            story_title: 'Nasceu de uma banca<br>no mercado do Bolhão.',
            story_p1: 'Em 1998, abrimos com quatro mesas, um fogão e a certeza de que <em>cozinhar é servir quem se gosta</em>. Vinte e oito anos depois, a sala cresceu — a forma de fazer as coisas não.',
            story_p2: 'Trabalhamos com cinco produtores da região. O peixe chega da lota de manhã, o pão é assado todos os dias às sete e as hortícolas vêm do mercado, não de um catálogo. Nada de congelados, nada de atalhos.',
            story_role: 'Chef e fundadora',
            val_years: 'anos de cozinha', val_suppliers: 'produtores locais', val_rating: 'avaliações de clientes',
            menu_eyebrow: 'Ementa da estação', menu_title: 'Escolha, adicione e receba.',
            menu_sub: 'Pedido directo, sem criação de conta. Takeaway ou consumo na mesa.',
            menu_all: 'Tudo', menu_search: 'Procurar um prato…', menu_add: 'Adicionar',
            menu_chef: 'Recomendação do Chef', menu_new: 'Novidade',
            rev_eyebrow: 'Avaliações', rev_title: 'Quem se senta à nossa mesa',
            rev_sub: 'Opiniões reais de quem já provou a nossa cozinha.',
            rev_count: 'Baseado em {n} avaliações', rev_cta: 'Deixar avaliação',
            rev_empty: 'Seja o primeiro a avaliar a nossa cozinha.', rev_verified: 'Cliente verificado',
            rev_form_title: 'Deixar avaliação',
            rev_form_sub: 'A sua opinião ajuda-nos a melhorar. Obrigado!',
            rev_form_rating: 'Classificação', rev_form_name: 'O seu nome',
            rev_form_name_ph: 'Nome', rev_form_comment: 'O seu comentário',
            rev_form_comment_ph: 'Conte-nos como foi a sua experiência…',
            rev_form_note: 'O comentário será publicado após aprovação da equipa.',
            rev_form_submit: 'Enviar avaliação',
            contact_tagline: 'Cozinha de autor no centro do Porto. Almoço, jantar e brunch de domingo. Reservas para grupos e eventos privados.',
            contact_order: 'Pedir agora', contact_address: 'Morada',
            contact_address_note: 'A 5 min de São Bento', contact_contact: 'Contacto',
            contact_reservations: 'Reservas e encomendas', contact_email_note: 'Resposta em 24 h',
            contact_hours: 'Horário', contact_week: 'Seg–Sáb', contact_sunday: 'Domingo',
            footer_text: 'Restaurante & Cafetaria — Sistema de Pedidos e Gestão',
            footer_admin: 'Acesso Reservado / Gestão',
            cart_btn: 'O meu pedido', cart_title: 'O seu pedido', cart_how: 'Como prefere receber?',
            cart_takeaway_desc: 'Pronto em ~20 min', cart_table: 'Na Mesa',
            cart_table_desc: 'Consumir no restaurante', cart_select_table: 'Escolha a mesa',
            cart_name: 'O seu nome', cart_name_ph: 'Nome', cart_phone: 'Telefone',
            cart_obs: 'Observações (opcional)', cart_obs_ph: 'Alergias, pedido especial…',
            cart_subtotal: 'Subtotal', cart_vat: 'IVA (13%)', cart_vat_included: 'incluído',
            cart_total: 'Total', cart_finish: 'Finalizar pedido',
            toast_title: 'Pedido confirmado', toast_text: 'A cozinha já recebeu o seu pedido.',
            cats: { 'Entradas': 'Entradas', 'Pratos': 'Pratos', 'Sobremesas': 'Sobremesas', 'Bebidas': 'Bebidas' }
        },
        en: {
            nav_home: 'Home', nav_story: 'Our Story', nav_menu: 'Menu',
            nav_reviews: 'Reviews', nav_contact: 'Contact',
            nav_admin: 'Staff Access', nav_explore: 'Explore Menu',
            hero_eyebrow: 'Porto · since 1998',
            hero_title: 'Honest cooking,<br><em>a table set with soul.</em>',
            hero_text: 'Local produce, family recipes and the right time for every dish. A small dining room in the heart of Porto, where the menu changes with the market.',
            hero_btn_menu: 'Explore Menu', hero_btn_story: 'Our Story',
            hero_hours_label: 'Mon–Sat · 12pm–11pm', hero_hours_text: 'Sunday · brunch until 3pm',
            hero_phone_text: 'Reservations & orders', hero_seal: 'signature cuisine',
            story_eyebrow: 'Our philosophy',
            story_title: 'It began at a stall<br>in the Bolhão market.',
            story_p1: 'In 1998 we opened with four tables, one stove and the certainty that <em>cooking is serving those you love</em>. Twenty-eight years later the room has grown — the way we do things has not.',
            story_p2: 'We work with five regional producers. The fish arrives from the market first thing, the bread is baked every day at seven and the vegetables come from the market, not a catalogue. No frozen food, no shortcuts.',
            story_role: 'Chef & founder',
            val_years: 'years of cooking', val_suppliers: 'local producers', val_rating: 'customer reviews',
            menu_eyebrow: 'Seasonal menu', menu_title: 'Choose, add and enjoy.',
            menu_sub: 'Order directly, no account needed. Takeaway or dine in.',
            menu_all: 'All', menu_search: 'Search a dish…', menu_add: 'Add',
            menu_chef: "Chef's recommendation", menu_new: 'New',
            rev_eyebrow: 'Reviews', rev_title: 'People at our table',
            rev_sub: 'Honest words from guests who have tasted our cooking.',
            rev_count: 'Based on {n} reviews', rev_cta: 'Leave a review',
            rev_empty: 'Be the first to review our kitchen.', rev_verified: 'Verified guest',
            rev_form_title: 'Leave a review',
            rev_form_sub: 'Your feedback helps us improve. Thank you!',
            rev_form_rating: 'Rating', rev_form_name: 'Your name',
            rev_form_name_ph: 'Name', rev_form_comment: 'Your comment',
            rev_form_comment_ph: 'Tell us about your experience…',
            rev_form_note: 'Your comment will be published after approval by our team.',
            rev_form_submit: 'Send review',
            contact_tagline: 'Signature cuisine in the centre of Porto. Lunch, dinner and Sunday brunch. Reservations for groups and private events.',
            contact_order: 'Order now', contact_address: 'Address',
            contact_address_note: '5 min from São Bento', contact_contact: 'Contact',
            contact_reservations: 'Reservations & orders', contact_email_note: 'Reply within 24 h',
            contact_hours: 'Opening hours', contact_week: 'Mon–Sat', contact_sunday: 'Sunday',
            footer_text: 'Restaurant & Café — Ordering & Management System',
            footer_admin: 'Staff Access / Management',
            cart_btn: 'My order', cart_title: 'Your order', cart_how: 'How would you like it?',
            cart_takeaway_desc: 'Ready in ~20 min', cart_table: 'Dine in',
            cart_table_desc: 'Eat at the restaurant', cart_select_table: 'Choose your table',
            cart_name: 'Your name', cart_name_ph: 'Name', cart_phone: 'Phone',
            cart_obs: 'Notes (optional)', cart_obs_ph: 'Allergies, special requests…',
            cart_subtotal: 'Subtotal', cart_vat: 'VAT (13%)', cart_vat_included: 'included',
            cart_total: 'Total', cart_finish: 'Place order',
            toast_title: 'Order confirmed', toast_text: 'The kitchen has received your order.',
            cats: { 'Entradas': 'Starters', 'Pratos': 'Mains', 'Sobremesas': 'Desserts', 'Bebidas': 'Drinks' }
        }
    };

    let idiomaAtual = 'pt';

    function traduzir(chave) {
        const dicionario = I18N[idiomaAtual] || I18N.pt;
        return dicionario[chave] || I18N.pt[chave] || '';
    }

    function aplicarIdioma(lang) {
        if (!I18N[lang]) lang = 'pt';
        idiomaAtual = lang;
        document.documentElement.lang = lang === 'pt' ? 'pt-PT' : 'en';

        document.querySelectorAll('[data-i18n]').forEach(function (el) {
            const txt = traduzir(el.getAttribute('data-i18n'));
            if (txt) el.textContent = txt;
        });

        document.querySelectorAll('[data-i18n-html]').forEach(function (el) {
            const txt = traduzir(el.getAttribute('data-i18n-html'));
            if (txt) el.innerHTML = txt;
        });

        document.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
            const txt = traduzir(el.getAttribute('data-i18n-placeholder'));
            if (txt) el.setAttribute('placeholder', txt);
        });

        /* Categorias da ementa (nomes vindos da BD) */
        const cats = (I18N[lang] && I18N[lang].cats) || {};
        document.querySelectorAll('[data-cat-nome]').forEach(function (el) {
            const original = el.getAttribute('data-cat-nome');
            el.textContent = cats[original] || original;
        });

        /* Opções de mesa do carrinho */
        document.querySelectorAll('#mesaPublica option').forEach(function (op) {
            const n = op.getAttribute('data-mesa');
            const l = op.getAttribute('data-lugares');
            if (!n) return;
            op.textContent = lang === 'en'
                ? 'Table ' + n + ' · ' + l + ' seats'
                : 'Mesa ' + n + ' · ' + l + ' lugares';
        });

        /* Contador de avaliações com valor dinâmico */
        document.querySelectorAll('[data-i18n="rev_count"]').forEach(function (el) {
            const n = el.getAttribute('data-n');
            el.textContent = traduzir('rev_count').replace('{n}', n !== null ? n : '0');
        });

        document.querySelectorAll('[data-lang]').forEach(function (b) {
            const ativo = b.getAttribute('data-lang') === lang;
            b.classList.toggle('ativo', ativo);
            b.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        });

        try { localStorage.setItem('saborproLang', lang); } catch (e) { /* sem localStorage */ }
    }

    function initIdioma() {
        document.querySelectorAll('[data-lang]').forEach(function (b) {
            b.addEventListener('click', function () {
                aplicarIdioma(b.getAttribute('data-lang'));
            });
        });

        let guardado = 'pt';
        try { guardado = localStorage.getItem('saborproLang') || 'pt'; } catch (e) { /* ignore */ }
        aplicarIdioma(guardado);
    }

    /* ---------------------- FORMULÁRIO DE AVALIAÇÃO -------------- */

    function initAvaliacao() {
        const form = document.getElementById('formAvaliacao');
        if (!form) return;

        const opcoes = Array.from(document.querySelectorAll('.estrela-opcao'));
        const erro = document.getElementById('avaliacaoErro');
        const nome = document.getElementById('avaliacaoNome');
        const comentario = document.getElementById('avaliacaoComentario');
        const botao = document.getElementById('btnEnviarAvaliacao');

        function notaAtual() {
            const escolhida = form.querySelector('input[name="classificacao"]:checked');
            return escolhida ? Number(escolhida.value) : 5;
        }

        function pintar(n) {
            opcoes.forEach(function (o, i) { o.classList.toggle('ativa', i < n); });
        }

        opcoes.forEach(function (o, i) {
            o.addEventListener('mouseenter', function () { pintar(i + 1); });
            o.addEventListener('mouseleave', function () { pintar(notaAtual()); });
            const input = o.querySelector('input');
            if (input) input.addEventListener('change', function () { pintar(i + 1); });
        });
        pintar(notaAtual());

        function mostrarErro(msg) {
            if (!erro) return;
            erro.textContent = msg;
            erro.classList.add('visivel');
        }

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            if (erro) { erro.classList.remove('visivel'); erro.textContent = ''; }

            const nomeV = nome ? nome.value.trim() : '';
            const comentarioV = comentario ? comentario.value.trim() : '';

            if (nomeV.length < 2) {
                mostrarErro('Indique o seu nome (mínimo 2 caracteres).');
                if (nome) nome.focus();
                return;
            }
            if (comentarioV.length < 3) {
                mostrarErro('Escreva um pequeno comentário sobre a sua experiência.');
                if (comentario) comentario.focus();
                return;
            }

            botao.disabled = true;
            const base = window.saborproBase ? window.saborproBase() : '';
            const honeypot = document.getElementById('avaliacaoWebsite');

            fetch(base + '/api/avaliacao.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nome: nomeV,
                    classificacao: notaAtual(),
                    comentario: comentarioV,
                    website: honeypot ? honeypot.value : ''
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                botao.disabled = false;
                if (!res || !res.ok) {
                    mostrarErro((res && res.erro) || 'Não foi possível enviar a avaliação.');
                    return;
                }

                window.saborproFecharModal('modalAvaliacao');
                form.reset();
                pintar(notaAtual());
                if (window.saborproToast) window.saborproToast(res.mensagem || 'Obrigado pela sua avaliação.');
            })
            .catch(function () {
                botao.disabled = false;
                mostrarErro('Sem ligação ao servidor. Tente novamente.');
            });
        });
    }

    /* ---------------------- ARRANQUE --------------------------- */

    document.addEventListener('DOMContentLoaded', function () {
        initIdioma();
        initModais();
        initAlertas();
        initValidacao();
        initMenu();
        initFiltro();
        initCarrinho();
        initCarrinhoPublico();
        initFiltroPublico();
        initAvaliacao();
        initImagensFallback();
        initAutoRefresh();
    });
})();
