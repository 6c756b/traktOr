<script lang="ts">
  let { variant, text }: { variant: "loading" | "empty" | "error"; text: string } = $props();
</script>

<div class="state-message state-message-{variant}">
  <svg class="state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {#if variant === "loading"}
      <circle cx="12" cy="12" r="9" stroke-dasharray="40 16" />
    {:else if variant === "error"}
      <circle cx="12" cy="12" r="9" />
      <line x1="12" y1="8" x2="12" y2="13" />
      <circle cx="12" cy="16.5" r="0.9" fill="currentColor" stroke="none" />
    {:else}
      <path d="M4.5 12.5V18a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-5.5" />
      <path d="M4.5 12.5l2.5-6.5h10l2.5 6.5" />
      <path d="M4.5 12.5H9a3 3 0 0 0 6 0h4.5" />
    {/if}
  </svg>
  <p>{text}</p>
</div>

<style>
  .state-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-s);
    padding: var(--space-xl) var(--space-m);
    text-align: center;
  }

  .state-message p {
    color: var(--text-muted);
  }

  .state-message-error p {
    color: var(--danger);
  }

  .state-icon {
    width: 32px;
    height: 32px;
    color: var(--text-muted);
  }

  .state-message-error .state-icon {
    color: var(--danger);
  }

  .state-message-loading .state-icon {
    animation: spin calc(var(--transition-base) * 4) linear infinite;
  }

  @keyframes spin {
    to {
      transform: rotate(360deg);
    }
  }
</style>
