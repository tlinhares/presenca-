/**
 * AOM UI — utilidades compartilhadas do design system.
 *
 * voltarAom(fallback): volta pra tela de onde o usuário veio (referrer da
 * mesma origem). Sem histórico útil (link direto, favorito), vai pro
 * fallback informado pelo botão.
 */
function voltarAom(fallback) {
    try {
        if (document.referrer) {
            var ref = new URL(document.referrer);
            if (ref.origin === window.location.origin && document.referrer !== window.location.href) {
                window.history.back();
                return;
            }
        }
    } catch (e) { /* URL inválida — segue pro fallback */ }
    window.location.href = fallback;
}
