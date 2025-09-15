(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.simpleavs = {
    attach: function (context) {
      if (!drupalSettings.simpleavs || context !== document) {
        return;
      }
      // Prevent double-init.
      if (document.documentElement.hasAttribute('data-simpleavs-init')) {
        return;
      }
      document.documentElement.setAttribute('data-simpleavs-init', '1');

      const s = drupalSettings.simpleavs;

      // Build a tiny, visible overlay so we can prove it’s attaching.
      const overlay = document.createElement('div');
      overlay.style.position = 'fixed';
      overlay.style.inset = '0';
      overlay.style.background = s.appearance.overlayColor || '#000';
      overlay.style.opacity = (s.appearance.overlayOpacity ?? 0.7);
      overlay.style.zIndex = '99998';

      const modal = document.createElement('div');
      modal.style.position = 'fixed';
      modal.style.zIndex = '99999';
      modal.style.top = '50%';
      modal.style.left = '50%';
      modal.style.transform = 'translate(-50%, -50%)';
      modal.style.background = s.appearance.modalBg || '#fff';
      modal.style.color = s.appearance.textColor || '#111';
      modal.style.padding = '20px';
      modal.style.borderRadius = '12px';
      modal.style.minWidth = '320px';
      modal.style.boxShadow = '0 10px 30px rgba(0,0,0,.25)';

      const h = document.createElement('h2');
      h.textContent = s.strings.modalTitle || 'Age Verification';
      h.style.marginTop = '0';
      modal.appendChild(h);

      const p = document.createElement('p');
      p.textContent = (s.strings.questionText || 'Are you over the age of [age]?').replace('[age]', s.minAge);
      modal.appendChild(p);

      const btnWrap = document.createElement('div');
      btnWrap.style.display = 'flex';
      btnWrap.style.gap = '10px';

      function makeBtn(label, ok) {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = label;
        b.style.background = s.appearance.buttonBg || '#2d6cdf';
        b.style.color = s.appearance.buttonText || '#fff';
        b.style.border = 'none';
        b.style.padding = '8px 14px';
        b.style.borderRadius = '8px';
        b.style.cursor = 'pointer';
        b.addEventListener('click', async () => {
          try {
            const tRes = await fetch(s.endpoints.token, { credentials: 'same-origin' });
            const tJson = await tRes.json();
            const token = tJson.token;

            const body = new URLSearchParams();
            body.set('action', ok ? 'yes' : 'no');
            body.set('token', token);

            const vRes = await fetch(s.endpoints.verify, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': token
              },
              credentials: 'same-origin',
              body: body.toString()
            });

            const vJson = await vRes.json();
            if (vJson.ok) {
              overlay.remove();
              modal.remove();
              if (vJson.redirect) {
                window.location.href = vJson.redirect;
              }
            } else {
              alert(vJson.error || 'Verification failed.');
            }
          } catch (e) {
            console.error('SimpleAVS error', e);
            alert('Verification failed.');
          }
        });
        return b;
      }

      btnWrap.appendChild(makeBtn(s.strings.yesButton || 'Yes', true));
      btnWrap.appendChild(makeBtn(s.strings.noButton || 'No', false));

      modal.appendChild(btnWrap);
      document.body.appendChild(overlay);
      document.body.appendChild(modal);
    }
  };

})(Drupal, drupalSettings);
