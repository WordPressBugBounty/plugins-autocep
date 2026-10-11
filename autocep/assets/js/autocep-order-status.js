/**
 * AutoCEP — Status do pedido em tempo real + página de obrigado.
 *
 * Roda só na página de pedido recebido e na página do pedido em Minha Conta.
 * Consulta o status do pedido a cada poucos segundos (pausando quando a aba
 * não está visível) até ele chegar a um status final ou o tempo acabar.
 * Quando o status muda, mostra a mensagem configurada; se o pagamento foi
 * aprovado, leva o cliente para a página de obrigado configurada.
 */
jQuery(function ($) {
    'use strict';

    if (typeof autocep_os_params === 'undefined') {
        return;
    }

    var p = autocep_os_params;
    var statusAtual = p.status;
    var inicio = Date.now();
    var timer = null;
    var CHAVE_MOSTRADO = 'autocep_os_mostrado_' + p.order_id;

    function ehPago(status) {
        return p.paid.indexOf(status) !== -1;
    }

    function ehFinal(status) {
        return p.final.indexOf(status) !== -1;
    }

    function tipo(status) {
        if (ehPago(status)) {
            return 'success';
        }

        return 'failed' === status || 'cancelled' === status ? 'error' : 'info';
    }

    function texto(status) {
        var modelo = p.messages[status] || '';

        return modelo
            .replace(/\{pedido\}/g, p.order_number)
            .replace(/\{nome\}/g, p.first_name || '');
    }

    // Lembra (por aba) que a mensagem de "pago" já foi mostrada, para não
    // repetir — nem redirecionar de novo — se o cliente voltar à página.
    function jaMostrado() {
        try {
            return '1' === window.sessionStorage.getItem(CHAVE_MOSTRADO);
        } catch (e) {
            return false;
        }
    }

    function marcarMostrado() {
        try {
            window.sessionStorage.setItem(CHAVE_MOSTRADO, '1');
        } catch (e) {}
    }

    function irParaObrigado() {
        if (p.redirect_url) {
            window.location.href = p.redirect_url;
        } else {
            // Sem página própria: recarrega para a página do pedido mostrar
            // o pagamento aprovado (em vez do QR Code do Pix, por exemplo).
            window.location.reload();
        }
    }

    function fechar() {
        $('.autocep-os-overlay').remove();
        $(document).off('keydown.autocep-os');
    }

    function mostrar(status) {
        var mensagem = texto(status);
        var pago = ehPago(status);

        if (!mensagem && !(pago && p.redirect_url)) {
            return;
        }

        if (pago) {
            marcarMostrado();
        }

        fechar();

        var $botoes = $('<div class="autocep-os-actions"></div>');
        var $caixa = $('<div class="autocep-os-modal" role="alertdialog" aria-modal="true" aria-labelledby="autocep-os-msg"></div>')
            .addClass('autocep-os-modal--' + tipo(status))
            .append('<div class="autocep-os-icon" aria-hidden="true"></div>')
            .append($('<p id="autocep-os-msg" class="autocep-os-text"></p>').text(mensagem))
            .append($botoes);

        var $overlay = $('<div class="autocep-os-overlay"></div>').append($caixa).appendTo('body');

        if (pago && p.redirect_url) {
            var restante = Math.max(0, parseInt(p.redirect_delay, 10) || 0);
            var $contagem = $('<p class="autocep-os-countdown" aria-live="polite"></p>');

            $botoes.before($contagem);
            $botoes.append($('<button type="button" class="autocep-os-btn"></button>').text(p.i18n.go_now).on('click', irParaObrigado));

            var contar = function () {
                if (restante <= 0) {
                    irParaObrigado();
                    return;
                }

                $contagem.text(p.i18n.redirecting.replace('%d', restante));
                restante--;
                setTimeout(contar, 1000);
            };

            contar();
        } else {
            $botoes.append($('<button type="button" class="autocep-os-btn"></button>').text(p.i18n.ok).on('click', function () {
                fechar();

                if (pago) {
                    irParaObrigado();
                }
            }));

            $(document).on('keydown.autocep-os', function (evento) {
                if (27 === evento.which) {
                    fechar();
                }
            });
        }

        $overlay.find('.autocep-os-btn').first().trigger('focus');
    }

    function consultar() {
        // Aba em segundo plano: não consulta (economiza servidor).
        if (document.hidden) {
            agendar();
            return;
        }

        $.post(p.ajax_url, { action: 'autocep_order_status', order_id: p.order_id, key: p.key }, null, 'json')
            .done(function (resposta) {
                if (!resposta || !resposta.success) {
                    return;
                }

                var novo = resposta.data.status;

                if (novo && novo !== statusAtual) {
                    statusAtual = novo;
                    mostrar(novo);
                }
            })
            .always(function () {
                if (!ehFinal(statusAtual)) {
                    agendar();
                }
            });
    }

    function agendar() {
        if (Date.now() - inicio > p.max_minutes * 60 * 1000) {
            return;
        }

        clearTimeout(timer);
        timer = setTimeout(consultar, p.interval);
    }

    // Pedido que já chega aprovado (ex.: cartão aprovado na hora).
    if (ehPago(statusAtual)) {
        if (p.paid_on_load && !jaMostrado()) {
            mostrar(statusAtual);
        }

        return;
    }

    if (!ehFinal(statusAtual)) {
        agendar();
    }
});
