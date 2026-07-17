<script lang="ts">
  import { rateItem, unrateItem, type ItemType } from "../api/rating";
  import { t } from "../i18n";

  let { itemType, id, rating = $bindable() }: {
    itemType: ItemType;
    id: number;
    rating: number | null;
  } = $props();

  let hovered = $state<number | null>(null);
  let saving = $state(false);

  async function handleClick(value: number) {
    saving = true;
    try {
      if (rating === value) {
        await unrateItem(itemType, id);
        rating = null;
      } else {
        await rateItem(itemType, id, value);
        rating = value;
      }
    } finally {
      saving = false;
    }
  }
</script>

<div class="row gap-xs rating-widget">
  {#each Array(10) as _, i}
    {@const value = i + 1}
    <button
      class="star"
      disabled={saving}
      onmouseenter={() => (hovered = value)}
      onmouseleave={() => (hovered = null)}
      onclick={() => handleClick(value)}
      title={$t("rating.rate", { n: value })}
      aria-label={$t("rating.rate", { n: value })}
    >
      {(hovered ?? rating ?? 0) >= value ? "★" : "☆"}
    </button>
  {/each}
  {#if rating}<span class="text-muted text-sm">{rating}/10</span>{/if}
</div>

<style>
  .rating-widget {
    align-items: center;
  }

  .star {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.3rem;
    line-height: 1;
    padding: 0;
    color: var(--primary);
    transition: transform var(--transition-fast) ease;
  }

  .star:hover {
    transform: scale(1.15);
  }

  .star:disabled {
    cursor: default;
    opacity: 0.6;
    transform: none;
  }
</style>
