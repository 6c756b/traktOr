<script lang="ts">
  import { link } from "../router";
  import { translateGenre } from "../utils/genres";
  import { translateStatus } from "../utils/status";
  import { language } from "../stores/settings";
  import { t } from "../i18n";

  let {
    href,
    title,
    year,
    posterUrl,
    genres,
    rating,
    status,
    progress,
    onRemove,
  }: {
    href: string;
    title: string;
    year: number | null;
    posterUrl: string | null;
    genres: string[];
    rating: number | null;
    status?: string | null;
    progress?: { aired: number; completed: number; hidden?: boolean } | null;
    onRemove?: () => void;
  } = $props();

  const progressPct = $derived(
    progress && progress.aired > 0 ? Math.round((progress.completed / progress.aired) * 100) : null
  );

  // Deliberately quiet -- folded into the existing muted meta line, not a colored badge
  // (unlike the rating overlay), so it doesn't compete for attention on the grid.
  const statusHint = $derived(
    progress?.hidden
      ? $t("library.statusCanceled")
      : status === "canceled" || status === "ended"
        ? translateStatus(status, $language)
        : null
  );
</script>

<div class="library-card-wrap">
  {#if rating}
    <span class="badge poster-badge">★ {rating}</span>
  {/if}
  <a {href} use:link class="card library-card stack gap-s">
    <div class="poster">
      {#if posterUrl}
        <img src={posterUrl} alt={$t("detail.posterAlt", { title })} loading="lazy" />
      {:else}
        <div class="poster-fallback text-muted">{title}</div>
      {/if}
    </div>

    <div class="stack gap-xs library-card-info">
      <h3 class="m-0 card-title">{title}</h3>
      <p class="text-muted text-sm card-meta">
        {year ?? ""}{genres.length ? ` · ${genres.slice(0, 2).map((g) => translateGenre(g, $language)).join(", ")}` : ""}{statusHint ? ` · ${statusHint}` : ""}
      </p>
      {#if progressPct !== null}
        <div class="progress-track">
          <div class="progress-fill" style="width: {progressPct}%;"></div>
        </div>
      {/if}
    </div>
  </a>

  {#if onRemove}
    <button
      type="button"
      class="badge poster-badge remove-badge"
      onclick={() => onRemove()}
      aria-label={$t("watchlist.remove")}
      title={$t("watchlist.remove")}
    >✕</button>
    <button
      type="button"
      class="btn btn-sm btn-danger library-card-remove-mobile"
      onclick={() => onRemove()}
    >{$t("watchlist.remove")}</button>
  {/if}
</div>

<style>
  .library-card-wrap {
    position: relative;
  }

  .library-card {
    height: 100%;
    padding: 0;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    transition: transform var(--transition-fast) ease;
  }

  .library-card:hover {
    transform: translateY(-2px);
  }

  .library-card-info {
    padding: 0 var(--space-m) var(--space-m);
    flex: 1;
  }

  .card-title {
    font-size: 1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .card-meta {
    min-height: 2.4em;
    line-height: 1.2;
  }

  .poster {
    position: relative;
    aspect-ratio: 2 / 3;
    background: var(--border);
  }

  .poster img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .poster-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: var(--space-m);
  }

  .poster-badge {
    background: rgba(0, 0, 0, 0.7);
  }

  .remove-badge {
    left: var(--space-s);
    right: auto;
    cursor: pointer;
    font: inherit;
    line-height: 1;
  }

  .remove-badge:hover {
    background: var(--danger);
  }

  .library-card-remove-mobile {
    display: none;
  }

  .btn-danger {
    background: #b91c1c;
    color: #fff;
  }

  .btn-danger:hover {
    opacity: 0.9;
  }

  @media (prefers-color-scheme: dark) {
    .btn-danger {
      background: var(--danger);
    }
  }

  :root[data-theme="dark"] .btn-danger {
    background: var(--danger);
  }

  .progress-track {
    margin-top: auto;
    height: 4px;
    border-radius: 999px;
    background: var(--border);
    overflow: hidden;
  }

  .progress-fill {
    height: 100%;
    background: var(--primary);
  }

  @media (max-width: 640px) {
    .library-card {
      flex-direction: row;
      align-items: stretch;
    }

    .poster {
      width: 110px;
      flex-shrink: 0;
    }

    .library-card-info {
      padding: var(--space-m);
    }

    .remove-badge {
      display: none;
    }

    .library-card-remove-mobile {
      display: flex;
      position: absolute;
      left: calc(110px + var(--space-m));
      right: var(--space-m);
      bottom: var(--space-m);
    }
  }
</style>
