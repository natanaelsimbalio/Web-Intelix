// Catálogo + carrito (drawer) de la tienda.
(async function () {
  const fmt = (n) => '$' + Number(n).toLocaleString('es-AR');
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  let productos = [];
  try {
    const res = await fetch('/api/productos.php');
    const data = await res.json();
    productos = data.productos || [];
  } catch {
    document.getElementById('shopGrid').innerHTML = '<p style="color:var(--muted)">No se pudo cargar el catálogo. Probá recargar la página.</p>';
    return;
  }

  const porSku = Object.fromEntries(productos.map((p) => [p.sku, p]));

  // --- Grilla de productos ---
  const grid = document.getElementById('shopGrid');
  grid.innerHTML = productos.map((p) => `
    <div class="product-card">
      <div class="product-img">
        <img src="${p.imagenes[0]}" alt="${esc(p.nombre)}" loading="lazy">
        ${p.imagenes.length > 1 ? `
          <div class="product-img-dots">
            ${p.imagenes.map((_, i) => `<button class="img-dot${i === 0 ? ' active' : ''}" data-img-dot="${i}" aria-label="Foto ${i + 1}"></button>`).join('')}
          </div>
        ` : ''}
      </div>
      <div class="product-body">
        <h3>${esc(p.nombre)}</h3>
        <p>${esc(p.descripcion)}</p>
        <div class="product-price">${fmt(p.precio_ars)}${p.moneda === 'USD' ? `<small style="display:block;font-size:11px;color:var(--dim);font-weight:400">USD ${p.precio_usd} · dólar oficial</small>` : ''}</div>
        <button class="btn-cart-3d" data-add="${p.sku}">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h-2l-1 2v2h2l3.6 7.59-1.35 2.44c-.16.28-.25.61-.25.97 0 1.1.9 2 2 2h12v-2h-11.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1h-14.19l-.94-2h-3.81zm3 15c-1.1 0-2 .89-2 2s.9 2 2 2 2-.89 2-2-.9-2-2-2zm10 0c-1.1 0-2 .89-2 2s.9 2 2 2 2-.89 2-2-.9-2-2-2z"/></svg>
          <span class="button-text">Agregar al carrito</span>
        </button>
      </div>
    </div>
  `).join('');

  grid.addEventListener('click', (e) => {
    const dot = e.target.closest('[data-img-dot]');
    if (dot) {
      const card = dot.closest('.product-card');
      const sku = card.querySelector('[data-add]').dataset.add;
      const idx = Number(dot.dataset.imgDot);
      card.querySelector('.product-img img').src = porSku[sku].imagenes[idx];
      card.querySelectorAll('.img-dot').forEach((d, i) => d.classList.toggle('active', i === idx));
      return;
    }

    const btn = e.target.closest('[data-add]');
    if (!btn || btn.disabled) return;
    const sku = btn.dataset.add;
    const p = porSku[sku];
    const enCarrito = Carrito.leer()[sku] || 0;
    if (p && enCarrito >= p.stock) {
      sincronizarBotonesStock();
      return;
    }
    Carrito.agregar(sku, 1);
    volarAlCarrito(btn.closest('.product-card').querySelector('.product-img img'));
  });

  // Refleja en cada botón "Agregar al carrito" si ya se llegó al stock
  // disponible (según lo que ya haya en el carrito) — se corre al cargar
  // la página y cada vez que el carrito cambia, así un botón que quedó
  // "Sin más stock" se vuelve a habilitar si el usuario saca ese producto
  // del carrito.
  function sincronizarBotonesStock() {
    grid.querySelectorAll('[data-add]').forEach((btn) => {
      const p = porSku[btn.dataset.add];
      const enCarrito = Carrito.leer()[btn.dataset.add] || 0;
      const sinStock = p && enCarrito >= p.stock;
      btn.disabled = sinStock;
      btn.querySelector('.button-text').textContent = sinStock ? 'Sin más stock' : 'Agregar al carrito';
    });
  }
  sincronizarBotonesStock();
  document.addEventListener('carrito:cambio', sincronizarBotonesStock);

  // Anima una copia de la imagen del producto volando hacia el ícono del carrito.
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
  function volarAlCarrito(imgEl) {
    const fab = document.getElementById('cartOpenBtn');
    if (reduceMotion) { abrirCarrito(); return; }
    const origen = imgEl.getBoundingClientRect();
    const destino = fab.getBoundingClientRect();

    const clon = imgEl.cloneNode();
    clon.style.cssText = `
      position:fixed; z-index:300; border-radius:10px; object-fit:cover;
      left:${origen.left}px; top:${origen.top}px; width:${origen.width}px; height:${origen.height}px;
      transition:transform .55s cubic-bezier(.3,.1,.3,1), opacity .55s ease;
      pointer-events:none;
    `;
    document.body.appendChild(clon);

    requestAnimationFrame(() => {
      const dx = destino.left + destino.width / 2 - (origen.left + origen.width / 2);
      const dy = destino.top + destino.height / 2 - (origen.top + origen.height / 2);
      clon.style.transform = `translate(${dx}px, ${dy}px) scale(.15)`;
      clon.style.opacity = '0.3';
    });

    clon.addEventListener('transitionend', () => {
      clon.remove();
      fab.classList.add('cart-pop');
      setTimeout(() => fab.classList.remove('cart-pop'), 320);
      abrirCarrito();
    }, { once: true });
  }

  // --- Drawer del carrito ---
  const overlay = document.getElementById('cartOverlay');
  const drawer = document.getElementById('cartDrawer');
  const itemsEl = document.getElementById('cartItems');
  const totalEl = document.getElementById('cartTotal');
  const fabCount = document.getElementById('cartCount');

  function abrirCarrito() {
    renderCarrito();
    overlay.classList.add('open');
    drawer.classList.add('open');
  }
  function cerrarCarrito() {
    overlay.classList.remove('open');
    drawer.classList.remove('open');
  }

  function renderCarrito() {
    const carrito = Carrito.leer();

    // Si algún sku del carrito ya no existe en el catálogo (o se desactivó),
    // lo sacamos — si no, queda contando en el badge del carrito pero sin
    // mostrarse en la lista, y el total nunca cierra.
    Object.keys(carrito).forEach((sku) => {
      if (!porSku[sku]) Carrito.quitar(sku);
    });

    const carritoLimpio = Carrito.leer();
    const skus = Object.keys(carritoLimpio);
    fabCount.textContent = Carrito.totalItems();

    if (skus.length === 0) {
      itemsEl.innerHTML = '<p class="cart-empty">Tu carrito está vacío.</p>';
      totalEl.querySelector('b').textContent = fmt(0);
      return;
    }

    let total = 0;
    itemsEl.innerHTML = skus.map((sku) => {
      const p = porSku[sku];
      const cant = carritoLimpio[sku];
      const subtotal = p.precio_ars * cant;
      total += subtotal;
      const alStock = cant >= p.stock;
      return `
        <div class="cart-item">
          <img src="${p.imagenes[0]}" alt="">
          <div class="cart-item-info">
            <h4>${esc(p.nombre)}</h4>
            <div class="cart-item-price">${fmt(p.precio_ars)} c/u</div>
            <div class="cart-qty">
              <button data-dec="${sku}" aria-label="Restar">−</button>
              <span>${cant}</span>
              <button data-inc="${sku}" aria-label="Sumar" ${alStock ? 'disabled title="No queda más stock"' : ''}>+</button>
            </div>
            <button class="cart-remove" data-remove="${sku}">Quitar</button>
          </div>
        </div>
      `;
    }).join('');

    totalEl.querySelector('b').textContent = fmt(total);
  }

  itemsEl.addEventListener('click', (e) => {
    const inc = e.target.closest('[data-inc]');
    const dec = e.target.closest('[data-dec]');
    const rem = e.target.closest('[data-remove]');
    const carrito = Carrito.leer();
    if (inc) {
      const p = porSku[inc.dataset.inc];
      const actual = carrito[inc.dataset.inc] || 0;
      if (!p || actual < p.stock) Carrito.setCantidad(inc.dataset.inc, actual + 1);
    }
    if (dec) Carrito.setCantidad(dec.dataset.dec, (carrito[dec.dataset.dec] || 0) - 1);
    if (rem) Carrito.quitar(rem.dataset.remove);
  });

  document.getElementById('cartOpenBtn').addEventListener('click', abrirCarrito);
  document.getElementById('cartCloseBtn').addEventListener('click', cerrarCarrito);
  overlay.addEventListener('click', cerrarCarrito);
  document.addEventListener('carrito:cambio', renderCarrito);

  fabCount.textContent = Carrito.totalItems();
})();
