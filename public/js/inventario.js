document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('nuevoInsumoModal'));
    const formInsumo = document.getElementById('formInsumo');
    const buscarInsumo = document.getElementById('buscarInsumo');
    let timeoutId;
    
    // Manejar búsqueda de insumos con debounce
    buscarInsumo.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        
        // Limpiar el timeout anterior
        clearTimeout(timeoutId);
        
        // Si el término de búsqueda está vacío, mostrar todos los insumos
        if (!searchTerm) {
            document.querySelectorAll('#insumosTabla tr').forEach(row => {
                row.style.display = '';
            });
            return;
        }
        
        // Esperar 300ms antes de realizar la búsqueda
        timeoutId = setTimeout(() => {
            document.querySelectorAll('#insumosTabla tr').forEach(row => {
                const codigo = row.cells[0].textContent.toLowerCase();
                const nombre = row.cells[1].textContent.toLowerCase();
                const descripcion = row.cells[2].textContent.toLowerCase();
                row.style.display = 
                    codigo.includes(searchTerm) || nombre.includes(searchTerm) || descripcion.includes(searchTerm) 
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
                case 'b': // Ctrl/Cmd + B para buscar insumo
                    e.preventDefault();
                    buscarInsumo.focus();
                    break;
                case 'n': // Ctrl/Cmd + N para nuevo insumo
                    e.preventDefault();
                    modal.show();
                    document.getElementById('nombre').focus();
                    break;
            }
        } else if (!e.ctrlKey && !e.metaKey && !e.altKey && !e.shiftKey) {
            // Tecla '/' para buscar (sin modificadores)
            if (e.key === '/') {
                e.preventDefault();
                buscarInsumo.focus();
            }
        }
    });

    // Manejar guardado de insumo
    document.getElementById('guardarInsumo').addEventListener('click', async function() {
        if (!formInsumo.checkValidity()) {
            formInsumo.reportValidity();
            return;
        }

        const insumoData = {
            nombre: document.getElementById('nombre').value,
            descripcion: document.getElementById('descripcion').value,
            stock: parseFloat(document.getElementById('stock').value) || 0,
            stock_minimo: parseFloat(document.getElementById('stock_minimo').value) || 0,
            estado: document.getElementById('chkEstado').checked ? 1 : 0
        };

        const insumoId = document.getElementById('insumoId').value;
        const url = insumoId ? `/api/inventario/${insumoId}` : '/api/inventario';
        const method = insumoId ? 'PUT' : 'POST';

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: JSON.stringify(insumoData)
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Error al guardar el insumo');
            }

            location.reload();
        } catch (error) {
            alert(error.message);
        }
    });

    // Limpiar formulario al abrir modal para nuevo insumo
    document.getElementById('nuevoInsumoModal').addEventListener('show.bs.modal', function(event) {
        if (!event.relatedTarget) return; // Si se abre para editar, no limpiar
        
        document.getElementById('insumoId').value = '';
        document.getElementById('formInsumo').reset();
        document.getElementById('modalTitle').textContent = 'Nuevo Insumo';
        
        // Enfocar el campo de código después de que el modal se muestre completamente
        setTimeout(() => {
            document.getElementById('nombre').focus();
        }, 500);
    });

    // Agregar tooltips para mostrar las teclas rápidas
    const tooltips = [
        { 
            element: buscarInsumo, 
            title: 'Teclas rápidas: Ctrl+B o /'
        },
        {
            element: document.querySelector('[data-bs-target="#nuevoInsumoModal"]'),
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
            editarInsumo(id);
        } else if (action === 'eliminar') {
            eliminarInsumo(id);
        }
    });
});

// Función para editar insumo
function editarInsumo(id) {
    fetch(`/api/inventario/${id}`)
        .then(response => response.json())
        .then(insumo => {
            document.getElementById('insumoId').value = insumo.id;
            document.getElementById('nombre').value = insumo.nombre;
            document.getElementById('descripcion').value = insumo.descripcion;
            document.getElementById('stock').value = insumo.stock;
            document.getElementById('stock_minimo').value = insumo.stock_minimo;
            document.getElementById('chkEstado').checked = insumo.activo == 1;
            
            document.getElementById('modalTitle').textContent = 'Editar Insumo';
            const modal = new bootstrap.Modal(document.getElementById('nuevoInsumoModal'));
            modal.show();
        })
        .catch(error => alert('Error al cargar el Insumo' + error));
}

// Función para eliminar Insumo
function eliminarInsumo(id) {
    if (!confirm('¿Está seguro de eliminar este insumo?')) {
        return;
    }

    fetch(`/api/inventario/${id}`, {
        method: 'DELETE'
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al eliminar el insumo');
            }
            location.reload();
        })
        .catch(error => alert(error.message));
} 