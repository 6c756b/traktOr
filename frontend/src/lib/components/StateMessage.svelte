<script lang="ts">
  let { variant, text }: { variant: "loading" | "empty" | "error"; text: string } = $props();
</script>

<div class="state-message state-message-{variant}">
  {#if variant === "loading"}
    <svg class="state-icon state-icon-loading" viewBox="0 0 64 40" fill="none" aria-hidden="true">
      <g class="tractor-smoke" fill="currentColor">
        <circle class="tractor-puff tractor-puff-1" cx="31" cy="9" r="2.2" />
        <circle class="tractor-puff tractor-puff-2" cx="31" cy="9" r="2.2" />
        <circle class="tractor-puff tractor-puff-3" cx="31" cy="9" r="2.2" />
      </g>
      <rect x="29.5" y="9" width="3" height="9" rx="1" fill="currentColor" />
      <path
        d="M6 22 V12 a2 2 0 0 1 2-2 h9 l3.5 5 h16 a3 3 0 0 1 3 3 v4 h-3"
        stroke="currentColor"
        stroke-width="2"
        stroke-linejoin="round"
        stroke-linecap="round"
      />
      <path d="M6 22 h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
      <path d="M11 12 v6 M11 12 h6 l3.5 5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" />
      <g class="tractor-wheel tractor-wheel-rear">
        <circle cx="16" cy="28" r="8" stroke="currentColor" stroke-width="2" />
        <circle cx="16" cy="28" r="1.6" fill="currentColor" />
        <path d="M16 21 v14 M9 28 h14 M11 23 l10 10 M11 33 l10 -10" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" />
      </g>
      <g class="tractor-wheel tractor-wheel-front">
        <circle cx="43" cy="31" r="4.5" stroke="currentColor" stroke-width="1.8" />
        <path d="M43 27 v8 M39 31 h8 M40 28 l6 6 M40 34 l6 -6" stroke="currentColor" stroke-width="1" stroke-linecap="round" />
      </g>
    </svg>
  {:else}
    <svg class="state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      {#if variant === "error"}
        <circle cx="12" cy="12" r="9" />
        <line x1="12" y1="8" x2="12" y2="13" />
        <circle cx="12" cy="16.5" r="0.9" fill="currentColor" stroke="none" />
      {:else}
        <path d="M4.5 12.5V18a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-5.5" />
        <path d="M4.5 12.5l2.5-6.5h10l2.5 6.5" />
        <path d="M4.5 12.5H9a3 3 0 0 0 6 0h4.5" />
      {/if}
    </svg>
  {/if}
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

  .state-icon-loading {
    width: 56px;
    height: 35px;
  }

  .tractor-wheel {
    transform-box: fill-box;
    transform-origin: center;
    animation: tractor-wheel 0.6s linear infinite;
  }

  @keyframes tractor-wheel {
    to {
      transform: rotate(360deg);
    }
  }

  .tractor-puff {
    transform-box: fill-box;
    transform-origin: center;
    opacity: 0;
    animation: tractor-puff 1.8s ease-out infinite;
  }

  .tractor-puff-1 {
    animation-delay: 0s;
  }

  .tractor-puff-2 {
    animation-delay: 0.6s;
  }

  .tractor-puff-3 {
    animation-delay: 1.2s;
  }

  @keyframes tractor-puff {
    0% {
      opacity: 0;
      transform: translate(0, 0) scale(0.5);
    }
    15% {
      opacity: 0.55;
    }
    100% {
      opacity: 0;
      transform: translate(5px, -15px) scale(1.7);
    }
  }
</style>
