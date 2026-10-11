/**
 * AutoCEP — Checkout Moderno (Multietapas)
 *
 * Reorganiza o checkout clássico do WooCommerce em 3 etapas:
 *   1. Identificação (e-mail, nome, documento, telefone, criar conta)
 *   2. Entrega (CEP + endereço com o autocompletar do AutoCEP, forma de entrega)
 *   3. Pagamento (formas de pagamento com layout por tipo + finalizar)
 * e um resumo do pedido fixo ao lado (recolhível no celular).
 *
 * Princípio: os elementos originais do WooCommerce são MOVIDOS para dentro
 * dos painéis, sempre dentro do mesmo <form class="checkout">. Nada é
 * clonado nem recriado, então:
 *  - o WooCommerce continua validando e enviando o pedido normalmente
 *    (todos os eventos dele são delegados a partir do form.checkout);
 *  - as atualizações AJAX ("update_checkout") substituem a tabela de resumo
 *    e o bloco de pagamento pela classe CSS, onde quer que eles estejam;
 *  - gateways e plugins de campos continuam encontrando seus elementos.
 *
 * Também roda na página "Pedido recebido" (mode = thankyou) para destacar
 * o QR Code e o código Pix copia e cola.
 */
jQuery(function ($) {
    'use strict';

    if (typeof autocep_mc_params === 'undefined') {
        return;
    }

    var params = autocep_mc_params;
    var i18n   = params.i18n;

    /* -----------------------------------------------------------------
     * Página "Pedido recebido": destaque do Pix
     * ------------------------------------------------------------- */

    /**
     * Os gateways de Pix exibem o código "copia e cola" de formas variadas
     * (input, textarea, <code>, parágrafo). Todo código Pix segue o padrão
     * EMV do Banco Central e começa com "000201" — é por isso que dá para
     * encontrá-lo de forma genérica, sem depender de cada gateway.
     */
    function enhancePixThankYou() {
        // Já destacado numa passada anterior.
        if ($('.autocep-mc-pix').length) {
            return;
        }

        var $area = $('.woocommerce-order, .woocommerce-thankyou-order-details').first().parent();

        if (!$area.length) {
            $area = $('body');
        }

        var encontrado = false;

        $area.find('input, textarea, code, pre, p, span, div').each(function () {
            if (encontrado) {
                return false;
            }

            var $el    = $(this);
            var ehCampo = $el.is('input, textarea');

            // Só elementos "folha" (sem filhos com o mesmo texto), para não
            // envolver um container inteiro da página.
            if (!ehCampo && $el.children().length) {
                return;
            }

            var codigo = $.trim(ehCampo ? $el.val() : $el.text());

            // Padrão EMV: começa com "000201" e termina com o CRC "6304XXXX".
            // O código pode ter espaços (ex.: o nome da loja vai dentro dele).
            if (!/^000201.{40,}6304[0-9A-Fa-f]{4}$/.test(codigo)) {
                return;
            }

            encontrado = true;

            var $caixa = $el.closest('.autocep-mc-pix');

            if (!$caixa.length) {
                $caixa = $('<div class="autocep-mc-pix"></div>');
                $caixa.append($('<p class="autocep-mc-pix__title"></p>').text(i18n.pix_title));
                $el.before($caixa);

                // QR Code do gateway (imagem/canvas/SVG mais próximo), se houver.
                var $qr = $el.parent().find('img, canvas, svg').filter(function () {
                    var info = ((this.alt || '') + ' ' + (this.src || '') + ' ' + (this.className || '')).toLowerCase();
                    return /qr|pix|data:image/.test(info) || $(this).width() >= 120;
                }).first();

                if ($qr.length) {
                    $caixa.append($('<div class="autocep-mc-pix__qr"></div>').append($qr));
                }

                $caixa.append($('<div class="autocep-mc-pix__code"></div>').text(codigo));
            }

            var $botao = $('<button type="button" class="autocep-mc-btn autocep-mc-pix__copy"></button>').text(i18n.copy_pix);

            $botao.on('click', function () {
                var copiar = navigator.clipboard && window.isSecureContext
                    ? navigator.clipboard.writeText(codigo)
                    : $.Deferred(function (d) {
                        var $tmp = $('<textarea>').val(codigo).appendTo('body').trigger('select');
                        document.execCommand('copy');
                        $tmp.remove();
                        d.resolve();
                    }).promise();

                $.when(copiar).then(function () {
                    $botao.text(i18n.copied).addClass('is-copied');
                    setTimeout(function () {
                        $botao.text(i18n.copy_pix).removeClass('is-copied');
                    }, 2500);
                });
            });

            $caixa.append($botao);

            // O código já aparece na caixa nova; esconde o original do gateway.
            $el.hide();
        });
    }

    if ('thankyou' === params.mode) {
        enhancePixThankYou();
        // Alguns gateways montam o Pix via JavaScript depois do carregamento.
        setTimeout(enhancePixThankYou, 1500);
        return;
    }

    /* -----------------------------------------------------------------
     * Checkout em etapas
     * ------------------------------------------------------------- */

    var $form = $('form.checkout');

    // Só o checkout clássico; nunca o Checkout em Blocos nem uma segunda vez.
    if (!$form.length || $form.closest('.wp-block-woocommerce-checkout').length || $form.find('.autocep-mc').length) {
        return;
    }

    // Campos da etapa 1, na ordem exibida. Inclui os campos dos plugins
    // brasileiros mais comuns (Brazilian Market: tipo de pessoa, CPF, CNPJ...).
    var CAMPOS_IDENTIFICACAO = [
        'billing_email', 'billing_first_name', 'billing_last_name',
        'billing_persontype', 'billing_cpf', 'billing_rg', 'billing_cnpj', 'billing_ie',
        'billing_company', 'billing_birthdate', 'billing_sex',
        'billing_phone', 'billing_cellphone'
    ];

    var TOTAL_ETAPAS = 3;

    var MC = {
        etapaAtual: 1,
        etapaMaxima: 1,
        $root: null,
        $panels: null,
        $progress: null,

        init: function () {
            this.build();
            this.bindEvents();

            if (params.compact) {
                this.applyCompactMode();
            }

            this.refreshShipping();
            this.decoratePayment();
            this.hideEmptyCells();

            // Cliente logado/retornando: o simulador já começa com o CEP dele.
            var cepAtual = $form.find('#billing_postcode').val();

            if (cepAtual) {
                this.$root.find('.autocep-mc__simulator .autocep-shipping-cep-input').val(cepAtual);
            }

            this.goTo(1, false);
            this.track('view');
        },

        /* ---------- Montagem ---------- */

        build: function () {
            var self = this;

            this.$root = $('<div class="autocep-mc"></div>');

            // Barra de etapas.
            this.$progress = $('<ol class="autocep-mc__progress"></ol>');

            params.steps.forEach(function (titulo, indice) {
                var numero = indice + 1;
                var $item  = $('<li class="autocep-mc__progress-item"></li>').attr('data-step', numero);

                $item.append($('<button type="button" class="autocep-mc__progress-btn"></button>')
                    .append($('<span class="autocep-mc__progress-num"></span>').text(numero))
                    .append($('<span class="autocep-mc__progress-label"></span>').text(titulo)));

                self.$progress.append($item);
            });

            var $main    = $('<div class="autocep-mc__main"></div>');
            var $summary = $('<aside class="autocep-mc__summary"></aside>');

            // Painéis das etapas.
            params.steps.forEach(function (titulo, indice) {
                var numero = indice + 1;
                var $panel = $('<section class="autocep-mc__panel" role="group"></section>')
                    .attr({ 'data-step': numero, 'aria-label': titulo });

                $panel.append($('<h2 class="autocep-mc__panel-title"></h2>')
                    .append($('<span class="autocep-mc__panel-num"></span>').text(numero))
                    .append(document.createTextNode(' ' + titulo)));

                $panel.append('<div class="autocep-mc__panel-body"></div>');

                var $nav = $('<div class="autocep-mc__nav"></div>');

                if (numero > 1) {
                    $nav.append($('<button type="button" class="autocep-mc__back"></button>').text('← ' + i18n.back));
                }

                if (numero < TOTAL_ETAPAS) {
                    $nav.append($('<button type="button" class="autocep-mc-btn autocep-mc__next"></button>').text(i18n.next));
                }

                $panel.append($nav);
                $main.append($panel);
            });

            // A barra de etapas fica numa linha própria, acima do formulário:
            // assim o formulário e o resumo começam na mesma altura.
            this.$root.append(this.$progress, $main, $summary);

            // Insere logo antes dos campos do cliente — o que outros plugins
            // colocaram antes disso (avisos, login, cupom) continua acima.
            var $detalhes = $form.find('#customer_details');

            if ($detalhes.length) {
                $detalhes.before(this.$root);
            } else {
                $form.prepend(this.$root);
            }

            this.$panels = this.$root.find('.autocep-mc__panel');

            var $corpo1 = this.panelBody(1);
            var $corpo2 = this.panelBody(2);
            var $corpo3 = this.panelBody(3);

            // --- Etapa 1: Identificação ---
            var $grupo1 = $('<div class="autocep-mc__fields"></div>').appendTo($corpo1);

            // Campos extras criados no painel para a etapa 1 vêm logo depois.
            CAMPOS_IDENTIFICACAO.concat(params.step1_fields || []).forEach(function (id) {
                var $linha = $form.find('#' + id + '_field');

                if ($linha.length) {
                    $grupo1.append($linha);
                }
            });

            // Criar conta / senha (checkout como visitante).
            var $conta = $form.find('.woocommerce-account-fields').appendTo($corpo1);

            // Aviso explicando a criação de conta (quem não tem conta).
            var $aviso = params.account && $conta.length ? this.buildAccountNotice($conta) : $();

            // Login dentro da etapa 1, no lugar do formulário do topo.
            this.buildInlineLogin($corpo1, $aviso);

            // --- Etapa 2: Entrega ---
            // Tudo o que estava nas colunas do #customer_details (campos de
            // cobrança restantes, entrega, observações e o que outros plugins
            // inseriram ali), na mesma ordem.
            var $colunas = $detalhes.children('.col-1, .col-2');
            var $origens = $colunas.length ? $colunas : $detalhes;

            $origens.each(function () {
                $(this).children().appendTo($corpo2);
            });

            // Algo inserido direto no #customer_details, fora das colunas.
            if ($colunas.length) {
                $detalhes.children().not('.col-1, .col-2').appendTo($corpo2);
            }

            $detalhes.addClass('autocep-mc__moved');

            // Painel de forma de entrega (espelho dos métodos do resumo).
            if (params.needs_shipping) {
                $corpo2.append(
                    $('<div class="autocep-mc__shipping"></div>')
                        .append($('<h3 class="autocep-mc__subtitle"></h3>').text(i18n.shipping_title))
                        .append($('<p class="autocep-mc__shipping-loading" aria-live="polite"></p>').text(i18n.shipping_loading))
                        .append('<div class="autocep-mc__shipping-list"></div>')
                );
            }

            // Simulador de frete do AutoCEP: no topo da etapa Entrega (onde
            // quer que o hook o tenha colocado, até fora do formulário) ou
            // oculto, já que as formas de entrega aparecem na própria etapa.
            var $simulador = $('.autocep-shipping-box--checkout');

            if ($simulador.length) {
                if ('hide' === params.simulator) {
                    $simulador.addClass('autocep-mc__moved');
                } else {
                    $simulador.addClass('autocep-mc__simulator').prependTo($corpo2);
                }
            }

            // --- Etapa 3: Pagamento ---
            $form.find('.woocommerce-checkout-payment').appendTo($corpo3);
            $corpo3.prepend('<div class="autocep-mc__pay-note" hidden></div>');

            // --- Resumo do pedido ---
            var $toggle = $('<button type="button" class="autocep-mc__summary-toggle" aria-expanded="false"></button>')
                .append($('<span class="autocep-mc__summary-toggle-label"></span>').text(i18n.show_summary))
                .append('<span class="autocep-mc__summary-toggle-total"></span>');

            $summary.append($toggle)
                .append($('<h3 class="autocep-mc__summary-title"></h3>').text(i18n.summary))
                .append($('<div class="autocep-mc__summary-body"></div>').append($form.find('.woocommerce-checkout-review-order-table')));

            this.buildCoupon($summary.find('.autocep-mc__summary-body'));

            // Outros elementos que plugins colocaram no #order_review (fora da
            // tabela e do pagamento) vão para a etapa de pagamento.
            $form.find('#order_review').children().prependTo($corpo3);
            $form.find('#order_review_heading, #order_review').addClass('autocep-mc__moved');

            this.updateSummaryTotal();
        },

        /**
         * Caixa "Sua conta será criada nesta compra" (ou "Crie sua conta —
         * opcional") acima do campo de senha / caixa "Criar uma conta", com
         * o link "Já tem conta? Entrar".
         */
        buildAccountNotice: function ($conta) {
            var conta  = params.account;
            var $aviso = $('<div class="autocep-mc__account-notice" role="note"></div>').attr('data-mode', conta.mode);
            var $texto = $('<p class="autocep-mc__account-text"></p>');
            var partes = String(conta.message).split('{email}');

            // "{email}" vira o e-mail que o cliente está digitando.
            partes.forEach(function (parte, indice) {
                if (indice > 0) {
                    $texto.append('<strong class="autocep-mc__account-email"></strong>');
                }

                $texto.append(document.createTextNode(parte));
            });

            var atualizarEmail = function () {
                var email = $.trim($form.find('#billing_email').val() || '');

                $texto.find('.autocep-mc__account-email').text(email || conta.email_fallback).toggleClass('is-empty', !email);
            };

            atualizarEmail();
            $form.on('input change', '#billing_email', atualizarEmail);

            $aviso
                .append($('<strong class="autocep-mc__account-title"></strong>').text(conta.title))
                .append($texto);

            if (conta.login_url) {
                $aviso.append($('<a class="autocep-mc__account-login"></a>').attr('href', conta.login_url).text(conta.login_text));
            }

            $conta.before($aviso);

            return $aviso;
        },

        /**
         * Login dentro da etapa 1.
         *
         * O WooCommerce mostra o login num formulário separado no topo da
         * página ("Já é cliente? Clique aqui para entrar") — com o campo de
         * senha da conta nova, ficavam dois pedidos de senha. Aqui o do topo
         * é escondido e o login abre na própria etapa 1. Quem entra de fato é
         * o formulário original do WooCommerce (escondido): estes campos só
         * repassam os dados, então segurança e redirecionamento são os dele.
         * Não dá para mover o formulário original para cá: um formulário
         * dentro do checkout faria o "Entrar" disparar o pedido.
         */
        buildInlineLogin: function ($corpo1, $aviso) {
            var textos = params.login;
            var $original = $('form.woocommerce-form-login').first();

            if (!textos || !$original.length) {
                return; // cliente logado ou loja sem login no checkout
            }

            $('.woocommerce-form-login-toggle').addClass('autocep-mc__moved');
            $original.addClass('autocep-mc__moved');

            var $painel = $('<div class="autocep-mc__login" hidden></div>');
            var $usuario = $('<input type="text" class="autocep-mc__login-input" autocomplete="username">').attr({ placeholder: textos.user, 'aria-label': textos.user });
            var $senha = $('<input type="password" class="autocep-mc__login-input" autocomplete="current-password">').attr({ placeholder: textos.password, 'aria-label': textos.password });
            var $lembrar = $('<input type="checkbox">');
            var $erro = $('<p class="autocep-mc__login-error" role="alert" hidden></p>');
            var $entrar = $('<button type="button" class="autocep-mc-btn autocep-mc__login-submit"></button>').text(textos.submit);
            var $cancelar = $('<button type="button" class="autocep-mc__login-cancel"></button>').text(textos.cancel);

            // Campos SEM name: não são enviados junto com o pedido.
            $painel
                .append($('<strong class="autocep-mc__login-title"></strong>').text(textos.title))
                .append($('<div class="autocep-mc__login-fields"></div>').append($usuario, $senha))
                .append($('<label class="autocep-mc__login-remember"></label>').append($lembrar, document.createTextNode(' ' + textos.remember)))
                .append($erro)
                .append($('<div class="autocep-mc__login-actions"></div>').append($entrar, $cancelar))
                .append($('<a class="autocep-mc__login-lost"></a>').attr('href', textos.lost_url).text(textos.lost));

            // Onde abre: logo depois do aviso de conta; sem aviso, no topo da etapa.
            var $abrir;

            if ($aviso.length) {
                $abrir = $aviso.find('.autocep-mc__account-login');

                if (!$abrir.length) {
                    $abrir = $('<a class="autocep-mc__account-login" href="#"></a>').text(textos.link).appendTo($aviso);
                }

                $aviso.after($painel);
            } else {
                $abrir = $('<a class="autocep-mc__account-login autocep-mc__login-link" href="#"></a>').text(textos.link);
                $corpo1.prepend($painel).prepend($abrir);
            }

            $abrir.on('click', function (evento) {
                evento.preventDefault();
                $painel.prop('hidden', false);
                $abrir.prop('hidden', true);
                // Entrando numa conta: o campo de senha da conta nova sai de cena
                // (senão seriam dois campos de senha de novo).
                $form.find('.woocommerce-account-fields').addClass('autocep-mc-collapsed');
                $usuario.val($usuario.val() || $.trim($form.find('#billing_email').val() || '')).trigger('focus');
            });

            $cancelar.on('click', function () {
                $painel.prop('hidden', true);
                $abrir.prop('hidden', false);
                $form.find('.woocommerce-account-fields').removeClass('autocep-mc-collapsed');
            });

            var entrar = function () {
                if (!$.trim($usuario.val()) || !$senha.val()) {
                    $erro.text(textos.empty).prop('hidden', false);
                    return;
                }

                $original.find('input[name="username"]').val($.trim($usuario.val()));
                $original.find('input[name="password"]').val($senha.val());
                $original.find('input[name="rememberme"]').prop('checked', $lembrar.prop('checked'));
                $entrar.prop('disabled', true);

                // Clica no botão original: o WooCommerce faz o login e volta ao checkout.
                var botao = $original.find('button[name="login"], [type="submit"]').get(0);

                if (botao) {
                    botao.click();
                } else {
                    $original.get(0).submit();
                }
            };

            $entrar.on('click', entrar);

            $painel.on('input', 'input', function () {
                $erro.prop('hidden', true);
            });

            // Enter nos campos de login entra (e não avança a etapa nem envia o pedido).
            $painel.on('keydown', 'input', function (evento) {
                if (13 === evento.which) {
                    evento.preventDefault();
                    evento.stopPropagation();
                    entrar();
                }
            });
        },

        /**
         * Cupom de desconto dentro do resumo do pedido.
         *
         * O formulário de cupom do WooCommerce fica fora do checkout (acima
         * dele). Ele continua lá, escondido, e é ele quem aplica o cupom:
         * este campo só repassa o código e aciona o envio original. Não dá
         * para mover o formulário para dentro do checkout — um formulário
         * dentro de outro faria o Enter do cupom disparar o pedido.
         */
        buildCoupon: function ($destino) {
            var $original = $('form.checkout_coupon');

            if (!$original.length) {
                return; // Cupons desativados na loja.
            }

            $('.woocommerce-form-coupon-toggle').addClass('autocep-mc__moved');
            $original.addClass('autocep-mc__moved');

            var $caixa = $('<div class="autocep-mc__coupon"></div>');
            var $abrir = $('<button type="button" class="autocep-mc__coupon-toggle"></button>').text(i18n.coupon_question);
            var $linha = $('<div class="autocep-mc__coupon-row" hidden></div>');
            var $campo = $('<input type="text" class="autocep-mc__coupon-input" autocomplete="off">').attr({ placeholder: i18n.coupon_placeholder, 'aria-label': i18n.coupon_placeholder });
            var $botao = $('<button type="button" class="autocep-mc__coupon-apply"></button>').text(i18n.coupon_apply);
            var $aviso = $('<p class="autocep-mc__coupon-msg" role="status" hidden></p>');

            $linha.append($campo, $botao);
            $caixa.append($abrir, $linha, $aviso).appendTo($destino);

            $abrir.on('click', function () {
                $linha.prop('hidden', false);
                $abrir.prop('hidden', true);
                $campo.trigger('focus');
            });

            var aplicar = function () {
                var codigo = $.trim($campo.val());

                if (!codigo) {
                    $campo.trigger('focus');
                    return;
                }

                $aviso.prop('hidden', true).removeClass('is-error is-success');
                $botao.prop('disabled', true).addClass('is-loading');
                $original.find('input[name="coupon_code"]').val(codigo);
                $original.trigger('submit');

                // Falha de conexão (sem resposta do WooCommerce): libera o botão.
                setTimeout(function () {
                    $botao.prop('disabled', false).removeClass('is-loading');
                }, 15000);
            };

            $botao.on('click', aplicar);

            // Enter no campo aplica o cupom (e nunca envia o pedido).
            $campo.on('keydown', function (evento) {
                if (13 === evento.which) {
                    evento.preventDefault();
                    evento.stopPropagation();
                    aplicar();
                }
            });

            $(document.body).on('errored_coupon_in_checkout', function (evento, codigo, resposta) {
                var texto = $.trim($('<div>').html(resposta).text());

                $aviso.text(texto).addClass('is-error').removeClass('is-success').prop('hidden', !texto);
            });

            $(document.body).on('applied_coupon_in_checkout', function () {
                $botao.prop('disabled', false).removeClass('is-loading');

                if ($aviso.hasClass('is-error')) {
                    return;
                }

                $aviso.text(i18n.coupon_applied).addClass('is-success').prop('hidden', false);
                $campo.val('');

                // O WooCommerce também insere o aviso de sucesso acima do
                // formulário original (escondido); o resumo já mostra o cupom.
                setTimeout(function () {
                    $original.prevAll('.woocommerce-message, .woocommerce-info, .is-success').first().remove();
                }, 600);
            });
        },

        /* ---------- Analytics (funil do checkout) ---------- */

        eventosEnviados: {},

        /**
         * Informa um passo do funil ao servidor (aba Analytics). Anônimo:
         * só o nome do passo, o tipo de pagamento ou o ID do campo com erro.
         * Cada passo é enviado uma vez por carregamento da página.
         */
        track: function (evento, detalhe) {
            if (!params.track || !params.track.enabled) {
                return;
            }

            var chave = evento + ':' + (detalhe || '');

            if (this.eventosEnviados[chave]) {
                return;
            }

            this.eventosEnviados[chave] = true;

            $.post(params.track.url, {
                action: 'autocep_mc_track',
                nonce: params.track.nonce,
                evento: evento,
                detalhe: detalhe || '',
                dispositivo: window.matchMedia && window.matchMedia('(max-width: 767px)').matches ? 'mobile' : 'desktop'
            });
        },

        /**
         * Tira da grade de campos os elementos vazios que temas e plugins
         * inserem (ex.: um <p></p> antes do primeiro campo do endereço de
         * entrega), que deixavam uma vaga em branco antes de "Nome".
         */
        hideEmptyCells: function () {
            this.$root.find('.woocommerce-billing-fields__field-wrapper, .woocommerce-shipping-fields__field-wrapper, .autocep-mc__fields').children().each(function () {
                var $filho = $(this);

                if ($filho.hasClass('form-row') || $filho.hasClass('autocep-mc__reveal') || $filho.find(':input, img, iframe, svg, select').length) {
                    return;
                }

                $filho.toggleClass('autocep-mc__empty-cell', '' === $.trim($filho.text()));
            });
        },

        panelBody: function (numero) {
            return this.$panels.filter('[data-step="' + numero + '"]').find('.autocep-mc__panel-body');
        },

        /* ---------- Eventos ---------- */

        bindEvents: function () {
            var self = this;

            this.$root.on('click', '.autocep-mc__next', function () {
                self.next();
            });

            this.$root.on('click', '.autocep-mc__back', function () {
                self.goTo(self.etapaAtual - 1, true);
            });

            // Voltar a uma etapa já visitada clicando na barra de etapas.
            this.$progress.on('click', '.autocep-mc__progress-btn', function () {
                var destino = parseInt($(this).parent().attr('data-step'), 10);

                if (destino < self.etapaAtual) {
                    self.goTo(destino, true);
                } else if (destino > self.etapaAtual && destino <= self.etapaMaxima) {
                    // Avançar pela barra também valida as etapas no caminho.
                    self.next(destino);
                }
            });

            // Enter nas etapas 1 e 2 avança em vez de enviar o pedido.
            $form.on('keydown', '.autocep-mc__panel:not([data-step="3"]) input', function (evento) {
                // Lista de sugestões de endereço aberta: o Enter escolhe a sugestão.
                if ('true' === $(this).attr('aria-expanded')) {
                    return;
                }

                if (13 === evento.which && !$(this).is('textarea')) {
                    evento.preventDefault();
                    self.next();
                }
            });

            // Limpa o erro de um campo assim que o cliente corrige.
            $form.on('input change', '.autocep-mc__panel .form-row :input', function () {
                var $linha = $(this).closest('.form-row');

                if ($linha.hasClass('autocep-mc-invalid') && self.fieldError($linha) === '') {
                    self.setFieldError($linha, '');
                }
            });

            // Resumo recolhível (celular).
            this.$root.on('click', '.autocep-mc__summary-toggle', function () {
                var aberto = self.$root.toggleClass('is-summary-open').hasClass('is-summary-open');

                $(this).attr('aria-expanded', aberto ? 'true' : 'false')
                    .find('.autocep-mc__summary-toggle-label')
                    .text(aberto ? i18n.hide_summary : i18n.show_summary);
            });

            // Indicador de carregamento das formas de entrega enquanto o
            // WooCommerce recalcula (resposta visual imediata ao cliente).
            $(document.body).on('update_checkout', function () {
                self.$root.find('.autocep-mc__shipping').addClass('is-loading');
            });

            // CEP completo na etapa Entrega: recalcula o frete na hora, sem
            // esperar o cliente sair do campo, e espelha o CEP no simulador.
            $form.on('input', '#billing_postcode, #shipping_postcode', function () {
                var digitos = $(this).val().replace(/\D/g, '');

                self.$root.find('.autocep-mc__simulator .autocep-shipping-cep-input').val($(this).val());

                if (8 === digitos.length && digitos !== $(this).data('autocepMcUltimoCep')) {
                    $(this).data('autocepMcUltimoCep', digitos);
                    $(document.body).trigger('update_checkout');
                }
            });

            // Fragmentos do WooCommerce substituíram resumo/pagamento.
            $(document.body).on('updated_checkout', function () {
                self.$root.find('.autocep-mc__shipping').removeClass('is-loading');
                self.refreshShipping();
                self.hideEmptyCells();
                self.decoratePayment();
                self.updateSummaryTotal();
            });

            $form.on('checkout_place_order', function () {
                var escolhido = $form.find('input[name="payment_method"]:checked').val();

                self.track('submit', self.paymentType(escolhido));
                // Sem retorno: não interfere na decisão do WooCommerce de enviar.
            });

            $(document.body).on('payment_method_selected', function () {
                self.decoratePayment();
            });

            // Seleção de frete pelo painel da etapa 2.
            this.$root.on('change', '.autocep-mc__shipping-list input', function () {
                var valor    = $(this).val();

                // Destaque imediato, sem esperar o recálculo do WooCommerce.
                self.$root.find('.autocep-mc__option').removeClass('is-selected');
                $(this).closest('.autocep-mc__option').addClass('is-selected');

                var $origem  = self.$root.find('.autocep-mc__summary input.shipping_method').filter(function () {
                    return this.value === valor;
                });

                if ($origem.length && !$origem.prop('checked')) {
                    $origem.prop('checked', true).trigger('change');
                }

                self.$root.find('.autocep-mc__shipping').removeClass('autocep-mc-invalid');
            });

            // Erro do servidor ao finalizar: leva o cliente à etapa do
            // primeiro campo com problema (o WooCommerce informa o campo em
            // data-id nos itens da lista de erros).
            $(document.body).on('checkout_error', function () {
                var etapaDoErro = null;

                $form.find('.woocommerce-NoticeGroup-checkout [data-id]').each(function () {
                    self.track('error', $(this).attr('data-id'));

                    var $campo = $form.find('#' + $(this).attr('data-id'));
                    var $panel = $campo.closest('.autocep-mc__panel');

                    if ($panel.length) {
                        etapaDoErro = parseInt($panel.attr('data-step'), 10);
                        return false;
                    }
                });

                if (null === etapaDoErro) {
                    var $invalido = self.$panels.find('.woocommerce-invalid').first();

                    if ($invalido.length) {
                        etapaDoErro = parseInt($invalido.closest('.autocep-mc__panel').attr('data-step'), 10);
                    }
                }

                if (etapaDoErro && etapaDoErro !== self.etapaAtual) {
                    self.goTo(etapaDoErro, false);
                }
            });
        },

        /* ---------- Navegação ---------- */

        next: function (destino) {
            var alvo = destino || this.etapaAtual + 1;

            for (var etapa = this.etapaAtual; etapa < alvo; etapa++) {
                if (!this.validateStep(etapa)) {
                    if (etapa !== this.etapaAtual) {
                        this.goTo(etapa, true);
                    }

                    return;
                }
            }

            this.goTo(alvo, true);
        },

        goTo: function (numero, rolar) {
            numero = Math.max(1, Math.min(TOTAL_ETAPAS, numero));

            this.etapaAtual  = numero;
            this.etapaMaxima = Math.max(this.etapaMaxima, numero);

            this.$panels.each(function () {
                var ativo = parseInt($(this).attr('data-step'), 10) === numero;

                $(this).toggleClass('is-active', ativo).attr('aria-hidden', ativo ? 'false' : 'true');
            });

            var self = this;

            this.$progress.children().each(function () {
                var etapa = parseInt($(this).attr('data-step'), 10);

                $(this)
                    .toggleClass('is-active', etapa === numero)
                    .toggleClass('is-done', etapa < numero || (etapa <= self.etapaMaxima && etapa !== numero))
                    .find('button')
                    .attr('aria-current', etapa === numero ? 'step' : null);
            });

            this.$root.attr('data-current-step', numero);

            if (numero > 1) {
                this.track('step' + numero);
            }

            if (rolar) {
                var topo = this.$root.offset().top - 20;

                if ($(window).scrollTop() > topo) {
                    $('html, body').animate({ scrollTop: topo }, 250);
                }

                var $primeiro = this.$panels.filter('.is-active').find('.form-row :input:visible').filter(function () {
                    return !this.value;
                }).first();

                if ($primeiro.length && numero < TOTAL_ETAPAS) {
                    setTimeout(function () {
                        $primeiro.trigger('focus');
                    }, 260);
                }
            }

            $(document.body).trigger('autocep_mc_step_changed', [numero]);
        },

        /* ---------- Validação ---------- */

        /**
         * Erro de um campo, ou '' se estiver válido. Segue as mesmas regras
         * do WooCommerce (classe validate-required / validate-email) e
         * acrescenta checagens leves de telefone e CEP brasileiros.
         */
        fieldError: function ($linha) {
            if (!$linha.is(':visible')) {
                return '';
            }

            var $campo = $linha.find(':input').not('[type="hidden"]').first();

            if (!$campo.length) {
                return '';
            }

            var valor = $campo.is(':checkbox') ? ($campo.prop('checked') ? '1' : '') : $.trim($campo.val() || '');
            var id    = $campo.attr('id') || '';

            if ($linha.hasClass('validate-required') && '' === valor) {
                return i18n.required;
            }

            if ('' === valor) {
                return '';
            }

            if ($linha.hasClass('validate-email') && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(valor)) {
                return i18n.invalid_email;
            }

            if (/_phone$|_cellphone$/.test(id) && valor.replace(/\D/g, '').length < 10) {
                return i18n.invalid_phone;
            }

            if (/_postcode$/.test(id)) {
                var pais = $form.find('#' + id.replace('_postcode', '_country')).val();

                if ((!pais || 'BR' === pais) && 8 !== valor.replace(/\D/g, '').length) {
                    return i18n.invalid_cep;
                }
            }

            return '';
        },

        setFieldError: function ($linha, mensagem) {
            $linha.find('.autocep-mc__error').remove();

            if (mensagem) {
                $linha.addClass('autocep-mc-invalid woocommerce-invalid').removeClass('woocommerce-validated');
                $linha.append($('<span class="autocep-mc__error" role="alert"></span>').text(mensagem));
            } else {
                $linha.removeClass('autocep-mc-invalid woocommerce-invalid woocommerce-invalid-required-field');
            }
        },

        validateStep: function (numero) {
            var self      = this;
            var $panel    = this.$panels.filter('[data-step="' + numero + '"]');
            var $primeiro = null;

            $panel.find('.form-row').each(function () {
                var $linha   = $(this);
                var mensagem = self.fieldError($linha);

                self.setFieldError($linha, mensagem);

                if (mensagem && !$primeiro) {
                    $primeiro = $linha;
                }
            });

            // Etapa 2: exige uma forma de entrega quando o carrinho precisa.
            if (2 === numero && !$primeiro && params.needs_shipping && !this.hasShippingChoice()) {
                var $caixa = this.$root.find('.autocep-mc__shipping').addClass('autocep-mc-invalid');

                $caixa.find('.autocep-mc__error').remove();
                $caixa.append($('<span class="autocep-mc__error" role="alert"></span>').text(i18n.choose_shipping));
                $primeiro = $caixa;
                this.track('error', 'shipping_method');
            }

            if ($primeiro) {
                // Analytics: só o primeiro campo com problema — é onde o
                // cliente travou (e para onde ele é levado). Registrar todos
                // os campos vazios de um clique distorceria o ranking.
                if ($primeiro.hasClass('form-row')) {
                    this.track('error', $primeiro.find(':input').not('[type="hidden"]').first().attr('id') || $primeiro.attr('id'));
                }

                // Se o campo com erro estava escondido pelo modo compacto, mostra.
                $primeiro.closest('.autocep-mc-collapsed').removeClass('autocep-mc-collapsed');

                if (numero !== this.etapaAtual) {
                    this.goTo(numero, false);
                }

                $('html, body').animate({ scrollTop: $primeiro.offset().top - 90 }, 200);
                $primeiro.find(':input:visible').first().trigger('focus');

                return false;
            }

            return true;
        },

        hasShippingChoice: function () {
            var $metodos = this.$root.find('.autocep-mc__summary [name^="shipping_method"]');

            if (!$metodos.length) {
                return false;
            }

            // Um único método vem como input hidden (já escolhido).
            return $metodos.filter('[type="hidden"], :checked, select').length > 0;
        },

        /* ---------- Forma de entrega (etapa 2) ---------- */

        /**
         * Os métodos de entrega do checkout clássico ficam na tabela de
         * resumo (que o WooCommerce redesenha a cada atualização). A etapa 2
         * mostra uma lista espelhada; escolher nela marca o método original,
         * o que dispara o recálculo nativo do WooCommerce.
         */
        refreshShipping: function () {
            var $lista = this.$root.find('.autocep-mc__shipping-list');

            if (!$lista.length) {
                return;
            }

            var $linha   = this.$root.find('.autocep-mc__summary tr.woocommerce-shipping-totals, .autocep-mc__summary tr.shipping').first();
            var $metodos = $linha.find('input.shipping_method');

            $lista.empty();

            if (!$metodos.length) {
                // Sem métodos: mostra a mensagem do próprio WooCommerce
                // (ex.: "Digite seu endereço..." / "Nenhuma opção...").
                var aviso = $.trim($linha.find('td').text());

                if (aviso) {
                    $lista.append($('<p class="autocep-mc__shipping-empty"></p>').text(aviso));
                }

                return;
            }

            $metodos.each(function () {
                var $original = $(this);
                var $rotulo   = $linha.find('label[for="' + $original.attr('id') + '"]');
                var marcado   = 'hidden' === $original.attr('type') || $original.prop('checked');
                var idEspelho = 'autocep-mc-' + $original.attr('id');

                $original.closest('li').toggleClass('autocep-mc-chosen', marcado);

                $lista.append(
                    $('<label class="autocep-mc__option"></label>')
                        .toggleClass('is-selected', marcado)
                        .attr('for', idEspelho)
                        .append($('<input type="radio" name="autocep_mc_shipping">').attr('id', idEspelho).val($original.val()).prop('checked', marcado))
                        .append($('<span class="autocep-mc__option-label"></span>').html($rotulo.html() || $original.val()))
                );
            });
        },

        /* ---------- Pagamento (etapa 3) ---------- */

        paymentType: function (gatewayId) {
            return (params.gateway_types && params.gateway_types[gatewayId]) || 'other';
        },

        /**
         * Marca cada forma de pagamento com o tipo (pix/card/boleto) e mostra
         * a mensagem correspondente ao método escolhido. Reaplicado a cada
         * atualização, porque o WooCommerce substitui o bloco de pagamento.
         */
        decoratePayment: function () {
            var self = this;
            var $pay = this.$root.find('.woocommerce-checkout-payment');

            $pay.find('.wc_payment_methods > li').each(function () {
                var $li = $(this);
                var id  = $li.find('input[name="payment_method"]').val();

                $li.addClass('autocep-mc-pay autocep-mc-pay--' + self.paymentType(id));
            });

            var escolhido = $pay.find('input[name="payment_method"]:checked').val();
            var tipo      = this.paymentType(escolhido);
            var mensagem  = params.pay_messages[tipo] || '';
            var $nota     = this.$root.find('.autocep-mc__pay-note');

            // A nota fica dentro do bloco de pagamento, que o WooCommerce
            // substitui inteiro a cada atualização — recria quando sumir.
            if (!$nota.length) {
                $nota = $('<div class="autocep-mc__pay-note" hidden></div>').prependTo(this.panelBody(3));
            }

            $pay.find('.wc_payment_methods > li').each(function () {
                $(this).toggleClass('is-selected', $(this).find('input[name="payment_method"]').val() === escolhido);
            });

            this.$root.attr('data-payment-type', escolhido ? tipo : '');

            $nota.attr('class', 'autocep-mc__pay-note autocep-mc__pay-note--' + tipo).text(mensagem).prop('hidden', !mensagem);

            // A nota fica logo acima do botão "Finalizar pedido".
            var $finalizar = $pay.find('.place-order');

            if ($finalizar.length && !$nota.next().is($finalizar)) {
                $finalizar.before($nota);
            }
        },

        /* ---------- Resumo ---------- */

        updateSummaryTotal: function () {
            var total = $.trim(this.$root.find('.autocep-mc__summary .order-total td').first().text());

            this.$root.find('.autocep-mc__summary-toggle-total').text(total);
        },

        /* ---------- Modo compacto ---------- */

        /**
         * Esconde campos opcionais vazios atrás de um link "+ adicionar".
         * O complemento não é escondido quando é ele que recebe o bairro do
         * autocompletar de CEP (configuração "Campo do Bairro").
         */
        applyCompactMode: function () {
            var campoBairro = params.neighborhood_field || '';
            var alvos = [
                { id: 'billing_company', texto: i18n.add_company },
                { id: 'billing_address_2', texto: i18n.add_address_2 },
                { id: 'shipping_company', texto: i18n.add_company },
                { id: 'shipping_address_2', texto: i18n.add_address_2 }
            ];

            alvos.forEach(function (alvo) {
                var $linha = $form.find('#' + alvo.id + '_field');
                var bairroAqui = campoBairro === alvo.id
                    || campoBairro.replace(/^billing_/, 'shipping_') === alvo.id;

                if (!$linha.length || bairroAqui || $linha.hasClass('validate-required') || $linha.find(':input').val()) {
                    return;
                }

                collapse($linha, alvo.texto);
            });

            // Observações do pedido: só o campo, para não esconder o que
            // outros plugins (ou o simulador do AutoCEP) colocam nessa área.
            var $notas = $form.find('.woocommerce-additional-fields__field-wrapper');

            if ($notas.length && !$notas.find('textarea').val()) {
                $form.find('.woocommerce-additional-fields > h3').addClass('autocep-mc-hidden-heading');
                collapse($notas, i18n.add_notes);
            }

            function collapse($alvo, texto) {
                $alvo.addClass('autocep-mc-collapsed');

                $('<button type="button" class="autocep-mc__reveal"></button>')
                    .text(texto)
                    .insertBefore($alvo)
                    .on('click', function () {
                        $alvo.removeClass('autocep-mc-collapsed');
                        $(this).remove();
                        $alvo.find(':input:visible').first().trigger('focus');
                    });
            }
        }
    };

    MC.init();
});
