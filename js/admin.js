/**
 * SimpleAVS admin helpers: presets that fill the form fields.
 */
(function (Drupal, drupalSettings, once) {
  "use strict";

  const PRESETS = (drupalSettings.simpleavsAdmin && drupalSettings.simpleavsAdmin.presets) || {};

  function applyPresetToForm(form, presetKey) {
    const p = PRESETS[presetKey];
    if (!p) { return;
    }

    // Helper to set value if element exists.
    const setVal = (name, value) => {
      const el = form.querySelector(`[name = "${CSS.escape(name)}"]`);
      if (!el) { return;
      }
      el.value = value;
      // Trigger change for any states/validation
      const ev = new Event('change', { bubbles: TRUE });
      el.dispatchEvent(ev);
    };

    // Appearance.
    if (p.appearance) {
      setVal('appearance[overlay_color]', p.appearance.overlay_color ? ? '');
      setVal('appearance[overlay_opacity]', (p.appearance.overlay_opacity ? ? '').toString());
      setVal('appearance[modal_bg]', p.appearance.modal_bg ? ? '');
      setVal('appearance[text_color]', p.appearance.text_color ? ? '');
      setVal('appearance[button_bg]', p.appearance.button_bg ? ? '');
      setVal('appearance[button_text]', p.appearance.button_text ? ? '');
    }

    // Strings (only populate common ones; admins can tweak all).
    if (p.strings) {
      setVal('strings[modal_title]', p.strings.modal_title ? ? '');
      setVal('strings[question_text]', p.strings.question_text ? ? '');
      setVal('strings[yes_button]', p.strings.yes_button ? ? '');
      setVal('strings[no_button]', p.strings.no_button ? ? '');
    }
  }

  Drupal.behaviors.simpleavsAdminPresets = {
    attach(context) {
      const forms = once('simpleavs-admin', 'form#simpleavs_settings_form', context);
      forms.forEach((form) => {
        const presetSelect = form.querySelector('select[name="preset"]');
        if (!presetSelect) { return;
        }

        presetSelect.addEventListener('change', (e) => {
          const key = e.target.value;
          if (!key) { return; // “— None —”
          }
          applyPresetToForm(form, key);
        });
      });
    }
  };

})(Drupal, drupalSettings, once);
