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

  function showTokenMessage(message) {
    if (tokenMessage) {
      tokenMessage.textContent = message;
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
    const hasToken = Boolean(getToken());
    launchButtons.forEach((button) => {
      button.disabled = !hasToken;
      button.classList.toggle('launch-disabled', !hasToken);
    });

    if (hasToken) {
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
      tokenInput?.classList.remove('token-error');
      showTokenMessage('Token saved for this session.');
      updateLaunchButtons();
    } catch {
      showTokenMessage('Unable to validate token today.');
    }
  });

  tokenClear?.addEventListener('click', () => {
    setToken('');
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

  updateLaunchButtons();
  applyFilters();
})();
