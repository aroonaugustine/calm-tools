(function () {
  const searchInput = document.querySelector('[data-filter="search"]');
  const cards = Array.from(document.querySelectorAll('[data-tool-card]'));
  const emptyStates = Array.from(document.querySelectorAll('[data-empty]'));
  const sectionCounts = Array.from(document.querySelectorAll('[data-section-count]'));
  const totalCountBadge = document.querySelector('[data-tool-count]');
  const tokenInput = document.querySelector('[data-token-input]');
  const tokenForm = document.querySelector('[data-token-form]');
  const tokenMessage = document.querySelector('[data-token-msg]');
  const tokenClear = document.querySelector('[data-token-clear]');
  const launchButtons = Array.from(document.querySelectorAll('[data-tool-slug]'));
  const main = document.querySelector('main');
  const runnerEndpoint = main?.dataset.runnerEndpoint || 'tool-runner.php';
  const storageKey = 'portalAccessToken';
  const viewTokenInput = document.querySelector('[data-view-token-input]');
  const viewTokenSave = document.querySelector('[data-view-token-save]');
  const viewTokenClear = document.querySelector('[data-view-token-clear]');
  const viewTokenMessage = document.querySelector('[data-view-token-msg]');
  const viewStorageKey = 'portalViewToken';
  const defaultViewHint = viewTokenMessage?.textContent?.trim() ?? '';

  function showTokenMessage(message) {
    if (tokenMessage) {
      tokenMessage.textContent = message;
    }
  }

  function showViewTokenMessage(message) {
    if (viewTokenMessage) {
      viewTokenMessage.textContent = message;
    }
  }

  function getToken() {
    return sessionStorage.getItem(storageKey) || '';
  }

  function setToken(value) {
    if (value) {
      sessionStorage.setItem(storageKey, value);
    } else {
      sessionStorage.removeItem(storageKey);
    }
  }

  function getViewToken() {
    return sessionStorage.getItem(viewStorageKey) || '';
  }

  function setViewToken(value) {
    if (value) {
      sessionStorage.setItem(viewStorageKey, value);
    } else {
      sessionStorage.removeItem(viewStorageKey);
    }
  }

  function applyFilters() {
    const term = (searchInput?.value || '').trim().toLowerCase();
    const counts = { web: 0, cli: 0 };
    let totalVisible = 0;

    cards.forEach((card) => {
      const haystack = card.dataset.search || '';
      const mode = card.dataset.mode || 'web';
      const match = !term || haystack.includes(term);
      card.hidden = !match;
      if (match) {
        counts[mode] = (counts[mode] || 0) + 1;
        totalVisible += 1;
      }
    });

    sectionCounts.forEach((el) => {
      const mode = el.dataset.sectionCount || 'web';
      el.textContent = (counts[mode] || 0).toString();
    });

    emptyStates.forEach((state) => {
      const mode = state.dataset.empty || 'web';
      state.hidden = (counts[mode] || 0) > 0;
    });

    if (totalCountBadge) {
      totalCountBadge.textContent = totalVisible.toString();
    }
  }

  searchInput?.addEventListener('input', applyFilters);

  tokenForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const value = (tokenInput?.value || '').trim();
    if (!value) {
      tokenInput?.classList.add('token-error');
      showTokenMessage('Token is required to launch every tool.');
      return;
    }
    setToken(value);
    tokenInput?.classList.remove('token-error');
    showTokenMessage('Token saved for this session.');
  });

  tokenClear?.addEventListener('click', () => {
    setToken('');
    if (tokenInput) {
      tokenInput.value = '';
      tokenInput.classList.remove('token-error');
    }
    showTokenMessage('Token cleared. Enter a new token to enable launches.');
  });

  tokenInput?.addEventListener('input', () => {
    tokenInput.classList.remove('token-error');
    showTokenMessage('Required for launching every tool. Stored only in this browser session.');
  });

  viewTokenSave?.addEventListener('click', () => {
    const value = (viewTokenInput?.value || '').trim();
    if (!value) {
      viewTokenInput?.classList.add('token-error');
      showViewTokenMessage('Viewer token is required to save it.');
      return;
    }
    setViewToken(value);
    viewTokenInput?.classList.remove('token-error');
    showViewTokenMessage('Viewer token saved for this session.');
  });

  viewTokenClear?.addEventListener('click', () => {
    setViewToken('');
    if (viewTokenInput) {
      viewTokenInput.value = '';
      viewTokenInput.classList.remove('token-error');
    }
    showViewTokenMessage(defaultViewHint);
  });

  viewTokenInput?.addEventListener('input', () => {
    viewTokenInput.classList.remove('token-error');
    showViewTokenMessage(defaultViewHint);
  });

  function openRunner(slug) {
    if (!slug) {
      return;
    }

    const token = getToken();
    if (!token) {
      tokenInput?.focus();
      tokenInput?.classList.add('token-error');
      showTokenMessage('Please save your access token before launching a tool.');
      return;
    }

    const base = new URL(runnerEndpoint, window.location.href);
    base.searchParams.set('tool', slug);
    base.searchParams.set('token', token);
    window.open(base.toString(), '_blank', 'noopener');
  }

  launchButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const slug = button.dataset.toolSlug;
      openRunner(slug);
    });
  });

  const savedToken = getToken();
  if (savedToken && tokenInput) {
    tokenInput.value = savedToken;
  }

  const savedViewToken = getViewToken();
  if (savedViewToken && viewTokenInput) {
    viewTokenInput.value = savedViewToken;
  }

  applyFilters();
})();
