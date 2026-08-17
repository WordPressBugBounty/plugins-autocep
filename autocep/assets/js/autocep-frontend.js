/**
 * AutoCEP - Front-end
 *
 * Responsável por:
 *  - Aplicar máscara dinâmica no campo de CEP (00000-000).
 *  - Autocompletar o endereço no Checkout do WooCommerce (Cobrança/Entrega).
 *  - Exibir e processar os simuladores de frete da página de produto e do checkout.
 *
 * Todas as opções configuradas no painel administrativo chegam aqui via
 * `wp_localize_script` no objeto global `autocep_params`.
 */
jQuery(function ($) {
    'use strict';

    if (typeof autocep_params === 'undefined') {
        return;
    }

    var params = autocep_params;

    /* -----------------------------------------------------------------
     * Utilitários
     * ------------------------------------------------------------- */

    /**
     * Aplica a máscara 00000-000 em um campo de CEP.
     *
     * @param {jQuery} $field Campo de input.
     */
    function applyCepMask($field) {
        $field.off('input.autocep-mask').on('input.autocep-mask', function () {
            var valor = $(this).val().replace(/\D/g, '').slice(0, 8);

            if (valor.length > 5) {
                valor = valor.replace(/^(\d{5})(\d{0,3})/, '$1-$2');
            }

            $(this).val(valor);
        });
    }

    /**
     * Bloqueia visualmente um elemento (overlay), usado como spinner de
     * carregamento durante as requisições AJAX.
     *
     * @param {jQuery} $el Elemento a bloquear.
     */
    function blockElement($el) {
        if (!$el.length) {
            return;
        }

        if (typeof $el.block === 'function') {
            $el.block({
                message: null,
                overlayCSS: { background: '#fff', opacity: 0.6 },
            });
        } else {
            $el.addClass('autocep-loading');
        }
    }

    /**
     * Remove o bloqueio visual aplicado por blockElement().
     *
     * @param {jQuery} $el Elemento a desbloquear.
     */
    function unblockElement($el) {
        if (!$el.length) {
            return;
        }

        if (typeof $el.unblock === 'function') {
            $el.unblock();
        } else {
            $el.removeClass('autocep-loading');
        }
    }

    /**
     * Alterna o estado (habilitado/bloqueado) do botão "Finalizar Compra"
     * do checkout, quando a opção correspondente está ativa.
     *
     * @param {boolean} bloquear Define se o botão deve ser bloqueado.
     */
    function toggleCheckoutButton(bloquear) {
        if (!params.messages || !params.messages.block_checkout_button) {
            return;
        }

        var $botao = $('#place_order');

        if (!$botao.length) {
            return;
        }

        $botao.prop('disabled', !!bloquear);
    }

    /**
     * Aplica os atributos HTML5 de "autocomplete" nos campos de endereço
     * do checkout (cobrança/entrega), para que o navegador ofereça
     * sugestões de preenchimento com base no histórico do cliente
     * enquanto ele digita. Controlado pela opção "Autocompletar do
     * Navegador" em AutoCEP > Geral & Busca de CEP.
     */
    function applyBrowserAutocomplete() {
        if (!params.general || !params.general.browser_autocomplete) {
            return;
        }

        var mapaCampos = {
            address_1: 'address-line1',
            address_2: 'address-line2',
            city: 'address-level2',
            state: 'address-level1',
            postcode: 'postal-code',
            country: 'country',
        };

        ['billing', 'shipping'].forEach(function (secao) {
            Object.keys(mapaCampos).forEach(function (campo) {
                var $campo = $('#' + secao + '_' + campo);

                if ($campo.length) {
                    $campo.attr('autocomplete', mapaCampos[campo]);
                }
            });
        });
    }

    /* -----------------------------------------------------------------
     * Módulo: Autocompletar endereço no Checkout (comportamento legado)
     * ------------------------------------------------------------- */
    var AutoCEPCheckout = {
        selectors: {
            checkoutForm: 'form.checkout',
        },

        debounceTimer: null,

        // Intervalo mínimo (ms) entre tentativas automáticas de busca para
        // a mesma seção. Evita o loop de requisições (ex.: o checkout
        // disparando "updated_checkout" repetidamente), mas — diferente de
        // um bloqueio permanente — permite tentar de novo depois desse
        // intervalo, o que é necessário em checkouts com etapas editáveis:
        // ao reabrir a seção de endereço para alterar o CEP, o campo
        // "Endereço" pode voltar a ficar vazio e precisa ser preenchido de novo.
        MIN_RETRY_INTERVAL_MS: 3000,

        // Controle por seção (billing/shipping): "fetching" impede
        // disparar uma nova requisição enquanto outra já está em
        // andamento; "lastCep" identifica se o CEP mudou (para liberar
        // uma nova tentativa imediatamente); "lastAttemptAt" é usado
        // para o cooldown das tentativas automáticas.
        state: {
            billing: { fetching: false, lastCep: '', lastAttemptAt: 0 },
            shipping: { fetching: false, lastCep: '', lastAttemptAt: 0 },
        },

        init: function () {
            if (!params.is_checkout) {
                return;
            }

            if (!params.general || (!params.general.enable_billing && !params.general.enable_shipping)) {
                return;
            }

            this.bindEvents();
            this.autoFillIfNeeded();

            // Alguns temas/plugins de checkout (Fluid Checkout, CartFlows)
            // renderizam os campos com um pequeno atraso; tentamos de novo.
            setTimeout(this.autoFillIfNeeded.bind(this), 800);
        },

        sectionEnabled: function (section) {
            return 'billing' === section ? !!params.general.enable_billing : !!params.general.enable_shipping;
        },

        bindEvents: function () {
            var self = this;

            $(document.body).off('input.autocep');
            $(document.body).off('updated_checkout.autocep wc_fragments_loaded.autocep');

            $(document.body).on('input.autocep', '#billing_postcode, #shipping_postcode', function () {
                clearTimeout(self.debounceTimer);

                var section = this.id.indexOf('billing') !== -1 ? 'billing' : 'shipping';

                if (!self.sectionEnabled(section)) {
                    return;
                }

                // O CEP mudou (ou o campo foi reaberto/editado): libera a
                // busca imediatamente, sem esperar o cooldown.
                var cepDigitado = $(this).val().replace(/\D/g, '');
                var estado = self.state[section];

                if (cepDigitado !== estado.lastCep) {
                    estado.lastAttemptAt = 0;
                }

                self.debounceTimer = setTimeout(function () {
                    self.fillAddress(section);
                }, 500);
            });

            $(document.body).on('updated_checkout.autocep wc_fragments_loaded.autocep', function () {
                self.autoFillIfNeeded();
            });
        },

        autoFillIfNeeded: function () {
            var self = this;
            var agora = Date.now();

            ['billing', 'shipping'].forEach(function (section) {
                if (!self.sectionEnabled(section)) {
                    return;
                }

                var $postcode = $('#' + section + '_postcode');
                var $address1 = $('#' + section + '_address_1');

                if (!$postcode.length) {
                    return;
                }

                var cep = $postcode.val().replace(/\D/g, '');

                if (8 !== cep.length) {
                    return;
                }

                // O campo de endereço já está preenchido: nada a fazer.
                if ($address1.val()) {
                    return;
                }

                var estado = self.state[section];

                // Evita reagir em cascata a eventos automáticos do
                // checkout disparados em sequência rápida (loop), mas sem
                // bloquear permanentemente: passado o intervalo mínimo,
                // uma nova tentativa é permitida — essencial para quando o
                // cliente reabre a seção de endereço para editar o CEP e o
                // campo "Endereço" volta a ficar vazio.
                if (estado.fetching || (agora - estado.lastAttemptAt) < self.MIN_RETRY_INTERVAL_MS) {
                    return;
                }

                self.fillAddress(section);
            });
        },

        fillAddress: function (section, callback) {
            var chamarCallback = function () {
                if ('function' === typeof callback) {
                    callback();
                }
            };

            var $country = $('#' + section + '_country');

            if ($country.length && $country.val() !== 'BR') {
                chamarCallback();
                return;
            }

            var $postcodeField = $('#' + section + '_postcode');
            var cep = $postcodeField.val().replace(/\D/g, '');

            // Limpa qualquer aviso de tentativa anterior sempre que uma
            // nova tentativa começa.
            this.showCepFeedback(section, '');

            if (cep.length !== 8) {
                // CEP incompleto/malformado: avisa apenas se já havia algo
                // digitado (evita mostrar erro enquanto o campo está vazio).
                if (cep.length > 0) {
                    this.showCepFeedback(section, params.messages.invalid_cep);
                }

                chamarCallback();
                return;
            }

            var estado = this.state[section];

            // Evita apenas disparar uma nova requisição enquanto outra já
            // está em andamento para a mesma seção — buscas explícitas
            // (usuário digitando ou reabrindo a seção) sempre são permitidas.
            if (estado.fetching) {
                chamarCallback();
                return;
            }

            estado.fetching = true;
            estado.lastCep = cep;
            estado.lastAttemptAt = Date.now();

            // Bloqueia apenas os campos da seção sendo preenchida (não o
            // checkout inteiro), para não travar outras etapas do
            // formulário (ex.: método de entrega, notas do pedido) caso
            // a requisição demore.
            var $escopo = $postcodeField.closest('.woocommerce-billing-fields, .woocommerce-shipping-fields, .woocommerce-additional-fields');

            if (!$escopo.length) {
                $escopo = $(this.selectors.checkoutForm);
            }

            blockElement($escopo);
            toggleCheckoutButton(true);
            $postcodeField.blur();

            var self = this;

            $.ajax({
                type: 'POST',
                url: params.ajax_url,
                dataType: 'json',
                timeout: 15000, // Evita que a requisição fique "pendurada" indefinidamente em caso de instabilidade de rede.
                data: {
                    action: 'autocep_get_address',
                    cep: cep,
                    nonce: params.nonce,
                },
            })
                .done(function (response) {
                    if (!response || !response.success) {
                        // Antes, uma falha aqui (CEP não encontrado, API fora
                        // do ar etc.) ficava completamente silenciosa para o
                        // cliente. Agora mostramos a mensagem retornada pelo
                        // servidor (ou a mensagem padrão de "não encontrado").
                        self.showCepFeedback(section, (response && response.data && response.data.message) || params.messages.not_found);
                        return;
                    }

                    self.populateFields(section, response.data);
                })
                .fail(function () {
                    self.showCepFeedback(section, params.messages.generic_error);
                })
                .always(function () {
                    estado.fetching = false;
                    unblockElement($escopo);
                    toggleCheckoutButton(false);
                    chamarCallback();
                });
        },

        /**
         * Mostra ou remove uma mensagem de feedback (erro/aviso) logo
         * abaixo do campo de CEP da seção informada — usado tanto para
         * "CEP inválido" quanto "CEP não encontrado", que antes falhavam
         * silenciosamente sem nenhuma indicação visual para o cliente.
         *
         * @param {string} section   "billing" ou "shipping".
         * @param {string} mensagem  Texto a exibir, ou string vazia para remover.
         */
        showCepFeedback: function (section, mensagem) {
            var $postcode = $('#' + section + '_postcode');

            if (!$postcode.length) {
                return;
            }

            var noticeClass = 'autocep-cep-feedback-notice';
            var $wrapper = $postcode.closest('.form-row, p');

            if (!$wrapper.length) {
                $wrapper = $postcode.parent();
            }

            var $existente = $wrapper.find('.' + noticeClass);

            if (!mensagem) {
                $existente.remove();
                return;
            }

            if ($existente.length) {
                $existente.text(mensagem);
                return;
            }

            $('<span class="' + noticeClass + '" role="alert"></span>').text(mensagem).appendTo($wrapper);
        },

        populateFields: function (section, data) {
            var $address1 = $('#' + section + '_address_1');
            $address1.val(data.logradouro).change();

            var neighborhoodField = params.general.neighborhood_field || (section + '_address_2');

            // O campo de bairro configurado no admin normalmente segue o
            // padrão "billing_xxx"; adaptamos o prefixo para "shipping_"
            // quando estivermos preenchendo a seção de entrega.
            var targetField = neighborhoodField.replace(/^billing_/, section + '_').replace(/^shipping_/, section + '_');

            var $neighborhood = $('#' + targetField);
            (($neighborhood.length ? $neighborhood : $('#' + section + '_address_2')))
                .val(data.bairro)
                .change();

            $('#' + section + '_city').val(data.localidade).change();
            $('#' + section + '_state').val(data.uf).trigger('change');

            // Alguns CEPs são válidos mas não possuem uma rua específica
            // cadastrada (comum em cidades pequenas) — cidade e estado
            // são preenchidos normalmente, mas o campo "Endereço" fica
            // vazio. Nesse caso, avisamos o cliente em vez de deixar o
            // campo sem explicação, e focamos nele para preenchimento manual.
            this.toggleNoStreetNotice(section, $address1, !data.logradouro);

            if (!data.logradouro) {
                $address1.focus();
                return;
            }

            if (params.general.auto_focus) {
                var $numero = $('#' + section + '_number');

                if ($numero.length) {
                    $numero.focus();
                }
            }
        },

        /**
         * Mostra ou remove o aviso de "CEP sem rua cadastrada" logo
         * abaixo do campo de endereço da seção informada.
         *
         * @param {string} section  "billing" ou "shipping".
         * @param {jQuery} $address1 O campo de endereço (rua) da seção.
         * @param {boolean} mostrar Se o aviso deve ser exibido.
         */
        toggleNoStreetNotice: function (section, $address1, mostrar) {
            var noticeClass = 'autocep-no-street-notice';
            var $existente = $address1.closest('.form-row, p').find('.' + noticeClass);

            if (!mostrar) {
                $existente.remove();
                return;
            }

            if ($existente.length) {
                return;
            }

            var $aviso = $('<span class="' + noticeClass + '" role="alert"></span>').text(params.messages.no_street);

            $address1.closest('.form-row, p').append($aviso);
        },
    };

    /* -----------------------------------------------------------------
     * Módulo: Simulador de Frete (Produto e Checkout)
     * ------------------------------------------------------------- */
    var AutoCEPShipping = {
        init: function () {
            this.bindProductBox();
            this.bindCartBox();
            this.bindCheckoutBox();
        },

        bindProductBox: function () {
            if (!params.is_product || !params.product_shipping || !params.product_shipping.enabled) {
                return;
            }

            var self = this;
            var $box = $('.autocep-shipping-box--product');

            if (!$box.length) {
                return;
            }

            applyCepMask($box.find('.autocep-shipping-cep-input'));

            $box.on('click', '.autocep-shipping-calc-btn', function () {
                self.calculateProductShipping($box);
            });

            $box.on('keypress', '.autocep-shipping-cep-input', function (e) {
                if (13 === e.which) {
                    e.preventDefault();
                    self.calculateProductShipping($box);
                }
            });
        },

        /**
         * Detecta se o produto atual é variável e se uma variação válida
         * já foi selecionada pelo cliente (campo oculto "variation_id"
         * preenchido pelo próprio WooCommerce após a escolha dos atributos).
         *
         * @returns {number} O ID da variação selecionada, ou 0 se não houver.
         */
        detectSelectedVariation: function () {
            var $variationInput = $('form.cart input[name="variation_id"]');

            if ($variationInput.length && parseInt($variationInput.val(), 10) > 0) {
                return parseInt($variationInput.val(), 10);
            }

            return 0;
        },

        calculateProductShipping: function ($box) {
            var cep = $box.find('.autocep-shipping-cep-input').val().replace(/\D/g, '');
            var $feedback = $box.find('.autocep-shipping-feedback');
            var $results = $box.find('.autocep-shipping-results');

            if (8 !== cep.length) {
                $feedback.text(params.messages.invalid_cep);
                return;
            }

            var productId = $box.data('product-id');
            var variationId = this.detectSelectedVariation();

            // Produto variável ainda sem variação selecionada: avisa o
            // cliente em vez de calcular o frete de um produto "vazio".
            if ($('form.cart input[name="variation_id"]').length && !variationId) {
                $feedback.text(params.messages.select_variation);
                return;
            }

            var quantidade = 1;

            if (params.product_shipping.consider_quantity) {
                var $qtyInput = $('form.cart input.qty, form.cart input[name="quantity"]').first();

                if ($qtyInput.length && parseInt($qtyInput.val(), 10) > 0) {
                    quantidade = parseInt($qtyInput.val(), 10);
                }
            }

            $feedback.text(params.messages.calculating);
            $results.empty();
            blockElement($box);

            $.ajax({
                type: 'POST',
                url: params.ajax_url,
                dataType: 'json',
                data: {
                    action: 'autocep_calc_shipping_product',
                    cep: cep,
                    product_id: productId,
                    variation_id: variationId,
                    quantity: quantidade,
                    nonce: params.nonce,
                },
            })
                .done(function (response) {
                    if (!response || !response.success) {
                        $feedback.text((response && response.data && response.data.message) || params.messages.generic_error);
                        return;
                    }

                    $feedback.empty();
                    renderRates($results, response.data.rates);
                })
                .fail(function () {
                    $feedback.text(params.messages.generic_error);
                })
                .always(function () {
                    unblockElement($box);
                });
        },

        bindCartBox: function () {
            if (!params.is_cart || !params.cart_shipping || !params.cart_shipping.enabled) {
                return;
            }

            var self = this;
            var $box = $('.autocep-shipping-box--cart');

            if (!$box.length) {
                return;
            }

            applyCepMask($box.find('.autocep-shipping-cep-input'));

            $box.on('click', '.autocep-shipping-calc-btn', function () {
                self.calculateCartBasedShipping($box, 'autocep_calc_shipping_cart', 'cart');
            });

            $box.on('keypress', '.autocep-shipping-cep-input', function (e) {
                if (13 === e.which) {
                    e.preventDefault();
                    self.calculateCartBasedShipping($box, 'autocep_calc_shipping_cart', 'cart');
                }
            });
        },

        bindCheckoutBox: function () {
            if (!params.is_checkout || !params.checkout_shipping || !params.checkout_shipping.enabled) {
                return;
            }

            var self = this;
            var $box = $('.autocep-shipping-box--checkout');

            if (!$box.length) {
                return;
            }

            applyCepMask($box.find('.autocep-shipping-cep-input'));

            $box.on('click', '.autocep-shipping-calc-btn', function () {
                self.calculateCartBasedShipping($box, 'autocep_calc_shipping_checkout', 'checkout');
            });

            $box.on('keypress', '.autocep-shipping-cep-input', function (e) {
                if (13 === e.which) {
                    e.preventDefault();
                    self.calculateCartBasedShipping($box, 'autocep_calc_shipping_checkout', 'checkout');
                }
            });
        },

        /**
         * Calcula o frete a partir do conteúdo atual do carrinho —
         * lógica compartilhada entre a caixa da página do carrinho e a
         * caixa do checkout, que diferem apenas na ação AJAX usada e em
         * bloquear (ou não) o botão "Finalizar Compra" durante o cálculo.
         *
         * As taxas retornadas são exibidas com um botão "Selecionar" —
         * clicar nele aplica de verdade aquele método ao pedido (não é
         * apenas informativo), através de selectRate().
         *
         * @param {jQuery} $box       A caixa de simulação (carrinho ou checkout).
         * @param {string} ajaxAction Nome da ação AJAX a ser chamada para calcular.
         * @param {string} contexto   "cart" ou "checkout".
         */
        calculateCartBasedShipping: function ($box, ajaxAction, contexto) {
            var cep = $box.find('.autocep-shipping-cep-input').val().replace(/\D/g, '');
            var $feedback = $box.find('.autocep-shipping-feedback');
            var $results = $box.find('.autocep-shipping-results');
            var afetaBotaoFinalizar = 'checkout' === contexto;
            var self = this;

            if (8 !== cep.length) {
                $feedback.text(params.messages.invalid_cep);
                return;
            }

            $feedback.text(params.messages.calculating);
            $results.empty();
            blockElement($box);

            if (afetaBotaoFinalizar) {
                toggleCheckoutButton(true);
            }

            $.ajax({
                type: 'POST',
                url: params.ajax_url,
                dataType: 'json',
                data: {
                    action: ajaxAction,
                    cep: cep,
                    nonce: params.nonce,
                },
            })
                .done(function (response) {
                    if (!response || !response.success) {
                        $feedback.text((response && response.data && response.data.message) || params.messages.generic_error);
                        return;
                    }

                    $feedback.empty();
                    renderRates($results, response.data.rates, {
                        selectable: true,
                        onSelect: function (rateId) {
                            self.selectRate($box, cep, rateId, contexto);
                        },
                    });
                })
                .fail(function () {
                    $feedback.text(params.messages.generic_error);
                })
                .always(function () {
                    unblockElement($box);

                    if (afetaBotaoFinalizar) {
                        toggleCheckoutButton(false);
                    }
                });
        },

        /**
         * Aplica de fato uma taxa de frete simulada como o método
         * escolhido para o pedido, chamando a ação AJAX
         * "autocep_select_shipping_rate" (que usa o mecanismo nativo de
         * sessão do WooCommerce). No checkout, dispara o evento nativo
         * "update_checkout" para que a etapa "Método de Entrega" se
         * atualize sozinha já mostrando a opção escolhida. No carrinho,
         * recarrega a página, para garantir que a seção de totais
         * nativa reflita a nova escolha independentemente do tema.
         *
         * @param {jQuery} $box     A caixa de simulação.
         * @param {string} cep      CEP usado na simulação.
         * @param {string} rateId   ID da taxa de frete escolhida.
         * @param {string} contexto "cart" ou "checkout".
         */
        selectRate: function ($box, cep, rateId, contexto) {
            var $feedback = $box.find('.autocep-shipping-feedback');

            $feedback.text(params.messages.selecting_rate);
            blockElement($box);

            $.ajax({
                type: 'POST',
                url: params.ajax_url,
                dataType: 'json',
                data: {
                    action: 'autocep_select_shipping_rate',
                    cep: cep,
                    rate_id: rateId,
                    context: contexto,
                    nonce: params.nonce,
                },
            })
                .done(function (response) {
                    if (!response || !response.success) {
                        $feedback.text((response && response.data && response.data.message) || params.messages.generic_error);
                        return;
                    }

                    $feedback.text(params.messages.rate_selected);

                    if ('checkout' === contexto) {
                        var jaRecalculou = false;

                        var recalcularCheckout = function () {
                            if (jaRecalculou) {
                                return;
                            }

                            jaRecalculou = true;

                            // Marca o rádio nativo de "Método de Entrega"
                            // correspondente a este frete, se ele já
                            // estiver renderizado na página. Sem isso, ao
                            // recalcular, o próprio WooCommerce reenvia o
                            // valor do rádio que ainda está marcado na tela
                            // (o antigo) e sobrescreve nossa seleção — por
                            // isso a mensagem de sucesso aparecia, mas o
                            // "Método de Entrega" continuava mostrando a
                            // opção anterior.
                            var $radioCorrespondente = $('input[name^="shipping_method"][value="' + rateId + '"]');

                            if ($radioCorrespondente.length) {
                                $radioCorrespondente.prop('checked', true);
                            }

                            $(document.body).trigger('update_checkout');
                        };

                        // Importante: sincroniza o CEP e o endereço completo
                        // (rua/bairro/cidade/estado) usados na simulação com
                        // os campos reais do formulário ANTES de pedir ao
                        // WooCommerce para recalcular. Sem isso, o recálculo
                        // nativo usa os dados que já estavam no formulário
                        // (que podem ser diferentes dos simulados), fazendo
                        // o frete voltar a mudar e parecer que a seleção não
                        // "pegou". Espera a sincronização terminar de
                        // verdade (em vez de um tempo fixo) antes de
                        // recalcular; um limite de segurança evita travar
                        // indefinidamente caso algo dê errado.
                        AutoCEPShipping.syncCheckoutAddressFields(cep, recalcularCheckout);
                        setTimeout(recalcularCheckout, 4000);
                    } else {
                        // Recarrega a página do carrinho para que a seção
                        // nativa de totais/frete (que varia de tema para
                        // tema) reflita a escolha de forma confiável.
                        setTimeout(function () {
                            window.location.reload();
                        }, 700);
                    }
                })
                .fail(function () {
                    $feedback.text(params.messages.generic_error);
                })
                .always(function () {
                    unblockElement($box);
                });
        },

        /**
         * Sincroniza o CEP escolhido na caixa "Simular Frete" do checkout
         * com os campos reais de endereço (cobrança/entrega), para que o
         * recálculo nativo do WooCommerce utilize o mesmo CEP que foi de
         * fato selecionado — evitando que o frete aplicado divirja do
         * que foi mostrado na simulação.
         *
         * @param {string} cep CEP selecionado na simulação (apenas dígitos).
         */
        syncCheckoutAddressFields: function (cep, callback) {
            var cepFormatado = cep.length === 8 ? cep.replace(/^(\d{5})(\d{3})$/, '$1-$2') : cep;
            var secoes = ['billing', 'shipping'].filter(function (section) {
                return $('#' + section + '_postcode').length > 0;
            });

            var pendentes = secoes.length;
            var callbackDisparado = false;

            var concluirSecao = function () {
                pendentes -= 1;

                if (pendentes <= 0 && !callbackDisparado) {
                    callbackDisparado = true;

                    if ('function' === typeof callback) {
                        callback();
                    }
                }
            };

            if (0 === secoes.length) {
                concluirSecao();

                return;
            }

            secoes.forEach(function (section) {
                var $postcode = $('#' + section + '_postcode');

                $postcode.val(cepFormatado).trigger('change');

                // Diferente do autocompletar "enquanto digita" (que
                // respeita as opções Autocompletar Cobrança/Entrega do
                // painel), esta sincronização acontece por uma ação
                // explícita do cliente — ele clicou em "Selecionar" na
                // simulação. Por isso sempre atualiza o endereço completo
                // (rua/bairro/cidade/estado) para bater com o CEP
                // escolhido, mesmo que o autocompletar automático esteja
                // desligado nas configurações; caso contrário, o CEP
                // muda mas cidade/estado ficam com os dados antigos,
                // dando a impressão de que a seleção não sincronizou tudo.
                if ('undefined' !== typeof AutoCEPCheckout) {
                    AutoCEPCheckout.fillAddress(section, concluirSecao);
                } else {
                    concluirSecao();
                }
            });
        },
    };

    /**
     * Renderiza a lista de taxas de frete retornadas pelo servidor.
     *
     * @param {jQuery}  $container Elemento onde a lista será inserida.
     * @param {Array}   rates      Lista de taxas normalizadas.
     * @param {Object}  [opcoes]   Opções extras.
     * @param {boolean} [opcoes.selectable] Se cada taxa deve exibir um botão "Selecionar".
     * @param {Function} [opcoes.onSelect]  Callback chamado com o ID da taxa escolhida.
     */
    function renderRates($container, rates, opcoes) {
        opcoes = opcoes || {};

        if (!rates || !rates.length) {
            $container.html('<p class="autocep-shipping-empty">' + params.messages.not_found + '</p>');
            return;
        }

        var $lista = $('<ul class="autocep-shipping-rates-list" />');

        rates.forEach(function (rate) {
            var $item = $('<li class="autocep-shipping-rate-item" />');
            var $info = $('<span class="autocep-shipping-rate-info" />');
            $info.append($('<span class="autocep-shipping-rate-label" />').text(rate.label));
            $info.append($('<span class="autocep-shipping-rate-cost" />').html(rate.cost_formatted));
            $item.append($info);

            if (opcoes.selectable) {
                $item.append(
                    $('<button type="button" class="autocep-shipping-select-btn" />')
                        .text(params.messages.select_rate)
                        .attr('data-rate-id', rate.id)
                );
            }

            $lista.append($item);
        });

        $container.empty().append($lista);

        if (opcoes.selectable && 'function' === typeof opcoes.onSelect) {
            $container.off('click.autocep-select').on('click.autocep-select', '.autocep-shipping-select-btn', function () {
                opcoes.onSelect($(this).attr('data-rate-id'));
            });
        }
    }

    /* -----------------------------------------------------------------
     * Inicialização
     * ------------------------------------------------------------- */

    // Máscara do CEP nos campos padrão do Checkout do WooCommerce.
    applyCepMask($('#billing_postcode, #shipping_postcode'));
    $(document.body).on('updated_checkout wc_fragments_loaded', function () {
        applyCepMask($('#billing_postcode, #shipping_postcode'));
    });

    // Sugestões automáticas do navegador (autocomplete) nos campos de
    // endereço do checkout, se a opção estiver habilitada no painel.
    if (params.is_checkout) {
        applyBrowserAutocomplete();
        $(document.body).on('updated_checkout wc_fragments_loaded', applyBrowserAutocomplete);
    }

    AutoCEPCheckout.init();
    AutoCEPShipping.init();

    /**
     * Compatibilidade com checkouts de terceiros (Fluid Checkout,
     * CartFlows, Elementor Pro, etc.).
     *
     * Esses construtores costumam reorganizar os campos em etapas e nem
     * sempre disparam os eventos nativos do WooCommerce ("updated_checkout",
     * "wc_fragments_loaded") ao trocar de etapa ou revelar campos que
     * antes estavam ocultos. Em vez de depender de eventos específicos de
     * cada plugin (o que exigiria manter uma lista de compatibilidade),
     * observamos diretamente mudanças no HTML do formulário de checkout e
     * reaplicamos a máscara de CEP, o autocomplete do navegador e a
     * verificação de autopreenchimento sempre que algo mudar — funciona
     * de forma agnóstica com qualquer checkout construído sobre o
     * formulário padrão do WooCommerce.
     */
    if (params.is_checkout && window.MutationObserver) {
        var alvoObservado = document.querySelector('form.checkout') || document.body;
        var timerReaplicacao = null;

        var observador = new MutationObserver(function () {
            // Debounce: várias mutações costumam ocorrer em sequência
            // (ex.: uma etapa inteira sendo desenhada de uma vez); espera
            // a "poeira baixar" antes de reagir, evitando trabalho repetido.
            clearTimeout(timerReaplicacao);

            timerReaplicacao = setTimeout(function () {
                applyCepMask($('#billing_postcode, #shipping_postcode'));
                applyBrowserAutocomplete();
                AutoCEPCheckout.autoFillIfNeeded();
            }, 300);
        });

        observador.observe(alvoObservado, { childList: true, subtree: true });
    }
});
