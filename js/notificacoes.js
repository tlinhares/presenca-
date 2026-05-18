/**
 * Sistema de Notificações - Compatibilidade
 * Arquivo de compatibilidade para o sistema de notificações
 */

// Verificar se o sistema de feedback já existe
if (typeof window.feedbackSystem === 'undefined') {
    // Sistema simples de fallback
    window.exibirToast = function(message, type = 'info', options = {}) {
        console.log(`[${type.toUpperCase()}] ${message}`);
        
        // Criar toast simples se possível
        if (typeof $ !== 'undefined') {
            const alertClass = type === 'success' ? 'alert-success' : 
                             type === 'danger' ? 'alert-danger' : 
                             type === 'warning' ? 'alert-warning' : 'alert-info';
            
            const toast = $(`
                <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                     style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);
            
            $('body').append(toast);
            
            // Auto-remove após 5 segundos
            setTimeout(() => {
                toast.alert('close');
            }, 5000);
        }
    };
}

// Funções de conveniência
window.exibirToastSuccess = function(message, options = {}) {
    return window.exibirToast(message, 'success', options);
};

window.exibirToastError = function(message, options = {}) {
    return window.exibirToast(message, 'danger', options);
};

window.exibirToastWarning = function(message, options = {}) {
    return window.exibirToast(message, 'warning', options);
};

window.exibirToastInfo = function(message, options = {}) {
    return window.exibirToast(message, 'info', options);
};