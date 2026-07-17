import { writable } from "svelte/store";

export type ToastVariant = "info" | "success" | "error";
export type Toast = { id: number; text: string; variant: ToastVariant };

function createToastStore() {
  const { subscribe, update } = writable<Toast[]>([]);
  let nextId = 0;

  /** duration in ms, 0 keeps the toast open until dismiss() is called explicitly. */
  function push(text: string, variant: ToastVariant = "info", duration = 3000): number {
    const id = nextId++;
    update((toasts) => [...toasts, { id, text, variant }]);
    if (duration > 0) {
      setTimeout(() => dismiss(id), duration);
    }
    return id;
  }

  function dismiss(id: number): void {
    update((toasts) => toasts.filter((toast) => toast.id !== id));
  }

  return { subscribe, push, dismiss };
}

export const toasts = createToastStore();
