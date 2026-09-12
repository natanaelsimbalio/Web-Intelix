(() => {
  'use strict';
  // Aditivo a index.html: NO reemplaza el script inline existente (nav,
  // dropdowns, reveal-on-scroll, tilt glow, FAQ, terminal typing) — solo
  // suma smooth scroll (Lenis) y microinteracciones en los botones.

  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- Lenis smooth scroll (self-hosted, respeta CSP script-src 'self') ---- */
  if (!reduceMotion && window.Lenis) {
    const lenis = new Lenis({
      duration: 1.05,
      easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
      smoothWheel: true,
    });
    function raf(time) {
      lenis.raf(time);
      requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);

    // Lenis controla el scroll frame a frame: sin esto, cualquier salto de
    // ancla nativo (nav, skip-link, footer) queda "peleado" y vuelve a 0.
    document.querySelectorAll('a[href^="#"]').forEach((link) => {
      link.addEventListener('click', (e) => {
        const id = link.getAttribute('href');
        if (!id || id === '#') return;
        const target = document.querySelector(id);
        if (!target) return;
        e.preventDefault();
        lenis.scrollTo(target, { offset: -84, duration: 1.1 });
      });
    });
  }

  /* ---- Carrusel de logos reales (clientes / partners) ----
     Envuelve el grid existente (con sus <a> e <img> reales de site.css)
     en un contenedor con máscara + duplica los items una vez para el loop
     infinito seamless. El set clonado se oculta de lectores de pantalla
     y teclado para no anunciar/tabear cada logo dos veces. */
  document.querySelectorAll('.clients-grid, .partner-grid').forEach((grid) => {
    if (grid.closest('.marquee-wrap')) return;
    const wrap = document.createElement('div');
    wrap.className = 'marquee-wrap';
    grid.parentNode.insertBefore(wrap, grid);
    wrap.appendChild(grid);
    if (!reduceMotion) {
      Array.from(grid.children).forEach((child) => {
        const clone = child.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        clone.querySelectorAll('a').forEach((a) => a.setAttribute('tabindex', '-1'));
        if (clone.matches('a')) clone.setAttribute('tabindex', '-1');
        grid.appendChild(clone);
      });
    }
  });

  /* ---- Ripple click effect en botones ---- */
  document.querySelectorAll('.btn').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      const rect = btn.getBoundingClientRect();
      const ripple = document.createElement('span');
      ripple.className = 'ripple';
      ripple.style.left = `${e.clientX - rect.left}px`;
      ripple.style.top = `${e.clientY - rect.top}px`;
      btn.appendChild(ripple);
      setTimeout(() => ripple.remove(), 650);
    });
  });

})();
