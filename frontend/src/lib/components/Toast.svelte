<script lang="ts">
  import { toasts } from "../stores/toast";
</script>

<div class="toast-stack" aria-live="polite">
  {#each $toasts as toast (toast.id)}
    <div class="toast toast-{toast.variant}">
      <svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        {#if toast.variant === "success"}
          <circle cx="12" cy="12" r="9" />
          <path d="M8 12.5l2.5 2.5 5.5-6" />
        {:else if toast.variant === "error"}
          <circle cx="12" cy="12" r="9" />
          <line x1="12" y1="8" x2="12" y2="13" />
          <circle cx="12" cy="16.5" r="0.9" fill="currentColor" stroke="none" />
        {:else}
          <circle cx="12" cy="12" r="9" />
          <line x1="12" y1="10.5" x2="12" y2="16" />
          <circle cx="12" cy="7.5" r="0.9" fill="currentColor" stroke="none" />
        {/if}
      </svg>
      <span>{toast.text}</span>
    </div>
  {/each}
</div>

<style>
  .toast-stack {
    position: fixed;
    right: var(--space-l);
    bottom: var(--space-l);
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: var(--space-s);
    z-index: 100;
    pointer-events: none;
  }

  .toast {
    pointer-events: auto;
    display: flex;
    align-items: center;
    gap: var(--space-s);
    max-width: 340px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-s);
    padding: var(--space-s) var(--space-m);
    font-size: 0.9rem;
    color: var(--text);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
    animation: toast-in var(--transition-base) ease;
  }

  .toast-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    color: var(--text-muted);
  }

  .toast-success {
    background: color-mix(in srgb, var(--primary) 14%, var(--surface));
    border-color: var(--primary);
  }

  .toast-success .toast-icon {
    color: var(--primary);
  }

  .toast-error {
    background: color-mix(in srgb, var(--danger) 14%, var(--surface));
    border-color: var(--danger);
    color: var(--danger);
  }

  .toast-error .toast-icon {
    color: var(--danger);
  }

  @keyframes toast-in {
    from {
      opacity: 0;
      transform: translateY(12px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  @media (max-width: 640px) {
    .toast-stack {
      left: var(--space-m);
      right: var(--space-m);
      bottom: var(--space-m);
      align-items: stretch;
    }

    .toast {
      max-width: none;
    }
  }
</style>
