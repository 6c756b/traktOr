import { api } from "./client";

export type ShowListItem = {
  id: number;
  slug: string;
  title: string;
  year: number | null;
  overview: string;
  status: string | null;
  network: string | null;
  runtime: number | null;
  genres: string[];
  posterUrl: string | null;
  backdropUrl: string | null;
  airedEpisodes: number | null;
  certification: string | null;
  rating: number | null;
  tmdbId: number | null;
  onWatchlist: boolean;
  progress: {
    aired: number;
    completed: number;
    lastWatchedAt: string | null;
    hidden: boolean;
    lastEpisode: { season: number; number: number } | null;
    nextEpisode: { season: number; number: number; title: string | null; firstAired: string | null } | null;
  } | null;
};

export type MovieListItem = {
  id: number;
  slug: string;
  title: string;
  year: number | null;
  overview: string;
  status: string | null;
  genres: string[];
  posterUrl: string | null;
  backdropUrl: string | null;
  runtime: number | null;
  released: string | null;
  certification: string | null;
  rating: number | null;
};

export type LibraryFilters = {
  genres?: string[];
  statuses?: string[];
  yearMin?: number;
  yearMax?: number;
  ratingMin?: number;
  listId?: number;
  search?: string;
  sort?: string;
  dir?: "asc" | "desc" | "";
  watchlistOnly?: boolean;
};

export type TraktList = { id: number; name: string; slug: string };

function toQuery(filters: LibraryFilters): string {
  const params = new URLSearchParams();
  if (filters.genres?.length) params.set("genres", filters.genres.join(","));
  if (filters.statuses?.length) params.set("statuses", filters.statuses.join(","));
  if (filters.yearMin) params.set("year_min", String(filters.yearMin));
  if (filters.yearMax) params.set("year_max", String(filters.yearMax));
  if (filters.ratingMin) params.set("rating_min", String(filters.ratingMin));
  if (filters.listId) params.set("list_id", String(filters.listId));
  if (filters.search) params.set("search", filters.search);
  if (filters.sort) params.set("sort", filters.sort);
  if (filters.dir) params.set("dir", filters.dir);
  if (filters.watchlistOnly) params.set("watchlist", "1");
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

export function fetchShows(filters: LibraryFilters): Promise<ShowListItem[]> {
  return api.get<ShowListItem[]>(`/shows${toQuery(filters)}`);
}

export function fetchMovies(filters: LibraryFilters): Promise<MovieListItem[]> {
  return api.get<MovieListItem[]>(`/movies${toQuery(filters)}`);
}

export function fetchShowDetail(id: number): Promise<ShowListItem> {
  return api.get<ShowListItem>(`/shows/${id}`);
}

export function hideShow(id: number): Promise<void> {
  return api.post(`/shows/${id}/hide`);
}

export function unhideShow(id: number): Promise<void> {
  return api.post(`/shows/${id}/unhide`);
}

export function fetchMovieDetail(id: number): Promise<MovieListItem> {
  return api.get<MovieListItem>(`/movies/${id}`);
}

export function fetchGenres(type: "shows" | "movies", watchlistOnly = false): Promise<string[]> {
  return api.get<string[]>(`/genres?type=${type}${watchlistOnly ? "&watchlist=1" : ""}`);
}

export function fetchLists(): Promise<TraktList[]> {
  return api.get<TraktList[]>("/lists");
}
