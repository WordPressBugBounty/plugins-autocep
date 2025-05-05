jQuery(function ($) {
  var AECEP = {
    initialize: function () {
      var self = this;
      self.bindEvents();
      self.initializeAddressFields();
    },

    bindEvents: function () {
      var self = this;

      $(document.body).on(
        'input',
        '#billing_postcode, #shipping_postcode',
        function () {
          var section = $(this).attr('id').includes('billing')
            ? 'billing'
            : 'shipping';
          self.fillAddress(section);
        },
      );

      $(document).on('updated_checkout', function () {
        self.bindEvents();
      });
    },

    initializeAddressFields: function () {
      var self = this;
      var $billingPostcode = $('#billing_postcode');
      var $shippingPostcode = $('#shipping_postcode');
      var $billingAddress1 = $('#billing_address_1');
      var $shippingAddress1 = $('#shipping_address_1');

      if ($billingPostcode.val() && !$billingAddress1.val()) {
        self.fillAddress('billing');
      }
      if ($shippingPostcode.val() && !$shippingAddress1.val()) {
        self.fillAddress('shipping');
      }
    },

    showLoading: function () {
      var $form = $('form.checkout');
      if ($form.length) {
        $form.block({
          message: null,
          overlayCSS: {
            background: '#fff',
            opacity: 0.6,
          },
        });
      } else {
        console.error('O formulário de checkout não foi encontrado.');
      }
    },

    hideLoading: function () {
      var $form = $('form.checkout');
      if ($form.length) {
        $form.unblock();
      } else {
        console.error('O formulário de checkout não foi encontrado.');
      }
    },

    fillAddress: function (section, applyToBoth) {
      var self = this;
      applyToBoth = applyToBoth || false;

      var $countryField = $('#' + section + '_country');
      var country = $countryField.val();

      if (!$countryField.length || country === 'BR') {
        var $postalCodeField = $('#' + section + '_postcode');
        var postalCode = $postalCodeField.val().replace(/\D/g, '');

        if (postalCode && postalCode.length === 8) {
          $postalCodeField.blur();
          self.showLoading();

          $.ajax({
            type: 'POST',
            url: autocep_params.ajax_url,
            data: {
              action: 'autocep_get_address',
              cep: postalCode,
              nonce: autocep_params.nonce,
            },
            dataType: 'json',
            success: function (response) {
              if (response.success) {
                self.populateFields(section, response.data);
                if (applyToBoth) {
                  var otherSection =
                    section === 'billing' ? 'shipping' : 'billing';
                  self.populateFields(otherSection, response.data);
                }
              } else {
                console.warn(
                  response.data.message ||
                    'Erro desconhecido ao buscar o endereço.',
                );
              }
            },
            error: function (jqXHR, textStatus, errorThrown) {
              console.error(
                'Erro ao buscar o endereço. Status: ' +
                  textStatus +
                  ', Erro: ' +
                  errorThrown,
              );
            },
            complete: function () {
              self.hideLoading();
            },
          });
        }
      }
    },

    populateFields: function (section, data) {
      $('#' + section + '_address_1')
        .val(data.logradouro)
        .change();
      $('#' + section + '_neighborhood').length
        ? $('#' + section + '_neighborhood')
            .val(data.bairro)
            .change()
        : $('#' + section + '_address_2')
            .val(data.bairro)
            .change();
      $('#' + section + '_city')
        .val(data.localidade)
        .change();
      $('#' + section + '_state')
        .val(data.uf)
        .trigger('change')
        .change();
    },
  };

  AECEP.initialize();
});
