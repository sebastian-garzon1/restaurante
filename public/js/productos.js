document.addEventListener('DOMContentLoaded', function() {
    // Producto
    const modal = new bootstrap.Modal(document.getElementById('nuevoProductoModal'));
    const formProducto = document.getElementById('formProducto');
    const buscarProducto = document.getElementById('buscarProducto');
    
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

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.search-container').length) {
            $('.search-results').remove();
        }
    });

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
            editarProducto(id);
        } else if (action === 'eliminar') {
            eliminarProducto(id);
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
