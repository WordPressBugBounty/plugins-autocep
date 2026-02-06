jQuery(function ($) {
  const AECEP = {
    selectors: {
      checkoutForm: "form.checkout",
      billing: {
        postcode: "#billing_postcode",
        address1: "#billing_address_1",
      },
      shipping: {
        postcode: "#shipping_postcode",
        address1: "#shipping_address_1",
      },
    },

    debounceTimer: null,

    init() {
      this.bindEvents();
      this.autoFillIfNeeded();

      setTimeout(() => {
        this.autoFillIfNeeded();
      }, 800);
    },

    bindEvents() {
      const self = this;

      $(document.body).off("input.autocep");
      $(document).off("updated_checkout.autocep");

      $(document.body).on(
        "input.autocep",
        "#billing_postcode, #shipping_postcode",
        function () {
          clearTimeout(self.debounceTimer);

          const section = this.id.includes("billing")
            ? "billing"
            : "shipping";

          self.debounceTimer = setTimeout(() => {
            self.fillAddress(section);
          }, 500);
        }
      );

      $(document).on("updated_checkout.autocep", () => {
        self.autoFillIfNeeded();
      });
    },

    autoFillIfNeeded() {
      ["billing", "shipping"].forEach((section) => {
        const $postcode = $(this.selectors[section].postcode);
        const $address1 = $(this.selectors[section].address1);

        if (
          $postcode.length &&
          $postcode.val() &&
          !$address1.val()
        ) {
          this.fillAddress(section);
        }
      });
    },

    showLoading() {
      const $form = $(this.selectors.checkoutForm);
      if ($form.length) {
        $form.block({
          message: null,
          overlayCSS: {
            background: "#fff",
            opacity: 0.6,
          },
        });
      }
    },

    hideLoading() {
      $(this.selectors.checkoutForm).unblock();
    },

    fillAddress(section, applyToBoth = false) {
      const $country = $(`#${section}_country`);

      if ($country.length && $country.val() !== "BR") return;

      const $postcodeField = $(`#${section}_postcode`);
      const cep = $postcodeField.val().replace(/\D/g, "");

      if (cep.length !== 8) return;

      this.showLoading();
      $postcodeField.blur();

      $.ajax({
        type: "POST",
        url: autocep_params.ajax_url,
        dataType: "json",
        data: {
          action: "autocep_get_address",
          cep,
          nonce: autocep_params.nonce,
        },
      })
        .done((response) => {
          if (!response.success) return;

          this.populateFields(section, response.data);

          if (applyToBoth) {
            const other =
              section === "billing" ? "shipping" : "billing";
            this.populateFields(other, response.data);
          }
        })
        .always(() => {
          this.hideLoading();
        });
    },

    populateFields(section, data) {
      $(`#${section}_address_1`)
        .val(data.logradouro)
        .change();

      const $neighborhood = $(`#${section}_neighborhood`);
      ($neighborhood.length
        ? $neighborhood
        : $(`#${section}_address_2`)
      )
        .val(data.bairro)
        .change();

      $(`#${section}_city`)
        .val(data.localidade)
        .change();

      $(`#${section}_state`)
        .val(data.uf)
        .trigger("change");
    },
  };

  AECEP.init();

  $(document.body).on(
    "updated_checkout wc_fragments_loaded",
    () => {
      AECEP.autoFillIfNeeded();
    }
  );
});
