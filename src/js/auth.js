(() => {
  const errorEl = document.getElementById('formError');
  const googleSlot = document.getElementById('googleSlot');
  const googleDivider = document.getElementById('googleDivider');

  function showError(message) {
    if (!errorEl) return;
    errorEl.textContent = message;
    errorEl.classList.add('is-visible');
  }

  async function handleGoogleCredential(credential) {
    try {
      await Api.post('api/google-login.php', { credential });
      window.location.href = 'index.html';
    } catch (err) {
      showError(err.message);
    }
  }

  if (googleSlot) {
    Api.mountGoogleButton(googleSlot, handleGoogleCredential).then(() => {
      if (googleSlot.children.length && googleDivider) {
        googleDivider.style.display = 'flex';
      }
    });
  }

  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      errorEl.classList.remove('is-visible');
      const formData = new FormData(loginForm);
      try {
        await Api.post('api/login.php', {
          email: formData.get('email'),
          password: formData.get('password'),
        });
        window.location.href = 'index.html';
      } catch (err) {
        showError(err.message);
      }
    });
  }

  const signupForm = document.getElementById('signupForm');
  if (signupForm) {
    signupForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      errorEl.classList.remove('is-visible');
      const formData = new FormData(signupForm);
      try {
        await Api.post('api/register.php', {
          name: formData.get('name'),
          email: formData.get('email'),
          password: formData.get('password'),
        });
        window.location.href = 'index.html';
      } catch (err) {
        showError(err.message);
      }
    });
  }
})();
