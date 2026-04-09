// JS de Mesas: UI para abrir/gestionar pedidos por mesa y enviar a cocina
// Relacionado con: views/mesas.ejs, routes/mesas.js, routes/productos.js, routes/facturas.js

$(function () {
	mesaModal = new bootstrap.Modal(document.getElementById('mesaModal'));
	const canvas = new bootstrap.Offcanvas("#canvasPedido");
	let pedidoActual = null; // { id, mesa_id }
	let items = []; // items del pedido en UI

	// Helpers UI
	function formatear(valor) {
		return `$${Number(valor || 0).toLocaleString("es-CO")}`;
	}
	function renderItems() {
		const tbody = $("#tbodyItems");
		tbody.empty();
		let total = 0;
		items.forEach((it, idx) => {
			const cantidad = Number(it.cantidad || 0);
			const precio = Number(
				(it.precio_unitario != null ? it.precio_unitario : it.precio) || 0
			);
			const subtotal = Number(
				it.subtotal != null ? it.subtotal : cantidad * precio
			);
			total += subtotal;
			tbody.append(`
        <tr data-item-id="${it.id}">
          <td>${it.producto_nombre || it.nombre || it.producto_id}</td>
          <td class="text-end">${cantidad}</td>
          <td class="text-end">${formatear(precio)}</td>
          <td class="text-end">${formatear(subtotal)}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-danger btnEliminarItem" data-idx="${idx}"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
      `);
		});
		$("#totalPedido").text(formatear(total));
	}

	// Cargar pedido por mesa
	async function abrirPedido(mesaId, mesaNumero) {
		try {
			const resp = await fetch("/api/mesas/abrir", {
				method: "POST",
				headers: {
					"Content-Type": "application/x-www-form-urlencoded",
				},
				body: JSON.stringify({ mesa_id: mesaId }),
			});
			const data = await resp.json();
			if (!resp.ok) throw new Error(data.error || "Error al abrir pedido");
			pedidoActual = data.pedido;
			$("#pedidoMesa").text(mesaNumero);
			await cargarPedido(pedidoActual.id);
			canvas.show();
		} catch (err) {
			Swal.fire({ icon: "error", title: err });
		}
	}

	async function cargarPedido(pedidoId) {
		const resp = await fetch(`/api/mesas/pedidos/${pedidoId}`);
		const data = await resp.json();
		if (!resp.ok) throw new Error(data.error || "Error al cargar pedido");
		items = data.items || [];
		renderItems();
	}

	// Buscar productos
	let to;
	$("#buscarProductoMesa").on("input", function () {
		clearTimeout(to);
		const q = this.value.trim();
		if (q.length < 2) {
			$("#resultadosProductoMesa").empty();
			return;
		}
		to = setTimeout(async () => {
			const resp = await fetch(
				`/api/productos/buscar?q=${encodeURIComponent(q)}`
			);
			const productos = await resp.json();
			const list = $("#resultadosProductoMesa");
			list.empty();
			productos.forEach((p) => {
				const item = $(`
          <a href="#" class="list-group-item list-group-item-action">
            <div><strong>${p.codigo}</strong> - ${p.nombre}</div>
            <div class="small text-muted">UND: $${p.precio_unidad}</div>
          </a>`);
				item.on("click", (e) => {
					e.preventDefault();
					$("#resultadosProductoMesa").empty();
					$("#buscarProductoMesa").val("");
					seleccionarProducto(p);
				});
				list.append(item);
			});
		}, 250);
	});

	// Selección rápida: UND por defecto + nota para cocina (oculta offcanvas durante todo el flujo)
	async function seleccionarProducto(p) {
		await runWithOffcanvasHidden(async () => {
			const cantidadRes = await Swal.fire({
				title: `Cantidad para ${p.nombre}`,
				input: "number",
				inputValue: 1,
				inputAttributes: { step: "1", min: "1" },
				showCancelButton: true,
				didOpen: () => {
					const inp = document.querySelector(".swal2-input");
					if (inp) {
						[
							"keydown",
							"keyup",
							"keypress",
							"paste",
							"copy",
							"cut",
							"contextmenu",
						].forEach((evt) => {
							inp.addEventListener(evt, (e) => e.stopPropagation());
						});
					}
				},
			});
			if (!cantidadRes.value) return;

			const notaRes = await Swal.fire({
				title: "Nota para cocina (opcional)",
				input: "text",
				inputPlaceholder: "Ej: sin cebolla, sin queso...",
				showCancelButton: true,
				didOpen: () => {
					const inp = document.querySelector(".swal2-input");
					if (inp) {
						[
							"keydown",
							"keyup",
							"keypress",
							"paste",
							"copy",
							"cut",
							"contextmenu",
						].forEach((evt) => {
							inp.addEventListener(evt, (e) => e.stopPropagation());
						});
					}
				},
			});
			const unidad = "UND";
			const precio = p.precio_unidad;
			const body = {
				producto_id: p.id,
				cantidad: Number(cantidadRes.value),
				unidad,
				precio: Number(precio),
				nota: notaRes.value || "",
			};
			const resp = await fetch(`/api/mesas/pedidos/${pedidoActual.id}/items`, {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify(body),
			});
			const data = await resp.json();
			if (!resp.ok)
				return Swal.fire({
					icon: "error",
					title: data.error || "Error al agregar",
				});
			await cargarPedido(pedidoActual.id);
			// limpiar y enfocar el buscador para el siguiente producto
			$("#buscarProductoMesa").val("").focus();
		});
	}

	// Enviar todos los items pendientes a cocina
	$("#btnEnviarCocina").on("click", async function () {
		try {
			const pendientes = items.filter((i) => i.estado === "pendiente");
			for (const it of pendientes) {
				await fetch(`/api/mesas/items/${it.id}/enviar`, { method: "PUT" });
			}
			await cargarPedido(pedidoActual.id);
			Swal.fire({ icon: "success", title: "Enviado a cocina" });
		} catch (err) {
			Swal.fire({ icon: "error", title: "No se pudo enviar a cocina" });
		}
	});

	// Mover pedido a otra mesa (handler compartido)
	async function handleMoverMesa() {
		try {
			// Obtener mesas disponibles
			const resp = await fetch("/api/mesas/listar");
			const mesas = await resp.json();
			const libres = mesas.filter(
				(m) => (m.pedidos_abiertos || 0) === 0 && m.id !== pedidoActual.mesa_id
			);
			if (libres.length === 0) {
				return Swal.fire({ icon: "info", title: "No hay mesas libres" });
			}

			const options = libres.reduce((acc, m) => {
				acc[m.id] = `Mesa ${m.numero}${m.descripcion ? " - " + m.descripcion : ""
					}`;
				return acc;
			}, {});
			const { value: destino } = await runWithOffcanvasHidden(async () => {
				return await Swal.fire({
					title: "Mover a mesa",
					input: "select",
					inputOptions: options,
					inputPlaceholder: "Seleccione mesa destino",
					showCancelButton: true,
				});
			});
			if (!destino) return;

			const r = await fetch(`/api/mesas/pedidos/${pedidoActual.id}/mover`, {
				method: "PUT",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify({ mesa_destino_id: Number(destino) }),
			});
			const data = await r.json();
			if (!r.ok) throw new Error(data.error || "No se pudo mover el pedido");

			// Actualizar etiqueta de mesa y recargar items
			const mesaSel = libres.find((m) => m.id === Number(destino));
			if (mesaSel) {
				$("#pedidoMesa").text(mesaSel.numero);
			}
			await cargarPedido(pedidoActual.id);
			Swal.fire({ icon: "success", title: "Pedido movido" });
		} catch (err) {
			Swal.fire({ icon: "error", title: err.message });
		}
	}

	$("#btnMoverMesa").on("click", handleMoverMesa);
	$("#btnMoverMesaHeader").on("click", handleMoverMesa);

	// ====== Estado en vivo de mesas (sin recargar) ======
	async function refreshMesas() {
		try {
			const resp = await fetch("/api/mesas/listar");
			const mesas = await resp.json();
			if (!Array.isArray(mesas)) return;
			mesas.forEach((m) => {
				const card = document.querySelector(
					`.mesa-card[data-mesa-id="${m.id}"]`
				);
				if (!card) return;
				const badge = card.querySelector(".estado-badge");
				if (badge) {
					badge.textContent = m.estado;
					badge.classList.remove("bg-success", "bg-warning", "bg-secondary");
					badge.classList.add(
						m.estado === "libre"
							? "bg-success"
							: m.estado === "ocupada"
								? "bg-warning"
								: "bg-secondary"
					);
				}
			});
		} catch (_) {
			/* ignorar errores de red */
		}
	}

	// refrescar cada 3s
	setInterval(refreshMesas, 3000);
	// primera carga
	refreshMesas();

	// Facturar pedido
	$("#btnFacturarPedido").on("click", async function () {
		try {
			const resultado = await runWithOffcanvasHidden(async () => {
				// CLIENTE
				const cliente = await seleccionarClienteConBusqueda();
				if (!cliente) return null;
				const cliente_id = cliente.id;

				// FORMA DE PAGO
				const { value: forma_pago } = await Swal.fire({
					title: "Forma de pago",
					input: "radio",
					inputOptions: {
						efectivo: "Efectivo",
						transferencia: "Transferencia",
						tarjeta: "Tarjeta",
					},
					inputValue: "efectivo",
					showCancelButton: true,
					confirmButtonText: "Aceptar",
					allowOutsideClick: false
				});
				if (!forma_pago) return null;

				// SERVICIO
				const { value: servicio } = await Swal.fire({
					title: "Servicio",
					html: `
						<div style="text-align:left">
							<label>
								<input type="checkbox" id="chkServicio" checked />
								Confirmo el aporte por servicio
							</label>

							<div id="servicioContainer" style="margin-top:10px;">
								<input 
									type="number" 
									id="servicio" 
									class="swal2-input"
									placeholder="Valor del servicio (opcional)"
									min="0"
								/>
								<small style="color:#666">
									Si no ingresas un valor, se aplicará el 10%
								</small>
							</div>
						</div>
					`,
					showCancelButton: true,
					confirmButtonText: "Aceptar",
					allowOutsideClick: false,
					didOpen: () => { 
						const chk = document.getElementById('chkServicio');
						const container = document.getElementById('servicioContainer');

						container.style.display = chk.checked ? 'block' : 'none';

						chk.addEventListener('change', () => {
							container.style.display = chk.checked ? 'block' : 'none';
						});	
					},
					preConfirm: () => { 
						const activo = document.getElementById('chkServicio').checked;
						const valorInput = document.getElementById('servicio').value;

						return {
							activo,
							valor: activo && valorInput
								? parseFloat(valorInput)
								: null // null indica "usar 10%"
						};	
					}
				});
				if (!servicio) return null;

				var servicioEstado = servicio.activo;
				var servicioValor = servicio.valor;

				// DESCUENTO
				const { value: descuento } = await Swal.fire({
					title: "Descuento",
					html: `
						<div style="text-align:left">
							<label>
								<input type="checkbox" id="chkDescuento">
								Aplicar descuento
							</label>

							<div id="descuentoContainer" style="margin-top:10px; display:none;">
								<input 
									type="number" 
									id="valorDescuento" 
									class="swal2-input"
									placeholder="Valor del descuento"
									min="0"
								/>
							</div>
						</div>
					`,
					showCancelButton: true,
					confirmButtonText: "Aceptar",
					allowOutsideClick: false,
					didOpen: () => { 
						const chk = document.getElementById('chkDescuento');
						const container = document.getElementById('descuentoContainer');

						chk.checked = false;
						container.style.display = 'none';

						chk.addEventListener('change', () => {
							container.style.display = chk.checked ? 'block' : 'none';
						});
					},
					preConfirm: () => { 
						const valor = document.getElementById('valorDescuento').value;

						return parseFloat(valor || 0);	
					}
				});

				// Si canceló o no activó descuento
				if (descuento === null) return;

				return {
					cliente_id, forma_pago, servicioEstado, servicioValor, descuento
				};
			});

			if (!resultado) return;

			const { cliente_id, forma_pago, servicioEstado, servicioValor, descuento } = resultado;

			// REVISION DE LA FACTURA
			const previewResp = await fetch(
				`/api/mesas/pedidos/${pedidoActual.id}/preview-factura`,
				{
					method: "POST",
					headers: { "Content-Type": "application/json" },
					body: JSON.stringify({ cliente_id, forma_pago, servicioEstado, servicioValor, descuento }),
				}
			);

			const preview = await previewResp.json();
			if (!previewResp.ok) throw new Error(preview.error || "Error en preview");

			const confirm = await Swal.fire({
				title: "Previsualización de factura",
				width: 600,
				html: `
					<div style="text-align:left">
						<b>Cliente:</b> ${preview.cliente}<br>
						<b>Forma de pago:</b> ${forma_pago}<br><br>

						<table class="table table-sm">
							<thead>
								<tr>
									<th>Producto</th>
									<th>Cant</th>
									<th>Total</th>
								</tr>
							</thead>
							<tbody>
								${preview.items.map(i => `
									<tr>
										<td>${i.nombre}</td>
										<td>${i.cantidad}</td>
										<td>$${i.total.toFixed(2)}</td>
									</tr>
								`).join('')}
							</tbody>
						</table>

						<hr>
						<div class="text-end">Subtotal: $${preview.subtotal.toFixed(2)}</div>
						<div class="text-end">Descuento: $${preview.descuento.toFixed(2)}</div>
						<div class="text-end">Servicio: $${preview.servicio.toFixed(2)}</div>
						<div class="text-end">Tarjeta: $${preview.tarjeta.toFixed(2)}</div>
						<h5 class="text-end">TOTAL: $${preview.total.toFixed(2)}</h5>
					</div>
				`,
				showCancelButton: true,
				confirmButtonText: "✅ Facturar",
				cancelButtonText: "❌ Volver"
			});

			if (!confirm.isConfirmed) return;

			const resp = await fetch(
				`/api/mesas/pedidos/${pedidoActual.id}/facturar`,
				{
					method: "POST",
					headers: { "Content-Type": "application/json" },
					body: JSON.stringify({ cliente_id, forma_pago, servicioEstado, servicioValor, descuento }),
				}
			);
			const data = await resp.json();
			if (!resp.ok) throw new Error(data.error || "Error al facturar");
			window.location.href = `/facturas/${data.factura_id}/imprimir`;
		} catch (err) {
			Swal.fire({ icon: "error", title: err.message });
		}
	});

	// Ocultar temporalmente el panel lateral (offcanvas) durante modales para evitar bloquear copiar/pegar
	async function runWithOffcanvasHidden(action) {
		const el = document.getElementById("canvasPedido");
		const wasOpen = el && el.classList.contains("show");
		if (wasOpen) {
			try {
				canvas.hide();
			} catch (_) {
				/* noop */
			}
			// esperar a que termine animación
			await new Promise((r) => setTimeout(r, 250));
		}
		try {
			const result = await action();
			return result;
		} finally {
			if (wasOpen) {
				try {
					canvas.show();
				} catch (_) {
					/* noop */
				}
			}
		}
	}

	function buildPedidoResumenHtml() {
		let total = 0;
		const rows = (items || [])
			.map((it) => {
				const cantidad = Number(it.cantidad || 0);
				const precio = Number(
					(it.precio_unitario != null ? it.precio_unitario : it.precio) || 0
				);
				const subtotal = Number(
					it.subtotal != null ? it.subtotal : cantidad * precio
				);
				total += subtotal;
				const nombre = it.producto_nombre || it.nombre || "";
				return `<tr><td>${nombre}</td><td class="text-end">${cantidad}</td><td class="text-end">$${subtotal.toLocaleString(
					"es-CO"
				)}</td></tr>`;
			})
			.join("");
		return `
      <div class="border rounded p-2 mt-2" id="contenedorResumen" style="display:none;max-height:220px;overflow:auto;">
        <table class="table table-sm mb-2">
          <thead class="table-light"><tr><th>Producto</th><th class="text-end">Cant</th><th class="text-end">Subt</th></tr></thead>
          <tbody>${rows}</tbody>
          <tfoot class="table-light"><tr><th colspan="2" class="text-end">Total</th><th class="text-end">$${total.toLocaleString(
			"es-CO"
		)}</th></tr></tfoot>
        </table>
      </div>`;
	}

	// -- Helpers de cliente: búsqueda por nombre con default "Consumidor final" --
	async function getOrCreateConsumidorFinal() {
		// Buscar por nombre
		try {
			const r = await fetch("/api/clientes/buscar?q=consumidor%20final");
			const list = await r.json();
			const cf = list.find(
				(c) => (c.nombre || "").toLowerCase() === "consumidor final"
			);
			if (cf) return cf;
		} catch (_) {
			/* noop */
		}
		// Crear si no existe
		try {
			const r = await fetch("/api/clientes", {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify({ nombre: "Consumidor final" }),
			});
			if (r.ok) {
				const cf = await r.json();
				return { id: cf.id, nombre: "Consumidor final" };
			}
		} catch (_) {
			/* noop */
		}
		// Último recurso: retornar marcador para evitar bloqueo
		return { id: null, nombre: "Consumidor final" };
	}

	async function buscarClientesPorNombre(q) {
		const resp = await fetch(`/api/clientes/buscar?q=${encodeURIComponent(q)}`);
		if (!resp.ok) return [];
		return await resp.json();
	}

	async function seleccionarClienteConBusqueda() {
		const defaultCliente = await getOrCreateConsumidorFinal();
		let seleccionado = defaultCliente;
		// Bucle para permitir crear cliente y luego usarlo
		// Confirm = Usar cliente; Deny = Crear cliente; Cancel = cancelar flujo
		// Tras crear, retornamos el nuevo cliente directamente
		// Diseño con buscador y lista, y default Consumidor final
		/* eslint no-constant-condition: 0 */
		while (true) {
			const result = await Swal.fire({
				title: "Seleccionar cliente",
				html: `
          <div class="mb-2 text-start small text-muted">Predeterminado: <strong id="cfNombre">${seleccionado.nombre
					}</strong></div>
          <div class="input-group mb-2">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input id="buscarClienteMesa" class="form-control" placeholder="Buscar cliente por nombre o teléfono..." />
          </div>
          <div id="resultadosClientesMesa" class="list-group" style="max-height:260px;overflow:auto"></div>
          <button id="btnToggleResumen" class="btn btn-outline-secondary btn-sm mt-2" type="button"><i class="bi bi-receipt"></i> Ver pedido</button>
          ${buildPedidoResumenHtml()}
        `,
				showCancelButton: true,
				showDenyButton: true,
				confirmButtonText: "Usar cliente",
				denyButtonText: "Crear cliente",
				didOpen: async () => {
					const $input = document.getElementById("buscarClienteMesa");
					const $list = document.getElementById("resultadosClientesMesa");
					// Permitir copiar/pegar sin interferencia de atajos globales
					const allowClipboard = (el) => {
						[
							"keydown",
							"keyup",
							"keypress",
							"paste",
							"copy",
							"cut",
							"contextmenu",
						].forEach((evt) => {
							el.addEventListener(evt, (e) => {
								e.stopPropagation(); // no afectar por manejadores globales
							});
						});
					};
					allowClipboard($input);
					// Prefill lista con Consumidor final
					$list.innerHTML = "";
					const li = document.createElement("a");
					li.href = "#";
					li.className = "list-group-item list-group-item-action active";
					li.textContent = `${seleccionado.nombre} (predeterminado)`;
					li.onclick = (e) => {
						e.preventDefault();
						marcarSeleccion(li, seleccionado);
					};
					$list.appendChild(li);

					// Toggle resumen
					const btnRes = document.getElementById("btnToggleResumen");
					const contRes = document.getElementById("contenedorResumen");
					if (btnRes && contRes) {
						btnRes.addEventListener("click", () => {
							const visible = contRes.style.display !== "none";
							contRes.style.display = visible ? "none" : "block";
							btnRes.classList.toggle("active", !visible);
							btnRes.innerHTML = !visible
								? '<i class="bi bi-receipt"></i> Ocultar pedido'
								: '<i class="bi bi-receipt"></i> Ver pedido';
						});
					}

					let to;
					function marcarSeleccion(el, cliente) {
						seleccionado = cliente;
						document
							.querySelectorAll("#resultadosClientesMesa .list-group-item")
							.forEach((x) => x.classList.remove("active"));
						el.classList.add("active");
						document.getElementById("cfNombre").textContent = cliente.nombre;
					}
					async function doSearch() {
						const q = ($input.value || "").trim();
						if (q.length < 2) {
							return;
						}
						const res = await buscarClientesPorNombre(q);
						$list.innerHTML = "";
						if (res.length === 0) {
							const empty = document.createElement("div");
							empty.className = "list-group-item text-muted";
							empty.textContent = "Sin resultados";
							$list.appendChild(empty);
							return;
						}
						res.forEach((c) => {
							const a = document.createElement("a");
							a.href = "#";
							a.className = "list-group-item list-group-item-action";
							a.innerHTML = `<div><strong>${c.nombre
								}</strong></div><div class="small text-muted">${c.telefono || ""
								} ${c.direccion ? "• " + c.direccion : ""}</div>`;
							a.onclick = (e) => {
								e.preventDefault();
								marcarSeleccion(a, c);
							};
							$list.appendChild(a);
						});
					}
					$input.addEventListener("input", () => {
						clearTimeout(to);
						to = setTimeout(doSearch, 250);
					});
				},
			});

			if (result.isDenied) {
				// Crear cliente nuevo
				const nuevo = await Swal.fire({
					title: "Nuevo cliente",
					html: `
            <div class="text-start">
              <div class="mb-2">
                <label class="form-label small">Nombre</label>
                <input id="nuevoCliNombre" class="form-control" placeholder="Nombre del cliente" />
              </div>
              <div class="mb-2">
                <label class="form-label small">Teléfono (opcional)</label>
                <input id="nuevoCliTel" class="form-control" placeholder="Teléfono" />
              </div>
              <div class="mb-2">
                <label class="form-label small">Dirección (opcional)</label>
                <input id="nuevoCliDir" class="form-control" placeholder="Dirección" />
              </div>
            </div>
          `,
					showCancelButton: true,
					confirmButtonText: "Guardar",
					didOpen: () => {
						// Permitir copiar/pegar en todos los inputs del modal
						["nuevoCliNombre", "nuevoCliTel", "nuevoCliDir"].forEach((id) => {
							const el = document.getElementById(id);
							if (!el) return;
							[
								"keydown",
								"keyup",
								"keypress",
								"paste",
								"copy",
								"cut",
								"contextmenu",
							].forEach((evt) => {
								el.addEventListener(evt, (e) => {
									e.stopPropagation();
								});
							});
						});
					},
					preConfirm: () => {
						const nombre = (
							document.getElementById("nuevoCliNombre").value || ""
						).trim();
						const telefono = (
							document.getElementById("nuevoCliTel").value || ""
						).trim();
						const direccion = (
							document.getElementById("nuevoCliDir").value || ""
						).trim();
						if (!nombre) {
							Swal.showValidationMessage("El nombre es requerido");
							return false;
						}
						return { nombre, telefono, direccion };
					},
				});
				if (nuevo.isConfirmed) {
					const body = nuevo.value;
					try {
						const resp = await fetch("/api/clientes", {
							method: "POST",
							headers: { "Content-Type": "application/json" },
							body: JSON.stringify(body),
						});
						if (!resp.ok) {
							const e = await resp.json();
							throw new Error(e.error || "Error al crear cliente");
						}
						const data = await resp.json();
						const creado = {
							id: data.id,
							nombre: body.nombre,
							telefono: body.telefono,
							direccion: body.direccion,
						};
						await Swal.fire({ icon: "success", title: "Cliente creado" });
						return creado;
					} catch (err) {
						await Swal.fire({
							icon: "error",
							title: err.message || "Error al crear cliente",
						});
						continue; // volver al selector
					}
				} else {
					continue; // volver al selector
				}
			}

			if (result.isConfirmed) {
				return seleccionado;
			}
			// Cancelado
			return null;
		}
	}

	$("#tbodyItems").on("click", ".btnEliminarItem", function () {
		const row = $(this).closest("tr");
		const itemId = row.data("item-id");
		const idx = $(this).data("idx");
		if (itemId == null) return; // seguridad

		eliminarItem(idx, itemId, row);
	});

	async function eliminarItem(idx, itemId, row) {
		const confirm = await Swal.fire({
			icon: "warning",
			title: "¿Eliminar producto?",
			text: "Esta acción no se puede deshacer",
			showCancelButton: true,
			confirmButtonText: "Sí, eliminar",
			cancelButtonText: "Cancelar"
		});

		if (!confirm.isConfirmed) return;

		try {
			const r = await fetch(`/api/mesas/pedidos/${itemId}/items`, {
				method: "DELETE"
			});

			const data = await r.json();
			if (!r.ok) throw new Error(data.error || "No se pudo eliminar");

			row.remove();
			items = items.filter((_, i) => i !== idx);
			renderItems();

			Swal.fire({
				icon: "success",
				title: "Producto eliminado",
				timer: 1200,
				showConfirmButton: false
			});
		} catch (err) {
			Swal.fire("Error", err.message, "error");
		}
	}

	// Clicks en tarjetas de mesa
	$("#gridMesas").on("click", ".btnAbrirPedido", function () {
		const card = $(this).closest(".card");
		const mesaId = card.data("mesa-id");
		const titulo = card.find(".card-title").text().replace("Mesa ", "");
		abrirPedido(mesaId, titulo);
	});

	// Liberar mesa desde tarjeta
	$("#gridMesas").on("click", ".btnLiberarMesa", async function () {
		const card = $(this).closest(".card");
		const mesaId = card.data("mesa-id");
		const mesaNum = card.find(".card-title").text().replace("Mesa ", "");
		const ok = await Swal.fire({
			title: `Liberar mesa ${mesaNum}?`,
			text: "Solo si no tiene items activos",
			icon: "warning",
			showCancelButton: true,
			confirmButtonText: "Sí, liberar",
		});
		if (!ok.isConfirmed) return;
		try {
			const r = await fetch(`/api/mesas/${mesaId}/liberar`, { method: "PUT" });
			const data = await r.json();
			if (!r.ok) throw new Error(data.error || "No se pudo liberar");
			Swal.fire({ icon: "success", title: "Mesa liberada" }).then(() =>
				location.reload()
			);
		} catch (err) {
			Swal.fire({ icon: "error", title: err.message });
		}
	});

	// Liberar desde header del offcanvas
	$("#btnLiberarMesaHeader").on("click", async function () {
		const ok = await Swal.fire({
			title: `Liberar mesa ${$("#pedidoMesa").text()}?`,
			text: "Solo si no tiene items activos",
			icon: "warning",
			showCancelButton: true,
			confirmButtonText: "Sí, liberar",
		});
		if (!ok.isConfirmed) return;
		try {
			const r = await fetch(`/api/mesas/${pedidoActual.mesa_id}/liberar`, {
				method: "PUT",
			});
			const data = await r.json();
			if (!r.ok) throw new Error(data.error || "No se pudo liberar");
			Swal.fire({ icon: "success", title: "Mesa liberada" }).then(() =>
				location.reload()
			);
		} catch (err) {
			Swal.fire({ icon: "error", title: err.message });
		}
	});

	// Ver pedido: reutiliza abrirPedido (recupera si existe, o crea si no)
	$("#gridMesas").on("click", ".btnVerPedido", function () {
		const card = $(this).closest(".card");
		const mesaId = card.data("mesa-id");
		const titulo = card.find(".card-title").text().replace("Mesa ", "");
		abrirPedido(mesaId, titulo);
	});

	// Crear nueva mesa (rápida)
	$("#btnNuevaMesa").on("click", async function () {
		const { value: numero } = await Swal.fire({
			title: "Número de mesa",
			input: "text",
			showCancelButton: true,
		});
		if (!numero) return;
		const { value: descripcion } = await Swal.fire({
			title: "Descripción",
			input: "text",
			showCancelButton: true,
		});
		const resp = await fetch("/api/mesas/crear", {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({ numero, descripcion }),
		});
		if (!resp.ok) {
			const err = await resp.json();
			return Swal.fire({ icon: "error", title: err.error || "Error" });
		}
		Swal.fire({ icon: "success", title: "Mesa creada" }).then(() =>
			location.reload()
		);
	});

	// Editar nueva mesa (rápida)
	$(".btnEditarMesa").on("click", async function () {
		const mesaId = $(this).closest('.mesa-card').data('mesa-id');
		editarMesa(mesaId);
	});

	async function editarMesa(id) {
		$.get(`/api/mesas/${id}`, function(mesa) {
			$('#mesaId').val(mesa.id);
			$('#nombre').val(mesa.numero);
			$('#descripcion').val(mesa.descripcion);

			$('#modalTitle').text('Editar Mesa');
			mesaModal.show();
		});
	}

	$('#guardarMesa').click(async function() {
        const mesa = {
            numero: $('#nombre').val(),
            descripcion: $('#descripcion').val(),
        };

        if (!mesa.numero) {
            alert('El número de mesa es requerido');
            return;
        }

        if (!mesa.descripcion) {
            alert('La descripción es requerida');
            return;
        }

        try {
            const resp = await fetch("/api/mesas/" + $('#mesaId').val(), {
                method: "PUT",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: JSON.stringify(mesa)
            });

            const data = await resp.json();

			if (data.success) {
				Swal.fire({
					icon: "success",
					title: "Mesa Actualizada",
					timer: 1500,
					showConfirmButton: false,
				}).then(() => location.reload());
			} else {
				Swal.fire({
					icon: "error",
					title: "Error",
					text: data.error || "No se pudo guardar la mesa",
				});
			}
        } catch (error) {
            alert(error.message);
        }
    });

	// Editar nueva mesa (rápida)
	$(".btnEliminarMesa").on("click", async function () {
		const mesaId = $(this).closest('.mesa-card').data('mesa-id');
		eliminarMesa(mesaId);
	});

	async function eliminarMesa(mesaId) {
		try {
			const confirm = await Swal.fire({
				icon: "warning",
				title: "¿Eliminar Mesa?",
				text: "Esta acción no se puede deshacer",
				showCancelButton: true,
				confirmButtonText: "Sí, eliminar",
				cancelButtonText: "Cancelar"
			});

			if (!confirm.isConfirmed) return;

			const resp = await fetch("/api/mesas/eliminar", {
				method: "delete", 
				headers: {
					"Content-Type": "application/json",
				},
				body: JSON.stringify({ id: mesaId }),
			});

			const data = await resp.json();

			if (data.success) {
				Swal.fire({
					icon: "success",
					title: "Mesa eliminada",
					timer: 1500,
					showConfirmButton: false,
				}).then(() => location.reload());
			} else {
				Swal.fire({
					icon: "error",
					title: "Error",
					text: data.error || "No se pudo eliminar la mesa",
				});
			}
		} catch (err) {
			Swal.fire({ icon: "error", title: err });
		}
	}
});
