<script lang="ts">
  let {
    open,
    title,
    message,
    confirmLabel,
    cancelLabel,
    variant = "default",
    onConfirm,
    onCancel,
  }: {
    open: boolean;
    title: string;
    message: string;
    confirmLabel: string;
    cancelLabel: string;
    variant?: "default" | "danger";
    onConfirm: () => void;
    onCancel: () => void;
  } = $props();

  function handleWindowKeydown(e: KeyboardEvent) {
    if (open && e.key === "Escape") {
      onCancel();
    }
  }
</script>

<svelte:window onkeydown={handleWindowKeydown} />

{#if open}
  <div class="dialog-backdrop">
    <button
      type="button"
      class="dialog-backdrop-close"
      onclick={onCancel}
      aria-label={cancelLabel}
    ></button>
    <div
      class="card stack gap-m dialog"
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="confirm-dialog-title"
      tabindex="-1"
    >
      <h2 id="confirm-dialog-title" class="m-0 card-subtitle">{title}</h2>
      <p class="text-muted m-0">{message}</p>
      <div class="row gap-s dialog-actions">
        <button type="button" class="btn btn-secondary" onclick={onCancel}>{cancelLabel}</button>
        <button
          type="button"
          class="btn {variant === 'danger' ? 'btn-danger' : 'btn-primary'}"
          onclick={onConfirm}
        >{confirmLabel}</button>
      </div>
    </div>
  </div>
{/if}

<style>
  .dialog-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
    padding: var(--space-l);
  }

  .dialog-backdrop-close {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: none;
    background: none;
    padding: 0;
    cursor: default;
  }

  .dialog {
    position: relative;
    max-width: 360px;
    width: 100%;
  }

  .dialog-actions {
    justify-content: flex-end;
  }

  .btn-danger {
    background: var(--danger);
    color: #fff;
  }

  .btn-danger:hover {
    opacity: 0.9;
  }
</style>
