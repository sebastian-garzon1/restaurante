document.addEventListener('DOMContentLoaded', function() {
    // Producto
    const modal = new bootstrap.Modal(document.getElementById('nuevoProductoModal'));
    const formProducto = document.getElementById('formProducto');
    const buscarProducto = document.getElementById('buscarProducto');
    // Inusmo
    const modalInsumos = new bootstrap.Modal(document.getElementById('nuevoInsumoModal'));
    const formInsumo = document.getElementById('formInsumo');
    const buscarInsumo = document.getElementById('buscarInsumo');

    let timeoutInsumo;
    let timeoutId;
    
    // Manejar búsqueda de productos con debounce
    buscarProducto.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        
        // Limpiar el timeout anterior
        clearTimeout(timeoutId);
        
        // Si el término de búsqueda está vacío, mostrar todos los productos
        if (!searchTerm) {
            document.querySelectorAll('#productosTabla tr').forEach(row => {
                row.style.display = '';
            });
            return;
        }
        
        // Esperar 300ms antes de realizar la búsqueda
        timeoutId = setTimeout(() => {
            document.querySelectorAll('#productosTabla tr').forEach(row => {
                const codigo = row.cells[0].textContent.toLowerCase();
                const nombre = row.cells[1].textContent.toLowerCase();
                row.style.display = 
                    codigo.includes(searchTerm) || nombre.includes(searchTerm) 
                        ? '' 
                        : 'none';
            });
        }, 300);
    });

    // Teclas rápidas
    document.addEventListener('keydown', function(e) {
        // Evitar que las teclas rápidas se activen cuando se está escribiendo en un input
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }

        if (e.ctrlKey || e.metaKey) { // Ctrl en Windows/Linux o Cmd en Mac
            switch(e.key.toLowerCase()) {
                case 'b': // Ctrl/Cmd + B para buscar producto
                    e.preventDefault();
                    buscarProducto.focus();
                    break;
                case 'n': // Ctrl/Cmd + N para nuevo producto
                    e.preventDefault();
                    modal.show();
                    document.getElementById('codigo').focus();
                    break;
            }
        } else if (!e.ctrlKey && !e.metaKey && !e.altKey && !e.shiftKey) {
            // Tecla '/' para buscar (sin modificadores)
            if (e.key === '/') {
                e.preventDefault();
                buscarProducto.focus();
            }
        }
    });

    // Manejar guardado de producto
    document.getElementById('guardarProducto').addEventListener('click', async function() {
        if (!formProducto.checkValidity()) {
            formProducto.reportValidity();
            return;
        }

        const productoData = {
            codigo: document.getElementById('codigo').value,
            nombre: document.getElementById('nombre').value,
            precio_unidad: parseFloat(document.getElementById('precioUnidad').value) || 0,
            cocina: document.getElementById('chkServicio').checked ? 1 : 0
        };

        const productoId = document.getElementById('productoId').value;
        const url = productoId ? `/api/productos/${productoId}` : '/api/productos';
        const method = productoId ? 'PUT' : 'POST';

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: JSON.stringify(productoData)
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Error al guardar el producto');
            }

            location.reload();
        } catch (error) {
            alert(error.message);
        }
    });

    // Limpiar formulario al abrir modal para nuevo producto
    document.getElementById('nuevoProductoModal').addEventListener('show.bs.modal', function(event) {
        if (!event.relatedTarget) return; // Si se abre para editar, no limpiar
        
        document.getElementById('productoId').value = '';
        document.getElementById('formProducto').reset();
        document.getElementById('modalTitle').textContent = 'Nuevo Producto';
        
        // Enfocar el campo de código después de que el modal se muestre completamente
        setTimeout(() => {
            document.getElementById('codigo').focus();
        }, 500);
    });

    // Búsqueda de clientes
    $('#insumo').on('keyup', function() {
        clearTimeout(timeoutInsumo);
        const valor = $(this).val();
        const id = $("#productoId").val();
        
        if (valor.length < 2) return;

        timeoutInsumo = setTimeout(() => {
            $.ajax({
                url: '/api/inventario/buscar',
                data: { id: id, q: valor },
                success: function(insumos) {
                    mostrarListaInsumos(insumos);
                }
            });
        }, 300);
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.search-container').length) {
            $('.search-results').remove();
        }
    });


    // Función para mostrar lista de insumos
    function mostrarListaInsumos(insumos) {
        // elimina resultados anteriores
        $('.search-results').remove();

        const lista = $('<ul class="list-group search-results">');

        insumos.forEach(insumo => {
            const item = $('<li class="list-group-item d-flex justify-content-between align-items-center gap-2">');

            // Texto
            const texto = $('<span class="flex-grow-1">').text(
                `${insumo.nombre}${insumo.descripcion ? ' - ' + insumo.descripcion : ''}`
            );

            // Badge stock
            const badge = $('<span class="badge text-bg-primary rounded-pill me-2">')
                .text(insumo.stock);

            // Botón agregar
            const btnAgregar = $(`
                <button class="btn btn-sm btn-success" title="Agregar">
                    <i class="bi bi-plus-circle"></i>
                </button>
            `);

            // Click del botón (API)
            btnAgregar.on('click', function (e) {
                e.stopPropagation();
                e.preventDefault();
                
                var producto =  $("#productoId").val();
                agregarInsumo(producto, insumo.id);
            });

            item.append(texto, badge, btnAgregar);
            lista.append(item);
        });

        $('#insumo').closest('.search-container').append(lista);
    }

    // Agregar tooltips para mostrar las teclas rápidas
    const tooltips = [
        { 
            element: buscarProducto, 
            title: 'Teclas rápidas: Ctrl+B o /'
        },
        {
            element: document.querySelector('[data-bs-target="#nuevoProductoModal"]'),
            title: 'Tecla rápida: Ctrl+N'
        }
    ];

    tooltips.forEach(({element, title}) => {
        if (element) {
            element.setAttribute('title', title);
            new bootstrap.Tooltip(element);
        }
    });

    document.querySelector('.table').addEventListener('click', function(e) {
        const button = e.target.closest('button');
        if (!button) return;

        const id = button.dataset.id;
        const action = button.dataset.action;

        if (action === 'editar') {
            alert("editar")
            editarProducto(id);
        } else if (action === 'eliminar') {
            eliminarProducto(id);
        } else if (action === 'insumos'){
            const nombre = button.dataset.name;
            
            verInsumos(id);

            document.getElementById('productoId').value = id;
            document.getElementById('modalTitleInsumo').textContent = nombre;
            const modal = new bootstrap.Modal(document.getElementById('nuevoInsumoModal'));
            modal.show();
        }
    });

    document.getElementById('btnDescargarPlantilla').onclick = function() {
        window.open('/productos/plantilla', '_blank');
    };

    const input = document.getElementById('archivoImport');
    document.getElementById('btnImportarProductos').onclick = () => input.click();

    input.addEventListener('change', async function(){
        if (!this.files.length) return;
        const fd = new FormData();
        fd.append('archivo', this.files[0]);

        const btn = document.getElementById('btnImportarProductos');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importando...';

        try {
            const resp = await fetch('/productos/importar', { method:'POST', body: fd });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || "Error al importar");
            alert("Importación completa: " + data.inserted + " filas.");
            location.reload();
        } catch(err) {
            alert(err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-upload"></i> Importar Excel';
            this.value = '';
        }
    });
});

async function agregarInsumo(productoId, insumoId){
    try {
        const insumoData = {
            producto: productoId,
            insumo: insumoId,
        };

        const response = await fetch("/api/inventario/insumo_producto", {
            method: "POST",
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: JSON.stringify(insumoData)
        });

        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.error || 'Error al guardar el insumo en el producto');
        }

        verInsumos(productoId);
        $('.search-results').remove();
    } catch (error) {
        alert(error.message);
    }
}

// Función para editar producto
function editarProducto(id) {
    fetch(`/api/productos/${id}`)
        .then(response => response.json())
        .then(producto => {
            document.getElementById('productoId').value = producto.id;
            document.getElementById('codigo').value = producto.codigo;
            document.getElementById('nombre').value = producto.nombre;
            document.getElementById('precioUnidad').value = producto.precio_unidad;
            document.getElementById('chkServicio').checked = producto.cocina == 1;
            
            document.getElementById('modalTitle').textContent = 'Editar Producto';
            const modal = new bootstrap.Modal(document.getElementById('nuevoProductoModal'));
            modal.show();
        })
        .catch(error => alert('Error al cargar el producto'));
}

// Función para eliminar producto
function eliminarProducto(id) {
    if (!confirm('¿Está seguro de eliminar este producto?')) {
        return;
    }

    fetch(`/api/productos/${id}`, {
        method: 'DELETE'
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al eliminar el producto');
            }
            location.reload();
        })
        .catch(error => alert(error.message));
}

function verInsumos(id){
    fetch(`/api/inventario/insumos/${id}`)
        .then(response => response.json())
        .then(productos => {
            const listaProductos = Array.isArray(productos) ? productos : [productos];
            let table_insumos = `
                <div class="text-center">
                    Este producto no tiene insumos asignados
                </div>
            `;

            if (listaProductos && listaProductos.length > 0) {
                if( !listaProductos[0]["error"] ){
                    table_insumos = `
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Stock</th>
                                    <th>Uso</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                    `;

                    listaProductos.forEach(producto => {
                        table_insumos += `
                            <tr>
                                <td>${producto.nombre}</td>
                                <td>${producto.descripcion ?? ''}</td>
                                <td>${producto.stock}</td>
                                <td>
                                    <input class="form-control form-control-sm" type="number" min="0" value="${producto.cantidad}" onchange="cambiarCantidad(this, ${producto.inventario_id})">
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger"
                                            data-id="${producto.inventario_id}"
                                            data-action="eliminar-insumo">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    table_insumos += `
                            </tbody>
                        </table>
                    `;
                }

                document.getElementById('tbl-insumos').innerHTML = table_insumos;
            }else{
                table_insumos += `
                    <div class="text-center">
                        No tiene insumos asignados
                    </div>
                    `;
            }
            
            document.getElementById('tbl-insumos').innerHTML = table_insumos;
        })
        .catch(error => alert('Error al cargar los insumos del producto ' + error));
}

$(document).on('click', '[data-action="eliminar-insumo"]', function (e) {
    e.preventDefault();
    var producto =  $("#productoId").val();
    const id = $(this).data('id');
    eliminarInsumo(id, producto);
});

async function eliminarInsumo(id, productoId){
    try {
        fetch(`/api/inventario/insumo_producto/${id}`, {
            method: 'DELETE'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al eliminar el insumo del producto');
            }
            return response.json();
        })
        .then(() => {
            verInsumos(productoId);
        })
        .catch(error => alert(error.message));
    } catch (error) {
        alert(error.message);
    }
}

async function cambiarCantidad(objeto, id_inventario){
    if( objeto.value != "" ){
        const productoData = {
            cantidad: objeto.value,
            id_inventario: id_inventario,
        };

        const url = `/api/inventario/insumo_producto/`;
        try {
            const response = await fetch(url, {
                method: "PUT",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: JSON.stringify(productoData)
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Error al guardar el insumo');
            }
        } catch (error) {
            alert(error.message);
        }
    } else {
        Swal.fire({
            icon: "error",
            title: "Fallo",
            html: "No puedes dejar el campo vacio.",
            timer: 3000
        });
        setInterval("location.reload()",3000);
    }
}