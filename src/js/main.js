(() => {
  const track = document.getElementById('occasionsTrack');
  const nextBtn = document.getElementById('occasionsNext');

  if (track && nextBtn) {
    nextBtn.addEventListener('click', () => {
      const card = track.querySelector('.occasions__card');
      const step = card ? card.getBoundingClientRect().width + 20 : track.clientWidth * 0.8;

      const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;

      track.scrollTo({
        left: atEnd ? 0 : track.scrollLeft + step,
        behavior: 'smooth',
      });
    });
  }

  const storiesNext = document.getElementById('storiesNext');
  const firstThing = document.getElementById('first-thing');
  if (storiesNext && firstThing) {
    storiesNext.addEventListener('click', () => {
      firstThing.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  const navAccountLink = document.getElementById('navAccountLink');
  if (navAccountLink && window.Api) {
    Api.get('api/me.php').then(({ user }) => {
      if (!user) return;
      navAccountLink.title = `Signed in as ${user.name} — click to sign out`;
      navAccountLink.addEventListener('click', async (e) => {
        e.preventDefault();
        await Api.post('api/logout.php', {});
        window.location.reload();
      });
    }).catch(() => {});
  }
})();
