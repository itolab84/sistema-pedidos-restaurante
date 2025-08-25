// Sistema de modales y alertas personalizados con Tailwind CSS
class CustomModals {
    constructor() {
        this.createModalContainer();
    }

    createModalContainer() {
        if (!document.getElementById('custom-modal-container')) {
            const container = document.createElement('div');
            container.id = 'custom-modal-container';
            container.className = 'fixed inset-0 hidden';
            container.style.zIndex = '9999'; // Higher than Bootstrap modal z-index (1055)
            document.body.appendChild(container);
        }
    }

    // Reemplaza alert()
    alert(message, type = 'info') {
        return new Promise((resolve) => {
            const modal = this.createAlertModal(message, type, resolve);
            this.showModal(modal);
        });
    }

    // Reemplaza confirm()
    confirm(message, title = 'Confirmación') {
        return new Promise((resolve) => {
            this.currentConfirmResolve = resolve;
            const modal = this.createConfirmModal(message, title);
            this.showModal(modal);
        });
    }

    // Modal de alerta personalizado
    createAlertModal(message, type) {
        const iconClass = {
            'success': 'text-green-500 fas fa-check-circle',
            'error': 'text-red-500 fas fa-exclamation-triangle',
            'warning': 'text-yellow-500 fas fa-exclamation-triangle',
            'info': 'text-blue-500 fas fa-info-circle'
        };

        const bgClass = {
            'success': 'bg-green-50 border-green-200',
            'error': 'bg-red-50 border-red-200',
            'warning': 'bg-yellow-50 border-yellow-200',
            'info': 'bg-blue-50 border-blue-200'
        };

        return `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full transform transition-all">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0">
                                <i class="${iconClass[type]} text-2xl"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-lg font-medium text-gray-900">
                                    ${type.charAt(0).toUpperCase() + type.slice(1)}
                                </h3>
                            </div>
                        </div>
                        <div class="mb-6">
                            <p class="text-sm text-gray-600">${message}</p>
                        </div>
                        <div class="flex justify-end">
                            <button onclick="customModals.closeModal()" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                                Aceptar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Modal de confirmación personalizado
    createConfirmModal(message, title) {
        return `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full transform transition-all">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0">
                                <i class="fas fa-question-circle text-yellow-500 text-2xl"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-lg font-medium text-gray-900">${title}</h3>
                            </div>
                        </div>
                        <div class="mb-6">
                            <p class="text-sm text-gray-600">${message}</p>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button onclick="customModals.resolveConfirm(false)" 
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                                Cancelar
                            </button>
                            <button onclick="customModals.resolveConfirm(true)" 
                                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                                Confirmar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    showModal(modalHTML) {
        const container = document.getElementById('custom-modal-container');
        container.innerHTML = modalHTML;
        container.classList.remove('hidden');
        
        // Prevenir scroll del body
        document.body.style.overflow = 'hidden';
    }

    closeModal() {
        const container = document.getElementById('custom-modal-container');
        container.classList.add('hidden');
        container.innerHTML = '';
        
        // Restaurar scroll del body
        document.body.style.overflow = '';
        
        if (this.currentResolve) {
            this.currentResolve(true);
            this.currentResolve = null;
        }
    }

    resolveConfirm(result) {
        if (this.currentConfirmResolve) {
            this.currentConfirmResolve(result);
            this.currentConfirmResolve = null;
        }
        this.closeModal();
    }

    // Método para mostrar notificaciones toast
    toast(message, type = 'info', duration = 3000) {
        const toastContainer = this.getToastContainer();
        const toast = this.createToast(message, type);
        
        toastContainer.appendChild(toast);
        
        // Mostrar con animación
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
        }, 100);
        
        // Ocultar después del tiempo especificado
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, duration);
    }

    getToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed top-4 right-4 space-y-2';
            container.style.zIndex = '10000'; // Higher than modals
            document.body.appendChild(container);
        }
        return container;
    }

    createToast(message, type) {
        const toast = document.createElement('div');
        
        const bgClass = {
            'success': 'bg-green-500',
            'error': 'bg-red-500',
            'warning': 'bg-yellow-500',
            'info': 'bg-blue-500'
        };

        const iconClass = {
            'success': 'fas fa-check-circle',
            'error': 'fas fa-exclamation-triangle',
            'warning': 'fas fa-exclamation-triangle',
            'info': 'fas fa-info-circle'
        };

        toast.className = `${bgClass[type]} text-white px-6 py-4 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 max-w-sm`;
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="${iconClass[type]} mr-3"></i>
                <span class="text-sm font-medium">${message}</span>
                <button onclick="this.parentNode.parentNode.remove()" class="ml-4 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        return toast;
    }
}

// Inicializar el sistema de modales
const customModals = new CustomModals();

// Sobrescribir funciones nativas (opcional)
window.customAlert = (message, type) => customModals.alert(message, type);
window.customConfirm = (message, title) => customModals.confirm(message, title);
window.customToast = (message, type, duration) => customModals.toast(message, type, duration);

// Función helper para formularios de eliminación
window.confirmDelete = async function(message = '¿Estás seguro de eliminar este elemento?') {
    return await customModals.confirm(message, 'Confirmar Eliminación');
};

// Función helper para mostrar mensajes de éxito
window.showSuccess = function(message) {
    customModals.toast(message, 'success');
};

// Función helper para mostrar mensajes de error
window.showError = function(message) {
    customModals.toast(message, 'error');
};
