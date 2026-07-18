<script lang="ts">
  import type { LibraryFilters, TraktList } from "../api/library";
  import { translateGenre } from "../utils/genres";
  import { language } from "../stores/settings";
  import { t } from "../i18n";

  let {
    filters = $bindable(),
    genreOptions,
    lists,
    showStatusToggle,
    sortOptions,
  }: {
    filters: LibraryFilters;
    genreOptions: string[];
    lists: TraktList[];
    showStatusToggle: boolean;
    sortOptions: { value: string; label: string }[];
  } = $props();

  const ongoingStatuses = ["returning series", "in production"];
  const endedStatuses = ["ended", "canceled"];

  function toggleGenre(genre: string) {
    const current = filters.genres ?? [];
    filters.genres = current.includes(genre)
      ? current.filter((g) => g !== genre)
      : [...current, genre];
  }

  function isStatusMode(statuses: string[] | undefined, mode: string[]): boolean {
    if (!statuses || statuses.length !== mode.length) return false;
    return mode.every((s) => statuses.includes(s));
  }

  function setStatusMode(mode: "all" | "ongoing" | "ended") {
    filters.statuses = mode === "ongoing" ? ongoingStatuses : mode === "ended" ? endedStatuses : undefined;
  }

  function resetFilters() {
    filters = { sort: filters.sort, dir: filters.dir };
  }

  let expanded = $state(window.innerWidth > 640);
</script>

<div class="card stack gap-m">
  <button
    type="button"
    class="filter-toggle"
    onclick={() => (expanded = !expanded)}
    aria-expanded={expanded}
  >
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="11" cy="11" r="7" />
      <line x1="16.5" y1="16.5" x2="21" y2="21" />
    </svg>
    <span class="grow">{$t("filter.toggleLabel")}</span>
    <svg class="chevron {expanded ? 'chevron-open' : ''}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <polyline points="6 9 12 15 18 9" />
    </svg>
  </button>

  {#if expanded}
  <div class="row gap-s wrap">
    <input
      class="input grow search-input"
      type="search"
      placeholder={$t("filter.searchPlaceholder")}
      value={filters.search ?? ""}
      oninput={(e) => (filters.search = e.currentTarget.value || undefined)}
    />

    <select class="input" value={filters.sort ?? "title"} onchange={(e) => (filters.sort = e.currentTarget.value)}>
      {#each sortOptions as opt}
        <option value={opt.value}>{opt.label}</option>
      {/each}
    </select>

    <select class="input" value={filters.dir ?? ""} onchange={(e) => (filters.dir = e.currentTarget.value as "" | "asc" | "desc")}>
      <option value="">{$t("filter.defaultDirection")}</option>
      <option value="asc">{$t("filter.ascending")}</option>
      <option value="desc">{$t("filter.descending")}</option>
    </select>
  </div>

  {#if showStatusToggle}
    <div class="row gap-s">
      <button class="btn {!filters.statuses ? 'btn-primary' : 'btn-secondary'}" onclick={() => setStatusMode("all")}>{$t("filter.all")}</button>
      <button class="btn {isStatusMode(filters.statuses, ongoingStatuses) ? 'btn-primary' : 'btn-secondary'}" onclick={() => setStatusMode("ongoing")}>{$t("filter.ongoing")}</button>
      <button class="btn {isStatusMode(filters.statuses, endedStatuses) ? 'btn-primary' : 'btn-secondary'}" onclick={() => setStatusMode("ended")}>{$t("filter.ended")}</button>
    </div>
  {/if}

  <div class="row gap-xs wrap">
    {#each genreOptions as genre}
      <button
        class="btn btn-sm {filters.genres?.includes(genre) ? 'btn-primary' : 'btn-secondary'}"
        onclick={() => toggleGenre(genre)}
      >
        {translateGenre(genre, $language)}
      </button>
    {/each}
  </div>

  <div class="row gap-s wrap">
    <label class="row gap-xs text-muted text-sm">
      {$t("filter.yearFrom")}
      <input
        class="input year-input"
        type="number"
        value={filters.yearMin ?? ""}
        oninput={(e) => (filters.yearMin = e.currentTarget.value ? Number(e.currentTarget.value) : undefined)}
      />
    </label>
    <label class="row gap-xs text-muted text-sm">
      {$t("filter.yearTo")}
      <input
        class="input year-input"
        type="number"
        value={filters.yearMax ?? ""}
        oninput={(e) => (filters.yearMax = e.currentTarget.value ? Number(e.currentTarget.value) : undefined)}
      />
    </label>

    <label class="row gap-xs text-muted text-sm">
      {$t("filter.ratingFrom")}
      <select
        class="input"
        value={filters.ratingMin ?? ""}
        onchange={(e) => (filters.ratingMin = e.currentTarget.value ? Number(e.currentTarget.value) : undefined)}
      >
        <option value="">{$t("filter.all")}</option>
        {#each [6, 7, 8, 9, 10] as r}
          <option value={r}>{r}+</option>
        {/each}
      </select>
    </label>

    {#if lists.length > 0}
      <label class="row gap-xs text-muted text-sm">
        {$t("filter.list")}
        <select
          class="input"
          value={filters.listId ?? ""}
          onchange={(e) => (filters.listId = e.currentTarget.value ? Number(e.currentTarget.value) : undefined)}
        >
          <option value="">{$t("filter.all")}</option>
          {#each lists as list}
            <option value={list.id}>{list.name}</option>
          {/each}
        </select>
      </label>
    {/if}

    <button class="btn btn-secondary" onclick={resetFilters}>{$t("filter.reset")}</button>
  </div>
  {/if}
</div>

<style>
  .filter-toggle {
    display: flex;
    align-items: center;
    gap: var(--space-s);
    width: 100%;
    min-height: 44px;
    background: none;
    border: none;
    padding: 0;
    color: var(--text);
    font: inherit;
    font-weight: 500;
    text-align: left;
    cursor: pointer;
  }

  .filter-toggle svg {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    color: var(--text-muted);
  }

  .chevron {
    transition: transform var(--transition-fast) ease;
  }

  .chevron-open {
    transform: rotate(180deg);
  }

  .search-input {
    min-width: 180px;
  }

  .year-input {
    width: 90px;
  }
</style>
