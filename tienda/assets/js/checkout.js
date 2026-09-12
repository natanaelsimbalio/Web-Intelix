// Lógica de la página de checkout: arma el resumen del pedido y llama al
// backend para generar el link de pago de Mercado Pago.
(async function () {
  const fmt = (n) => '$' + Number(n).toLocaleString('es-AR');

  const carrito = Carrito.leer();
  const skus = Object.keys(carrito);

  const resumenEl = document.getElementById('orderLines');
  const totalEl = document.getElementById('orderTotalValue');
  const form = document.getElementById('checkoutForm');
  const errorEl = document.getElementById('formError');
  const shippingWrap = document.getElementById('shippingOptions');
  const pagarBtn = document.getElementById('pagarBtn');

  if (skus.length === 0) {
    document.getElementById('checkoutWrap').innerHTML =
      '<p style="padding:60px 0;color:var(--muted)">Tu carrito está vacío. <a href="index.html" style="color:var(--gold)">Volver a la tienda</a>.</p>';
    return;
  }

  let productos = [], envios = [];
  try {
    const [rp, re] = await Promise.all([
      fetch('/api/productos.php').then((r) => r.json()),
      fetch('/api/envios.php').then((r) => r.json()),
    ]);
    productos = rp.productos || [];
    envios = re.opciones || [];
  } catch {
    errorEl.textContent = 'No se pudo cargar la tienda. Recargá la página.';
    errorEl.style.display = 'block';
    return;
  }

  const porSku = Object.fromEntries(productos.map((p) => [p.sku, p]));
  let subtotal = 0;

  // Si el stock cambió después de que se agregó al carrito (dos pestañas,
  // stock editado, etc.), avisamos acá en vez de que el usuario se entere
  // recién cuando falle el pago.
  const sinStockSuficiente = skus.filter((sku) => porSku[sku] && carrito[sku] > porSku[sku].stock);
  if (sinStockSuficiente.length > 0) {
    errorEl.textContent = 'Cambió el stock disponible de: ' + sinStockSuficiente.map((sku) => porSku[sku].nombre).join(', ') + '. Volvé al carrito y ajustá la cantidad.';
    errorEl.style.display = 'block';
    pagarBtn.disabled = true;
  }

  resumenEl.innerHTML = skus.map((sku) => {
    const p = porSku[sku];
    if (!p) return '';
    const cant = carrito[sku];
    const sub = p.precio_ars * cant;
    subtotal += sub;
    const nombre = document.createElement('span');
    nombre.textContent = `${cant}x ${p.nombre}`;
    return `<div class="order-line"><span>${nombre.innerHTML}</span><b>${fmt(sub)}</b></div>`;
  }).join('');

  shippingWrap.innerHTML = envios.map((e, i) => `
    <label class="shipping-option">
      <input type="radio" name="envio" value="${e.id}" data-precio="${e.precio}" ${i === 0 ? 'checked' : ''}>
      <span>${e.nombre}</span>
      <b>${e.precio > 0 ? fmt(e.precio) : 'Gratis'}</b>
    </label>
  `).join('');

  function actualizarTotal() {
    const sel = shippingWrap.querySelector('input:checked');
    const envio = sel ? Number(sel.dataset.precio) : 0;
    totalEl.textContent = fmt(subtotal + envio);
    document.getElementById('direccionField').style.display = sel && sel.value === 'retiro' ? 'none' : 'block';
  }
  shippingWrap.addEventListener('change', actualizarTotal);
  actualizarTotal();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorEl.style.display = 'none';
    pagarBtn.disabled = true;
    pagarBtn.querySelector('.button-text').textContent = 'Generando pago…';

    const sel = shippingWrap.querySelector('input:checked');
    const payload = {
      items: skus.map((sku) => ({ sku, cantidad: carrito[sku] })),
      envio_id: sel ? sel.value : '',
      comprador: {
        nombre: form.nombre.value.trim(),
        telefono: form.telefono.value.trim(),
        email: form.email.value.trim(),
        direccion: form.direccion.value.trim(),
      },
    };

    try {
      const res = await fetch('/api/crear-preferencia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!res.ok || !data.init_point) {
        throw new Error(data.error || 'No se pudo generar el pago.');
      }

      Carrito.vaciar();
      window.location.href = data.init_point;
    } catch (err) {
      errorEl.textContent = err.message || 'Ocurrió un error. Intentá de nuevo.';
      errorEl.style.display = 'block';
      pagarBtn.disabled = false;
      pagarBtn.querySelector('.button-text').textContent = 'Pagar con Mercado Pago';
    }
  });
})();
