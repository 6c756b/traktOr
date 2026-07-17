<script lang="ts">
  import { link } from "../router";
  import { translateGenre } from "../utils/genres";
  import { language } from "../stores/settings";
  import { t } from "../i18n";

  let {
    href,
    title,
    year,
    posterUrl,
    genres,
    rating,
    progress,
  }: {
    href: string;
    title: string;
    year: number | null;
    posterUrl: string | null;
    genres: string[];
    rating: number | null;
    progress?: { aired: number; completed: number } | null;
  } = $props();

  const progressPct = $derived(
    progress && progress.aired > 0 ? Math.round((progress.completed / progress.aired) * 100) : null
  );
</script>

<a {href} use:link class="card library-card stack gap-s">
  <div class="poster">
    {#if posterUrl}
      <img src={posterUrl} alt={$t("detail.posterAlt", { title })} loading="lazy" />
    {:else}
      <div class="poster-fallback text-muted">{title}</div>
    {/if}
    {#if rating}
      <span class="badge poster-badge">★ {rating}</span>
    {/if}
  </div>

  <div class="stack gap-xs library-card-info">
    <h3 class="m-0 card-title">{title}</h3>
    <p class="text-muted text-sm card-meta">
      {year ?? ""}{genres.length ? ` · ${genres.slice(0, 2).map((g) => translateGenre(g, $language)).join(", ")}` : ""}
    </p>
    {#if progressPct !== null}
      <div class="progress-track">
        <div class="progress-fill" style="width: {progressPct}%;"></div>
      </div>
    {/if}
  </div>
</a>

<style>
  .library-card {
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

  .progress-track {
    height: 4px;
    border-radius: 999px;
    background: var(--border);
    overflow: hidden;
  }

  .progress-fill {
    height: 100%;
    background: var(--primary);
  }
</style>
