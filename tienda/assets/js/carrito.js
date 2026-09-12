// Carrito de compras — vive en localStorage del navegador, sin login.
// Formato guardado: { "sku": cantidad, ... }
const Carrito = (() => {
  const KEY = 'intelix_carrito';

  function leer() {
    try {
      return JSON.parse(localStorage.getItem(KEY)) || {};
    } catch {
      return {};
    }
  }

  function guardar(carrito) {
    localStorage.setItem(KEY, JSON.stringify(carrito));
    document.dispatchEvent(new CustomEvent('carrito:cambio', { detail: carrito }));
  }

  function agregar(sku, cantidad = 1) {
    const carrito = leer();
    carrito[sku] = (carrito[sku] || 0) + cantidad;
    guardar(carrito);
  }

  function setCantidad(sku, cantidad) {
    const carrito = leer();
    if (cantidad <= 0) {
      delete carrito[sku];
    } else {
      carrito[sku] = cantidad;
    }
    guardar(carrito);
  }

  function quitar(sku) {
    const carrito = leer();
    delete carrito[sku];
    guardar(carrito);
  }

  function vaciar() {
    guardar({});
  }

  function totalItems() {
    return Object.values(leer()).reduce((a, b) => a + b, 0);
  }

  return { leer, agregar, setCantidad, quitar, vaciar, totalItems };
})();
