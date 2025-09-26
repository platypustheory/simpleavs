/**
 * SimpleAVS DOB input masking.
 * - Auto-inserts slashes while typing.
 * - Honors drupalSettings.simpleavs.date_format: "mdy" or "dmy" (defaults to mdy).
 * This file is standalone and does not replace your existing modal JS.
 */
(function (Drupal, drupalSettings) {
  "use strict";

  function formatDigits(d, order) {
    // d: only digits
    // order: "mdy" or "dmy"
    if (!/^\d{1,8}$/.test(d)) { return d; // let user continue typing
    }

    if (order === "dmy") {
      // DD/MM/YYYY
      if (d.length <= 2) { return d;
      }
      if (d.length <= 4) { return d.slice(0,2) + "/" + d.slice(2);
      }
      return d.slice(0,2) + "/" + d.slice(2,4) + "/" + d.slice(4,8);
    } else {
      // MDY (default): MM/DD/YYYY
      if (d.length <= 2) { return d;
      }
      if (d.length <= 4) { return d.slice(0,2) + "/" + d.slice(2);
      }
      return d.slice(0,2) + "/" + d.slice(2,4) + "/" + d.slice(4,8);
    }
  }

  Drupal.behaviors.simpleavsDobMask = {
    attach(context) {
      const cfg = (drupalSettings.simpleavs || {});
      const order = (cfg.date_format === "dmy") ? "dmy" : "mdy";

      // Common selectors for the DOB input; attach only once per element.
      const inputs = once(
        "simpleavs-dob-mask",
        'input.simpleavs-dob, input[name="dob"], input[name="simpleavs_dob"], #simpleavs-dob',
        context
      );
      if (!inputs.length) { return;
      }

      inputs.forEach((el) => {
        // Normalize any prefilled value.
        el.value = el.value.replace(/[^\d]/g, "").slice(0, 8);
        el.value = formatDigits(el.value, order);

        el.addEventListener("input", () => {
          const digits = el.value.replace(/\D+/g, "").slice(0, 8);
          el.value = formatDigits(digits, order);
        });

        el.addEventListener("blur", () => {
          const digits = el.value.replace(/\D+/g, "").slice(0, 8);
          el.value = formatDigits(digits, order);
        });
      });
    }
  };
})(Drupal, drupalSettings);
