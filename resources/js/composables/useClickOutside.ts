import { onBeforeUnmount, onMounted } from 'vue';

/**
 * A template ref holding something element-like.
 *
 * Deliberately structural rather than `Ref<HTMLElement | null>`: vue-tsc infers
 * a template-bound ref as its own expanded element type, which does not match
 * the nominal `HTMLElement`. This composable only ever *reads* the element and
 * calls `contains`, so that is all it asks for.
 */
type ElementRefLike = {
    readonly value: { contains(node: Node | null): boolean } | null;
};

/**
 * Calls `handler` when a pointerdown lands outside `target`, or on Escape.
 *
 * Used by the header's user menu and mobile menu, which the Blade version
 * implemented with Alpine's `@click.outside` — Alpine was only ever loaded on
 * the auth layout, so those dropdowns never actually worked.
 */
export function useClickOutside(target: ElementRefLike, handler: () => void) {
    const onPointerDown = (event: PointerEvent) => {
        const el = target.value;
        if (el && event.target instanceof Node && !el.contains(event.target)) {
            handler();
        }
    };

    const onKeydown = (event: KeyboardEvent) => {
        if (event.key === 'Escape') handler();
    };

    onMounted(() => {
        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeydown);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('pointerdown', onPointerDown);
        document.removeEventListener('keydown', onKeydown);
    });
}
