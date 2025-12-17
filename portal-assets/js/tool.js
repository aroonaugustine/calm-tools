(() => {
  const body = document.body;
  if (body) {
    body.classList.add('portal-tool-body');
  }

  const main = document.querySelector('main');
  if (main) {
    main.classList.add('portal-tool-shell');
  }

  const params = new URLSearchParams(window.location.search);
  const token = params.get('token');
  const fields = Array.from(document.querySelectorAll('input[name="token"]'));
  if (!fields.length) {
    return;
  }

  const applyHint = () => {
    fields.forEach((field) => {
      const container = field.closest('label') || field.closest('fieldset');
      if (!container) {
        return;
      }
      if (container.querySelector('.token-hint')) {
        return;
      }
      const hint = document.createElement('div');
      hint.className = 'token-hint';
      hint.textContent = 'Launch this tool from the CALM Admin Toolkit portal to auto-fill the access token.';
      container.appendChild(hint);
    });
  };

  if (token) {
    fields.forEach((field) => {
      field.value = token;
      field.type = 'hidden';
      if (field.hasAttribute('data-token-keep')) {
        return;
      }
      const container = field.closest('label') || field.closest('fieldset');
      if (container) {
        container.querySelector('.token-hint')?.remove();
        container.style.display = 'none';
      }
    });
    return;
  }

  applyHint();
})();
