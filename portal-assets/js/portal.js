(() => {
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
  const tokenCheckEndpoint = main?.dataset.tokenCheck || 'token-check.php';
  const storageKey = 'portalAccessToken';
  const tokenTypeStorageKey = 'portalAccessTokenType';
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

  function getTokenType() {
    return sessionStorage.getItem(tokenTypeStorageKey) || '';
  }

  function setTokenType(value) {
    if (value) {
      sessionStorage.setItem(tokenTypeStorageKey, value);
    } else {
      sessionStorage.removeItem(tokenTypeStorageKey);
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

  async function checkTokenType(value) {
    const response = await fetch(tokenCheckEndpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        Accept: 'application/json',
      },
      body: new URLSearchParams({ token: value }),
    });

    if (!response.ok) {
      throw new Error('Token validation failed');
    }

    const payload = await response.json();
    return payload.type || '';
  }

  function updateLaunchButtons() {
    const tokenType = getTokenType();
    const hasMaster = tokenType === 'master' && Boolean(getToken());
    const hasViewer = tokenType === 'viewer' && Boolean(getViewToken());

    launchButtons.forEach((button) => {
      button.disabled = !(hasMaster || hasViewer);
      button.classList.toggle('launch-disabled', hasViewer && !hasMaster);
    });

    if (hasViewer && !hasMaster) {
      showTokenMessage('Viewer mode active — enter your session token above to run tools.');
    } else if (hasMaster) {
      showTokenMessage('Token saved for this session.');
    } else {
      showTokenMessage('Required for launching every tool. Stored only in this browser session.');
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

  tokenForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const value = (tokenInput?.value || '').trim();
    if (!value) {
      tokenInput?.classList.add('token-error');
      showTokenMessage('Token is required to launch every tool.');
      return;
    }
    try {
      const type = await checkTokenType(value);
      if (type !== 'master') {
        tokenInput?.classList.add('token-error');
        showTokenMessage('Enter the master session token to run tools.');
        return;
      }
      setToken(value);
      setTokenType('master');
      tokenInput?.classList.remove('token-error');
      showTokenMessage('Token saved for this session.');
      updateLaunchButtons();
    } catch {
      showTokenMessage('Unable to validate token today.');
    }
  });

  tokenClear?.addEventListener('click', () => {
    setToken('');
    setTokenType('');
    if (tokenInput) {
      tokenInput.value = '';
      tokenInput.classList.remove('token-error');
    }
    showTokenMessage('Token cleared. Enter a new token to enable launches.');
    updateLaunchButtons();
  });

  tokenInput?.addEventListener('input', () => {
    tokenInput.classList.remove('token-error');
  });

  viewTokenSave?.addEventListener('click', async () => {
    const value = (viewTokenInput?.value || '').trim();
    if (!value) {
      viewTokenInput?.classList.add('token-error');
      showViewTokenMessage('Viewer token is required to save it.');
      return;
    }
    try {
      const type = await checkTokenType(value);
      if (type !== 'viewer') {
        viewTokenInput?.classList.add('token-error');
        showViewTokenMessage('Enter a valid viewer token.');
        return;
      }
      setViewToken(value);
      setTokenType('viewer');
      viewTokenInput?.classList.remove('token-error');
      showViewTokenMessage('Viewer token saved for this session.');
      updateLaunchButtons();
    } catch {
      showViewTokenMessage('Unable to validate viewer token today.');
    }
  });

  viewTokenClear?.addEventListener('click', () => {
    setViewToken('');
    if (viewTokenInput) {
      viewTokenInput.value = '';
      viewTokenInput.classList.remove('token-error');
    }
    showViewTokenMessage(defaultViewHint);
    updateLaunchButtons();
  });

  viewTokenInput?.addEventListener('input', () => {
    viewTokenInput.classList.remove('token-error');
    showViewTokenMessage(defaultViewHint);
  });

  function openRunner(slug) {
    if (!slug) {
      return;
    }

    const tokenType = getTokenType();
    const base = new URL(runnerEndpoint, window.location.href);
    base.searchParams.set('tool', slug);

    if (tokenType === 'master') {
      const token = getToken();
      if (!token) {
        tokenInput?.focus();
        tokenInput?.classList.add('token-error');
        showTokenMessage('Please save your access token before launching a tool.');
        return;
      }
      base.searchParams.set('token', token);
    } else if (tokenType === 'viewer') {
      const viewerToken = getViewToken();
      if (!viewerToken) {
        showTokenMessage('Viewer token missing; enter it above to view tools.');
        return;
      }
      base.searchParams.set('token', viewerToken);
      base.searchParams.set('mode', 'viewer');
    } else {
      tokenInput?.focus();
      tokenInput?.classList.add('token-error');
      showTokenMessage('Please save your access token before launching a tool.');
      return;
    }

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

  if (getTokenType() === '') {
    if (savedToken) {
      setTokenType('master');
    } else if (savedViewToken) {
      setTokenType('viewer');
    }
  }

  updateLaunchButtons();
  applyFilters();
})();
