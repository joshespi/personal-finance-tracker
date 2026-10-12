// App-wide form behaviour that would otherwise need touching every view:
//  1. styled confirm dialog in place of the browser's confirm() popup,
//  2. double-submit guard on every plain form,
//  3. "n" keyboard shortcut for Quick Add.
//
// Both form handlers are delegated at the document level in the capture phase, so they
// also cover forms Livewire re-renders after page load.

const GUARD_RESET_MS = 5000;

// Pulls the message out of an inline `onsubmit="return confirm('…')"` by running the
// attribute with a stand-in confirm() that records its argument.
export function extractConfirmMessage(code) {
    if (!code || !code.includes('confirm(')) return null;
    let message = null;
    try {
        new Function('confirm', code)((m) => { message = String(m); return true; });
    } catch {
        return null;
    }
    return message;
}

let dialog = null;

function buildDialog() {
    const el = document.createElement('dialog');
    el.className = 'rounded-lg p-0 shadow-xl backdrop:bg-black/50 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 w-[calc(100%-2rem)] max-w-sm';
    el.innerHTML = `
        <form method="dialog" class="p-6">
            <h2 class="text-base font-semibold">Please confirm</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300" data-confirm-message></p>
            <div class="mt-6 flex justify-end gap-3">
                <button value="cancel" class="px-4 py-2 text-sm font-semibold rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</button>
                <button value="ok" class="px-4 py-2 text-sm font-semibold rounded-md bg-red-600 text-white hover:bg-red-500 transition">Confirm</button>
            </div>
        </form>`;
    document.body.appendChild(el);
    return el;
}

function askConfirm(message) {
    dialog ??= buildDialog();
    dialog.querySelector('[data-confirm-message]').textContent = message;
    return new Promise((resolve) => {
        dialog.addEventListener('close', () => resolve(dialog.returnValue === 'ok'), { once: true });
        dialog.returnValue = 'cancel';
        dialog.showModal();
        dialog.querySelector('button[value="cancel"]').focus();
    });
}

export function initFormGuards() {
    const guarded = new WeakSet();

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        const message = extractConfirmMessage(form.getAttribute('onsubmit'));
        if (message === null) return;

        // Keep the inline handler (and its native popup) from running.
        event.stopImmediatePropagation();
        event.preventDefault();

        const submitter = event.submitter;
        askConfirm(message).then((ok) => {
            if (!ok) return;
            form.removeAttribute('onsubmit');
            form.requestSubmit(submitter ?? undefined);
        });
    }, true);

    // Runs after the other handlers, so a form that something else cancelled (validation,
    // Livewire/Alpine .prevent) is left alone. Disabling on a timeout keeps the submitter's
    // name/value in the payload, and the reset covers responses that don't navigate away
    // (file downloads).
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || ['get', 'dialog'].includes(form.method.toLowerCase())) return;

        setTimeout(() => {
            if (event.defaultPrevented) return;
            // form.elements includes buttons attached from outside via the form="" attribute.
            const buttons = [...form.elements].filter((el) => el.type === 'submit' && !el.disabled);
            buttons.forEach((b) => { b.disabled = true; guarded.add(b); });
            setTimeout(() => buttons.forEach((b) => { b.disabled = false; guarded.delete(b); }), GUARD_RESET_MS);
        }, 0);
    });

    // Back/forward cache restores the page with the buttons still disabled — re-enable only
    // the ones this guard disabled, not buttons the page disabled on purpose.
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        for (const b of document.querySelectorAll('button:disabled')) {
            if (guarded.has(b)) b.disabled = false;
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'n' || event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented) return;
        const t = event.target;
        if (t instanceof HTMLElement && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName))) return;
        if (document.querySelector('dialog[open]')) return;
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('open-quick-add'));
    });
}
