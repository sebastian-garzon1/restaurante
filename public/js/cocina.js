// JS de Cocina: muestra cola y permite avanzar estados
// Relacionado con: views/cocina.ejs, routes/cocina.js, routes/mesas.js
let colaPrevios = new Set();
let audioHabilitado = false;

const sonidoNuevo = new Audio("/sounds/nuevo-item.mp3");
sonidoNuevo.volume = 0.6;

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const tipo = params.get('tipo') || '';

    document.querySelectorAll('.btn-group a').forEach(btn => {
        btn.classList.toggle(
            'active',
            btn.dataset.tipo === tipo
        );
    });
});

// habilitar audio tras interacción
document.addEventListener("click", () => {
    audioHabilitado = true;
}, { once: true });

$(function () {
	// Permitir abrir directamente pestaña con ?tab=listos
	function activarTabDesdeQuery() {
		const params = new URLSearchParams(window.location.search);
		const tab = params.get('tab');
		if (tab === 'listos') {
			const triggerEl = document.querySelector('#tabListos-tab');
			if (triggerEl) {
				const tabObj = new bootstrap.Tab(triggerEl);
				tabObj.show();
			}
		}
	}

	async function cargarCola() {
		const params = window.location.search;
		const resp = await fetch('/api/cocina/cola'+params);
		const items = await resp.json();
		render(items);
	}

	function minutosDesde(fecha) {
		const inicio = new Date(fecha);
		const ahora = new Date();

		const diffMs = ahora - inicio; // diferencia en milisegundos
		return Math.floor(diffMs / 60000); // minutos
	}

	function cardItem(it) {
		var minutosTrans = minutosDesde(it.created_at);
		const badgeTiempo = minutosTrans < 25 ? 'success' :  minutosTrans < 35 ? 'warning' : 'danger';
		const badge = it.estado === 'preparando' ? 'warning' : (it.estado === 'listo' ? 'success' : 'secondary');
		const headerLeft = `
			<div>
				<div class="small text-muted">Mesa ${it.mesa_numero}</div>
				<div class="producto">${it.producto_nombre}</div>
				${it.nota ? `<div class="mt-1 fw-bold text-danger">${it.nota}</div>` : ''}
				<div class="small">${new Date(it.created_at).toLocaleTimeString()}· ${minutosTrans} min</div>
			</div>`;

		const qtyBadge = `<span class="badge text-bg-${badgeTiempo} cantidad-badge">${Math.round(it.cantidad)}</span>`;
		const actions = `
			<div class="mt-2 d-flex gap-2">
				${it.estado === 'enviado' ? `<button class="btn btn-sm btn-primary" data-action="prep" data-id="${it.id}"><i class="bi bi-play"></i> Preparar</button>` : ''}
				${it.estado === 'preparando' ? `<button class="btn btn-sm btn-success" data-action="listo" data-id="${it.id}"><i class="bi bi-check2"></i> Listo</button>` : ''}
				${it.estado === 'listo' ? `<button class="btn btn-sm btn-outline-dark" data-action="servido" data-id="${it.id}"><i class="bi bi-box-seam"></i> Recogido</button>` : ''}
			</div>`;

		return `
		<div class="card card-cocina">
			<div class="card-body">
			<div class="d-flex justify-content-between align-items-center">
				${headerLeft}
				${qtyBadge}
			</div>
				${actions}
			</div>
		</div>`;
	}

	function render(items) {
		const cola = $('#listaCola').empty();
		const listos = $('#listaListos').empty();

		let hayNuevo = false;

		// IDs actuales en cola
		const colaActual = new Set();

		items.forEach(it => {
			colaActual.add(it.id);

			// Si ahora está en cola y antes NO estaba → nuevo en cocina
			if (!colaPrevios.has(it.id)) {
				hayNuevo = true;
			}
		});

		// Actualizamos estado previo
		colaPrevios = colaActual;

		if (hayNuevo) {
			sonidoNuevo.currentTime = 0;
			sonidoNuevo.play().catch(() => {});
		}

		// Render cola
		items.filter(it => it.estado !== 'listo')
			.forEach(it => cola.append(cardItem(it)));

		// Render listos
		const porMesa = new Map();
		items
			.filter(it => it.estado === 'listo')
			.forEach(it => {
				listos.append(
					`<div class="item-row col-md-3">${cardItem(it)}</div>`
				);
			});
	}

	// Acciones
	$(document).on('click', '[data-action="prep"]', async function () {
		const id = this.dataset.id;
		await fetch(`/api/cocina/item/${id}/estado`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ estado: 'preparando' }) });
		await cargarCola();
	});

	$(document).on('click', '[data-action="listo"]', async function () {
		const id = this.dataset.id;
		await fetch(`/api/cocina/item/${id}/estado`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ estado: 'listo' }) });
		await cargarCola();
	});

	$(document).on('click', '[data-action="servido"]', async function () {
		const id = this.dataset.id;
		await fetch(`/api/mesas/items/${id}/estado`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ estado: 'servido' }) });
		await cargarCola();
	});

	// Auto-refresh
	cargarCola();
	setInterval(cargarCola, 2500);
	activarTabDesdeQuery();
});


