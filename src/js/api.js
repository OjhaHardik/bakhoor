const Api = (() => {
  async function request(url, options = {}) {
    const res = await fetch(url, {
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      ...options,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) {
      throw new Error(data.error || 'Something went wrong. Please try again.');
    }
    return data;
  }

  const post = (url, body) => request(url, { method: 'POST', body: JSON.stringify(body) });
  const get = (url) => request(url);

  let configPromise = null;
  const getConfig = () => {
    if (!configPromise) {
      configPromise = get('api/public-config.php');
    }
    return configPromise;
  };

  let gisLoadPromise = null;
  function loadGoogleScript() {
    if (gisLoadPromise) return gisLoadPromise;
    gisLoadPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://accounts.google.com/gsi/client';
      script.async = true;
      script.defer = true;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
    return gisLoadPromise;
  }

  async function mountGoogleButton(slotEl, onCredential) {
    const config = await getConfig();
    if (!config.googleEnabled || !slotEl) return;

    await loadGoogleScript();

    window.google.accounts.id.initialize({
      client_id: config.googleClientId,
      callback: (response) => onCredential(response.credential),
    });

    window.google.accounts.id.renderButton(slotEl, {
      theme: 'outline',
      size: 'large',
      width: 320,
      text: 'continue_with',
    });
  }

  return { post, get, getConfig, mountGoogleButton };
})();
