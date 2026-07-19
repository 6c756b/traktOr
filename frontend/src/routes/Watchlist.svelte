<script lang="ts">
  import { onMount } from "svelte";
  import { navigate } from "../lib/router";
  import { apiErrorMessage } from "../lib/api/errors";
  import {
    fetchShows, fetchMovies, fetchGenres,
    type ShowListItem, type MovieListItem, type LibraryFilters,
  } from "../lib/api/library";
  import { removeFromWatchlist } from "../lib/api/watchlist";
  import { toasts } from "../lib/stores/toast";
  import FilterBar from "../lib/components/FilterBar.svelte";
  import LibraryCard from "../lib/components/LibraryCard.svelte";
  import StateMessage from "../lib/components/StateMessage.svelte";
  import ConfirmDialog from "../lib/components/ConfirmDialog.svelte";
  import { showCollectionStatus, movieCollectionStatus } from "../lib/utils/collectionStatus";
  import { t } from "../lib/i18n";

  type WatchlistType = "shows" | "movies";

  function parseFiltersFromUrl(): { type: WatchlistType; filters: LibraryFilters } {
    const params = new URLSearchParams(window.location.search);
    const type: WatchlistType = params.get("type") === "movies" ? "movies" : "shows";
    const filters: LibraryFilters = {
      sort: params.get("sort") ?? "listed",
      dir: (params.get("dir") as LibraryFilters["dir"]) ?? "",
      watchlistOnly: true,
    };
    if (params.get("genres")) filters.genres = params.get("genres")!.split(",");
    if (params.get("statuses")) filters.statuses = params.get("statuses")!.split(",");
    if (params.get("year_min")) filters.yearMin = Number(params.get("year_min"));
    if (params.get("year_max")) filters.yearMax = Number(params.get("year_max"));
    if (params.get("rating_min")) filters.ratingMin = Number(params.get("rating_min"));
    if (params.get("search")) filters.search = params.get("search")!;
    if (params.get("collection")) filters.collectionOnly = true;
    return { type, filters };
  }

  const initial = parseFiltersFromUrl();
  let type = $state<WatchlistType>(initial.type);
  let filters = $state<LibraryFilters>(initial.filters);

  let shows = $state<ShowListItem[] | null>(null);
  let movies = $state<MovieListItem[] | null>(null);
  let genreOptions = $state<string[]>([]);
  let error = $state("");

  const sortOptions = $derived([
    { value: "listed", label: $t("watchlist.sort.added") },
    { value: "title", label: $t("library.sort.title") },
    { value: "year", label: $t("library.sort.year") },
    { value: "rating", label: $t("library.sort.rating") },
  ]);

  async function loadGenres() {
    genreOptions = await fetchGenres(type, true);
  }

  async function load() {
    error = "";
    try {
      if (type === "shows") {
        shows = await fetchShows(filters);
      } else {
        movies = await fetchMovies(filters);
      }
    } catch (e) {
      error = apiErrorMessage(e, "common.loadError", $t);
    }
  }

  function syncUrl() {
    const params = new URLSearchParams();
    params.set("type", type);
    if (filters.genres?.length) params.set("genres", filters.genres.join(","));
    if (filters.statuses?.length) params.set("statuses", filters.statuses.join(","));
    if (filters.yearMin) params.set("year_min", String(filters.yearMin));
    if (filters.yearMax) params.set("year_max", String(filters.yearMax));
    if (filters.ratingMin) params.set("rating_min", String(filters.ratingMin));
    if (filters.search) params.set("search", filters.search);
    if (filters.collectionOnly) params.set("collection", "1");
    if (filters.sort && filters.sort !== "listed") params.set("sort", filters.sort);
    if (filters.dir) params.set("dir", filters.dir);
    navigate(`/watchlist?${params.toString()}`, true);
  }

  $effect(() => {
    // Explicitly read dependencies, but do NOT touch shows/movies here --
    // otherwise the effect would retrigger itself via load() (infinite loop).
    JSON.stringify(filters);
    type;
    syncUrl();
    load();
  });

  onMount(loadGenres);

  function switchType(next: WatchlistType) {
    if (type === next) return;
    type = next;
    filters = { sort: "listed", dir: "", watchlistOnly: true };
    shows = null;
    movies = null;
    loadGenres();
  }

  let pendingRemove = $state<{ itemType: "show" | "movie"; id: number; title: string } | null>(null);

  function handleRemove(itemType: "show" | "movie", id: number, title: string) {
    pendingRemove = { itemType, id, title };
  }

  async function confirmRemove() {
    if (!pendingRemove) return;
    const { itemType, id } = pendingRemove;
    pendingRemove = null;
    try {
      await removeFromWatchlist(itemType, id);
      if (type === "shows") {
        shows = shows ? shows.filter((s) => s.id !== id) : shows;
      } else {
        movies = movies ? movies.filter((m) => m.id !== id) : movies;
      }
      toasts.push($t("watchlist.removeSuccess"), "success");
    } catch (e) {
      toasts.push(apiErrorMessage(e, "watchlist.removeError", $t), "error");
    }
  }

  const items = $derived(type === "shows" ? shows : movies);
</script>

<div class="container stack gap-l page">
  <div class="row space-between wrap gap-s">
    <h1>{$t("nav.watchlist")}</h1>
    <div class="row gap-s">
      <button class="btn {type === 'shows' ? 'btn-primary' : 'btn-secondary'}" onclick={() => switchType("shows")}>{$t("library.shows")}</button>
      <button class="btn {type === 'movies' ? 'btn-primary' : 'btn-secondary'}" onclick={() => switchType("movies")}>{$t("library.movies")}</button>
    </div>
  </div>

  <FilterBar
    bind:filters
    {genreOptions}
    lists={[]}
    showStatusToggle={type === "shows"}
    {sortOptions}
  />

  {#if error}
    <StateMessage variant="error" text={error} />
  {:else if items === null}
    <StateMessage variant="loading" text={$t("common.pageLoading")} />
  {:else if items.length === 0}
    <StateMessage variant="empty" text={$t("watchlist.empty")} />
  {:else}
    <div class="grid">
      {#each items as item (item.id)}
        {#if type === "shows"}
          <LibraryCard
            href={`/show/${item.id}`}
            title={item.title}
            year={item.year}
            posterUrl={item.posterUrl}
            genres={item.genres}
            rating={item.rating}
            status={item.status}
            progress={(item as ShowListItem).progress}
            showProgress={false}
            collectionStatus={showCollectionStatus(item as ShowListItem)}
            onRemove={() => handleRemove("show", item.id, item.title)}
          />
        {:else}
          <LibraryCard
            href={`/movie/${item.id}`}
            title={item.title}
            year={item.year}
            posterUrl={item.posterUrl}
            genres={item.genres}
            rating={item.rating}
            status={item.status}
            collectionStatus={movieCollectionStatus(item as MovieListItem)}
            onRemove={() => handleRemove("movie", item.id, item.title)}
          />
        {/if}
      {/each}
    </div>
  {/if}
</div>

<ConfirmDialog
  open={pendingRemove !== null}
  title={$t("watchlist.removeConfirmTitle")}
  message={$t("watchlist.removeConfirmBody", { title: pendingRemove?.title ?? "" })}
  confirmLabel={$t("common.confirm")}
  cancelLabel={$t("common.cancel")}
  variant="danger"
  onConfirm={confirmRemove}
  onCancel={() => (pendingRemove = null)}
/>
