// Catálogo de productos y servicios — sin precios, sin carrito. Cada producto
// tiene un botón que abre WhatsApp con un mensaje precargado para consultar.
(async function () {
  const WHATSAPP_NUM = '543412280272';
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
        <a class="btn-cart-3d" data-contact="${p.sku}" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.738-.945z"/></svg>
          <span class="button-text">Consultar por WhatsApp</span>
        </a>
      </div>
    </div>
  `).join('');

  grid.addEventListener('click', (e) => {
    const dot = e.target.closest('[data-img-dot]');
    if (dot) {
      const card = dot.closest('.product-card');
      const sku = card.querySelector('[data-contact]').dataset.contact;
      const idx = Number(dot.dataset.imgDot);
      card.querySelector('.product-img img').src = porSku[sku].imagenes[idx];
      card.querySelectorAll('.img-dot').forEach((d, i) => d.classList.toggle('active', i === idx));
      return;
    }

    const btn = e.target.closest('[data-contact]');
    if (!btn) return;
    const p = porSku[btn.dataset.contact];
    if (!p) return;
    const mensaje = encodeURIComponent(`Hola, quería consultar por: ${p.nombre}`);
    btn.href = `https://wa.me/${WHATSAPP_NUM}?text=${mensaje}`;
  });
})();
