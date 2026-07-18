<script lang="ts">
  import { onMount } from "svelte";
  import { t } from "../i18n";

  let visible = $state(false);

  function handleScroll() {
    visible = window.scrollY > 400;
  }

  function scrollToTop() {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" });
  }

  onMount(() => {
    window.addEventListener("scroll", handleScroll, { passive: true });
    return () => window.removeEventListener("scroll", handleScroll);
  });
</script>

{#if visible}
  <button
    type="button"
    class="scroll-top-btn"
    onclick={scrollToTop}
    aria-label={$t("common.scrollToTop")}
  >
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <line x1="12" y1="19" x2="12" y2="5" />
      <polyline points="6 11 12 5 18 11" />
    </svg>
  </button>
{/if}

<style>
  .scroll-top-btn {
    display: none;
  }

  @media (max-width: 640px) {
    .scroll-top-btn {
      display: flex;
      position: fixed;
      right: var(--space-m);
      bottom: var(--space-l);
      z-index: 50;
      align-items: center;
      justify-content: center;
      min-width: 44px;
      min-height: 44px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: var(--surface);
      color: var(--text-muted);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
      cursor: pointer;
      opacity: 0.9;
      transition: opacity var(--transition-fast) ease, color var(--transition-fast) ease;
    }

    .scroll-top-btn svg {
      width: 20px;
      height: 20px;
    }

    .scroll-top-btn:hover {
      opacity: 1;
      color: var(--text);
    }
  }

  @media (prefers-color-scheme: dark) {
    .scroll-top-btn {
      border-color: var(--text-muted);
    }
  }

  :root[data-theme="dark"] .scroll-top-btn {
    border-color: var(--text-muted);
  }
</style>
