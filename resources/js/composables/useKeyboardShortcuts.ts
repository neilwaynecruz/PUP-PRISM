import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { create as productsCreate } from '@/routes/inventory/products';

export function useKeyboardShortcuts(): void {
    function dispatchShortcutEvent(name: string): void {
        window.dispatchEvent(new CustomEvent(name));
    }

    function isTypingTarget(target: EventTarget | null): boolean {
        const element = target as HTMLElement | null;

        return (
            element instanceof HTMLInputElement ||
            element instanceof HTMLTextAreaElement ||
            element instanceof HTMLSelectElement ||
            element?.closest('[contenteditable="true"]') !== null
        );
    }

    function handleKeydown(event: KeyboardEvent): void {
        const typing = isTypingTarget(event.target);

        if (typing && event.key !== 'Escape') {
            return;
        }

        const path = window.location.pathname;

        // Ctrl+K / Cmd+K → Global search
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            dispatchShortcutEvent('app:open-global-search');

            return;
        }

        // Ctrl+N / Cmd+N → New item (context-aware)
        if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
            event.preventDefault();

            const explicitAction = document.querySelector<HTMLElement>(
                '[data-shortcut-action="new"]',
            );

            if (explicitAction && !explicitAction.hasAttribute('disabled')) {
                explicitAction.click();

                return;
            }

            if (path.includes('/inventory/products') && !path.includes('/create')) {
                router.get(productsCreate().url);
            } else if (path.includes('/inventory/bookings')) {
                const form = document.querySelector('[data-shortcut="new"]');

                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const firstInput = form.querySelector<HTMLInputElement>('input, select');
                    firstInput?.focus();
                }
            } else if (path.includes('/inventory/requisitions')) {
                // Scroll to the new requisition form
                const form = document.querySelector('[data-shortcut="new"]');

                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const firstInput = form.querySelector<HTMLInputElement>('input, select');
                    firstInput?.focus();
                }
            }

            return;
        }

        // Esc → Close overlays and command palette
        if (event.key === 'Escape') {
            dispatchShortcutEvent('app:close-overlays');
        }
    }

    onMounted(() => {
        document.addEventListener('keydown', handleKeydown);
    });

    onUnmounted(() => {
        document.removeEventListener('keydown', handleKeydown);
    });
}
