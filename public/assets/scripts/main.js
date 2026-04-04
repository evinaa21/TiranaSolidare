function toggleMenu() {
  const headerNav = document.querySelector('.header-nav');
  headerNav.classList.toggle('active');

  if (!headerNav.classList.contains('active')) {
    closeUserMenu();
  }
}

// Keep logout form CSRF token in sync with the rotating session token.
// The hidden field is rendered at page-load time; any subsequent API mutation
// rotates the session token, so we update the field just before submission.
document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form && form.action && form.action.indexOf('logout.php') !== -1) {
            var field = form.querySelector('input[name="_csrf_token"]');
            var meta  = document.querySelector('meta[name="csrf-token"]');
            if (field && meta && meta.content) {
                field.value = meta.content;
            }
        }
    });
});

function isMobileHeaderView() {
  return window.matchMedia('(max-width: 1280px)').matches;
}

function closeUserMenu() {
  const dropdown = document.getElementById('header-user-dropdown');
  const avatarBtn = document.querySelector('.header-user-avatar');
  if (!dropdown || !avatarBtn) return;

  dropdown.classList.remove('active');
  avatarBtn.setAttribute('aria-expanded', 'false');
}

function toggleUserMenu(event) {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }

  const dropdown = document.getElementById('header-user-dropdown');
  const avatarBtn = document.querySelector('.header-user-avatar');
  if (!dropdown || !avatarBtn) return;

  const willOpen = !dropdown.classList.contains('active');
  if (willOpen) {
    dropdown.classList.add('active');
    avatarBtn.setAttribute('aria-expanded', 'true');
    if (isMobileHeaderView()) {
      dropdown.scrollIntoView({ block: 'nearest' });
    }
  } else {
    closeUserMenu();
  }
}

document.addEventListener('DOMContentLoaded', function () {
  const userMenu = document.getElementById('header-user-menu');
  if (!userMenu) return;

  document.addEventListener('click', function (event) {
    if (!userMenu.contains(event.target)) {
      closeUserMenu();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeUserMenu();
    }
  });

  document.querySelectorAll('#header-user-dropdown a').forEach((link) => {
    link.addEventListener('click', () => {
      closeUserMenu();
      if (isMobileHeaderView()) {
        const headerNav = document.querySelector('.header-nav');
        headerNav?.classList.remove('active');
      }
    });
  });
});


/* ===========================
   STICKY NAVBAR ON SCROLL
   =========================== */
(function () {
  const header = document.getElementById('header');
  if (!header) return;

  var scrollThreshold = document.body.classList.contains('page-home') ? 50 : 0;

  window.addEventListener('scroll', function () {
    var currentScroll = window.pageYOffset || document.documentElement.scrollTop;
    
    if (currentScroll > scrollThreshold) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  }, { passive: true });
})();


/* ===========================
   STATS COUNT-UP ANIMATION
   =========================== */
document.addEventListener('DOMContentLoaded', function () {
  const counters = document.querySelectorAll('#main-stats b[data-count]');
  if (!counters.length) return;

  const duration = 2000; // ms
  const fps = 60;
  const steps = Math.ceil(duration / (1000 / fps));

  function easeOutQuart(t) {
    return 1 - Math.pow(1 - t, 4);
  }

  function animateCounter(el) {
    if (el.dataset.animated) return;
    el.dataset.animated = 'true';
    const target = parseInt(el.getAttribute('data-count'), 10);
    let step = 0;
    const interval = setInterval(() => {
      step++;
      const progress = easeOutQuart(step / steps);
      const current = Math.round(target * progress);
      el.textContent = current.toLocaleString() + '+';
      if (step >= steps) {
        el.textContent = target.toLocaleString() + '+';
        clearInterval(interval);
      }
    }, 1000 / fps);
  }

  // Use IntersectionObserver so numbers animate when scrolled into view
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            animateCounter(entry.target);
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.1 }
    );
    counters.forEach((el) => observer.observe(el));
  } else {
    // Fallback: animate immediately
    counters.forEach((el) => animateCounter(el));
  }
});


/* ===========================
   PARALLAX HERO SHAPES
   =========================== */
(function () {
  const shapes = document.querySelectorAll('.hero-shape');
  if (!shapes.length) return;

  let ticking = false;
  window.addEventListener('mousemove', (e) => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      const x = (e.clientX / window.innerWidth - 0.5) * 2;
      const y = (e.clientY / window.innerHeight - 0.5) * 2;
      shapes.forEach((s, i) => {
        const speed = (i + 1) * 6;
        s.style.transform = `translate(${x * speed}px, ${y * speed}px)`;
      });
      ticking = false;
    });
  });
})();


/* ===========================
   EQUAL-HEIGHT CARDS IN SCROLLERS
   =========================== */
(function () {
  function equalizeCards() {
    document.querySelectorAll('.hs_Wrapper').forEach(wrapper => {
      const cards = wrapper.querySelectorAll('.help_card');
      if (cards.length < 2) return;
      // Reset heights first
      cards.forEach(c => c.style.minHeight = '');
      // Find the tallest card
      let maxH = 0;
      cards.forEach(c => {
        if (c.offsetHeight > maxH) maxH = c.offsetHeight;
      });
      // Apply to all
      cards.forEach(c => c.style.minHeight = maxH + 'px');
    });
  }

  // Run after DOM + images loaded
  document.addEventListener('DOMContentLoaded', equalizeCards);
  window.addEventListener('load', equalizeCards);
  window.addEventListener('resize', equalizeCards);
})();

function initPasswordVisibilityToggles() {
  const passwordFields = document.querySelectorAll('input[type="password"]');
  if (!passwordFields.length) return;
  passwordFields.forEach((input) => {
    if (input.closest('.auth-password-wrap')) return;
    if (input.closest('.password-input-wrapper')) return;
    if (input.dataset.hasPasswordToggle) return;
    input.dataset.hasPasswordToggle = '1';
    const wrapper = document.createElement('div');
    wrapper.className = 'password-input-wrapper';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'password-toggle-btn';
    btn.setAttribute('aria-label', 'Shfaq ose fshih fjalëkalimin');
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
    btn.addEventListener('click', () => {
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      btn.innerHTML = isPassword
        ? '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.41 21.41 0 0 1 5.06-5.94"/><path d="M1 1l22 22"/><path d="M9.53 9.53A3 3 0 1 0 14.47 14.47"/><path d="M22 12s-4 7-11 7a10.94 10.94 0 0 1-5.94-1.94"/></svg>'
        : '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
    });
    wrapper.appendChild(btn);
  });
}
document.addEventListener('DOMContentLoaded', initPasswordVisibilityToggles);


/* ===========================
   EVENTS — 3D CARD CAROUSEL
   =========================== */
(function () {
  var stage = document.getElementById('evs-stage');
  if (!stage) return;

  var cards = Array.from(stage.querySelectorAll('.evs-card'));
  var indicatorWrap = document.getElementById('evs-indicators');
  var dots = indicatorWrap ? Array.from(indicatorWrap.querySelectorAll('.evs-dot-btn')) : [];
  var prevBtn = stage.querySelector('.evs-nav--prev');
  var nextBtn = stage.querySelector('.evs-nav--next');

  var current = 0;
  var total = cards.length;
  var autoTimer = null;
  var AUTO_DELAY = 5000;

  function updatePositions() {
    cards.forEach(function (card, i) {
      var pos = i - current;
      if (pos > Math.floor(total / 2)) pos -= total;
      if (pos < -Math.floor(total / 2)) pos += total;

      if (pos < -2 || pos > 2) {
        card.removeAttribute('data-pos');
      } else {
        card.setAttribute('data-pos', pos);
      }
    });

    dots.forEach(function (dot, i) {
      var isActive = i === current;
      dot.classList.toggle('evs-dot-btn--active', isActive);
      var fill = dot.querySelector('.evs-dot-fill');
      if (fill) {
        fill.style.animation = 'none';
        fill.offsetHeight;
        fill.style.animation = '';
      }
    });
  }

  function goTo(idx) {
    current = ((idx % total) + total) % total;
    updatePositions();
    resetAuto();
  }

  function next() { goTo(current + 1); }
  function prev() { goTo(current - 1); }

  function resetAuto() {
    clearInterval(autoTimer);
    autoTimer = setInterval(next, AUTO_DELAY);
  }

  if (prevBtn) prevBtn.addEventListener('click', prev);
  if (nextBtn) nextBtn.addEventListener('click', next);

  dots.forEach(function (dot) {
    dot.addEventListener('click', function () {
      goTo(parseInt(dot.dataset.idx, 10));
    });
  });

  // Touch / swipe support
  var startX = 0;
  stage.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
  stage.addEventListener('touchend', function (e) {
    var diff = e.changedTouches[0].clientX - startX;
    if (Math.abs(diff) > 50) { diff > 0 ? prev() : next(); }
  });

  // Keyboard navigation
  stage.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft') prev();
    if (e.key === 'ArrowRight') next();
  });

  // Initialize
  updatePositions();
  resetAuto();

  // Pause auto on hover
  stage.addEventListener('mouseenter', function () { clearInterval(autoTimer); });
  stage.addEventListener('mouseleave', resetAuto);
})();


/* ===========================
   COMMUNITY VOICES — REVEAL + SCROLL
   =========================== */
(function () {
  /* ── Staggered card entrance ── */
  var cards = document.querySelectorAll('.cv-card');
  if (!cards.length) return;

  cards.forEach(function (card, i) {
    card.style.setProperty('--card-delay', (i * 0.08) + 's');
  });

  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.style.animationPlayState = 'running';
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    cards.forEach(function (card) { observer.observe(card); });
  } else {
    cards.forEach(function (card) { card.style.animationPlayState = 'running'; });
  }

  /* ── Gallery carousel (full-card pages) ── */
  var gallery = document.querySelector('.cv-gallery');
  var wrap = document.querySelector('.cv-gallery-wrap');
  var prevBtn = document.querySelector('.cv-scroll-btn--prev');
  var nextBtn = document.querySelector('.cv-scroll-btn--next');

  if (gallery && wrap && prevBtn && nextBtn) {
    var page = 0;

    function isMobileGallery() {
      return window.innerWidth <= 700;
    }

    function getPerPage() {
      var w = wrap.offsetWidth;
      if (w <= 500) return 1;
      if (w <= 768) return 2;
      return 3;
    }

    function getMaxPage() {
      var total = cards.length;
      var perPage = getPerPage();
      return Math.max(0, Math.ceil(total / perPage) - 1);
    }

    function slide() {
      if (isMobileGallery()) return;
      var perPage = getPerPage();
      var gap = 24;
      var cardW = (wrap.offsetWidth - gap * (perPage - 1)) / perPage;
      var offset = page * perPage * (cardW + gap);
      gallery.style.transform = 'translateX(-' + offset + 'px)';
      prevBtn.style.opacity = page <= 0 ? '0.3' : '1';
      prevBtn.style.pointerEvents = page <= 0 ? 'none' : 'auto';
      nextBtn.style.opacity = page >= getMaxPage() ? '0.3' : '1';
      nextBtn.style.pointerEvents = page >= getMaxPage() ? 'none' : 'auto';
    }

    function mobileScroll(dir) {
      var card = gallery.querySelector('.cv-card');
      var scrollAmt = card ? card.offsetWidth + 12 : 300;
      gallery.scrollBy({ left: dir * scrollAmt, behavior: 'smooth' });
    }

    prevBtn.addEventListener('click', function (e) {
      if (isMobileGallery()) {
        e.stopPropagation();
        mobileScroll(-1);
        return;
      }
      if (page > 0) { page--; slide(); }
    });
    nextBtn.addEventListener('click', function (e) {
      if (isMobileGallery()) {
        e.stopPropagation();
        mobileScroll(1);
        return;
      }
      if (page < getMaxPage()) { page++; slide(); }
    });

    window.addEventListener('resize', function () {
      if (isMobileGallery()) {
        gallery.style.transform = '';
        prevBtn.style.opacity = '';
        prevBtn.style.pointerEvents = '';
        nextBtn.style.opacity = '';
        nextBtn.style.pointerEvents = '';
        return;
      }
      if (page > getMaxPage()) page = getMaxPage();
      slide();
    });

    slide();
  }
})();


/* ===========================
   SCROLL-REVEAL ENGINE
   =========================== */
(function () {
  var reveals = document.querySelectorAll('.reveal');
  if (!reveals.length) return;

  // Assign stagger index to children of .reveal-stagger containers
  document.querySelectorAll('.reveal-stagger').forEach(function (parent) {
    var children = parent.querySelectorAll('.reveal');
    children.forEach(function (child, i) {
      child.style.setProperty('--reveal-i', i);
    });
  });

  if ('IntersectionObserver' in window) {
    var revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('revealed');
          revealObserver.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.08,
      rootMargin: '0px 0px -40px 0px'
    });

    reveals.forEach(function (el) {
      revealObserver.observe(el);
    });
  } else {
    // Fallback: show everything
    reveals.forEach(function (el) {
      el.classList.add('revealed');
    });
  }
})();


/* ===========================
   SMOOTH SECTION PARALLAX
   =========================== */
(function () {
  var hero = document.getElementById('main');
  if (!hero) return;

  var heroBlob = hero.querySelector('.hero-blob-tr');
  var ticking = false;

  window.addEventListener('scroll', function () {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      var scrollY = window.pageYOffset;
      if (scrollY < window.innerHeight && heroBlob) {
        heroBlob.style.transform = 'translateY(' + (scrollY * 0.15) + 'px)';
      }
      ticking = false;
    });
  }, { passive: true });
})();


/* ===========================
   MOBILE TOUCH FEEDBACK
   =========================== */
(function () {
  if (!('ontouchstart' in window)) return;

  var touchCards = document.querySelectorAll('.sf-card, .kbtn-card, .cv-card, .cv-spotlight');
  touchCards.forEach(function (card) {
    card.addEventListener('touchstart', function () {
      this.style.transform = 'scale(0.98)';
    }, { passive: true });
    card.addEventListener('touchend', function () {
      this.style.transform = '';
    }, { passive: true });
  });
})();
