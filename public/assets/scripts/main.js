function toggleMenu() {
  const headerNav = document.querySelector('.header-nav');
  headerNav.classList.toggle('active');
}


/* ===========================
   STICKY NAVBAR ON SCROLL
   =========================== */
(function () {
  const header = document.getElementById('header');
  if (!header) return;

  window.addEventListener('scroll', function () {
    const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
    
    if (currentScroll > 50) {
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