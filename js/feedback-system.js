/**
 * Sistema de Feedback - Toast Notifications
 * Sistema completo de notificações toast para o sistema de presença
 */

class FeedbackSystem {
    constructor() {
        this.container = null;
        this.toasts = new Map();
        this.defaultOptions = {
            duration: 5000,
            position: 'top-right',
            showCloseButton: true,
            showProgress: true,
            animation: 'slide'
        };
        this.init();
    }

    init() {
        this.createContainer();
        this.bindEvents();
    }

    createContainer() {
        // Verificar se o container já existe
        this.container = document.getElementById('toastContainer');
        
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toastContainer';
            this.container.className = 'position-fixed top-0 end-0 p-3';
            this.container.style.zIndex = '9999';
            document.body.appendChild(this.container);
        }
    }

    bindEvents() {
        // Event listener para fechar toasts ao clicar no X
        this.container.addEventListener('click', (e) => {
            if (e.target.classList.contains('toast-close')) {
                const toastId = e.target.closest('.toast-feedback').dataset.toastId;
                this.hide(toastId);
            }
        });
    }

    show(message, type = 'info', options = {}) {
        const config = { ...this.defaultOptions, ...options };
        const toastId = this.generateId();
        
        const toast = this.createToast(toastId, message, type, config);
        this.container.appendChild(toast);
        this.toasts.set(toastId, toast);

        // Animar entrada
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        // Auto-hide se duration > 0
        if (config.duration > 0) {
            setTimeout(() => {
                this.hide(toastId);
            }, config.duration);
        }

        return toastId;
    }

    createToast(id, message, type, config) {
        const toast = document.createElement('div');
        toast.className = `toast-feedback toast-${type}`;
        toast.dataset.toastId = id;

        const icon = this.getIcon(type);
        const title = this.getTitle(type);

        toast.innerHTML = `
            <div class="toast-icon">
                <i class="${icon}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            ${config.showCloseButton ? '<button class="toast-close" type="button">&times;</button>' : ''}
            ${config.showProgress ? '<div class="toast-progress"></div>' : ''}
        `;

        // Configurar barra de progresso
        if (config.showProgress && config.duration > 0) {
            const progress = toast.querySelector('.toast-progress');
            progress.style.width = '100%';
            progress.style.transition = `width ${config.duration}ms linear`;
            setTimeout(() => {
                progress.style.width = '0%';
            }, 10);
        }

        return toast;
    }

    getIcon(type) {
        const icons = {
            success: 'bi bi-check-circle-fill',
            danger: 'bi bi-exclamation-triangle-fill',
            warning: 'bi bi-exclamation-triangle-fill',
            info: 'bi bi-info-circle-fill'
        };
        return icons[type] || icons.info;
    }

    getTitle(type) {
        const titles = {
            success: 'Sucesso',
            danger: 'Erro',
            warning: 'Atenção',
            info: 'Informação'
        };
        return titles[type] || titles.info;
    }

    hide(toastId) {
        const toast = this.toasts.get(toastId);
        if (!toast) return;

        toast.classList.add('hide');
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
            this.toasts.delete(toastId);
        }, 300);
    }

    hideAll() {
        this.toasts.forEach((toast, id) => {
            this.hide(id);
        });
    }

    generateId() {
        return 'toast_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    // Métodos de conveniência
    success(message, options = {}) {
        return this.show(message, 'success', options);
    }

    error(message, options = {}) {
        return this.show(message, 'danger', options);
    }

    warning(message, options = {}) {
        return this.show(message, 'warning', options);
    }

    info(message, options = {}) {
        return this.show(message, 'info', options);
    }
}

// Instância global
window.feedbackSystem = new FeedbackSystem();

// Função global para compatibilidade
window.exibirToast = function(message, type = 'info', options = {}) {
    return window.feedbackSystem.show(message, type, options);
};

// Funções de conveniência globais
window.exibirToastSuccess = function(message, options = {}) {
    return window.feedbackSystem.success(message, options);
};

window.exibirToastError = function(message, options = {}) {
    return window.feedbackSystem.error(message, options);
};

window.exibirToastWarning = function(message, options = {}) {
    return window.feedbackSystem.warning(message, options);
};

window.exibirToastInfo = function(message, options = {}) {
    return window.feedbackSystem.info(message, options);
};

// Auto-inicialização quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        // Sistema já foi inicializado
    });
} else {
    // DOM já está pronto
}

// Exportar para uso em módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FeedbackSystem;
}
