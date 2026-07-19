import { api } from "./client";

export type SearchResultType = "show" | "movie";

export type SearchResult = {
  type: SearchResultType;
  traktId: number;
  title: string;
  year: number | null;
  overview: string | null;
  genres: string[];
  posterUrl: string | null;
  watched: boolean;
  onWatchlist: boolean;
};

export function searchTrakt(query: string): Promise<SearchResult[]> {
  return api.get<SearchResult[]>(`/search?q=${encodeURIComponent(query)}`);
}

export function fetchRecommendedShows(): Promise<SearchResult[]> {
  return api.get<SearchResult[]>("/recommendations/shows");
}

export function fetchRecommendedMovies(): Promise<SearchResult[]> {
  return api.get<SearchResult[]>("/recommendations/movies");
}

export function fetchTrendingShows(): Promise<SearchResult[]> {
  return api.get<SearchResult[]>("/trending/shows");
}

export function fetchPopularMovies(): Promise<SearchResult[]> {
  return api.get<SearchResult[]>("/popular/movies");
}

export function fetchRelatedShows(showId: number): Promise<SearchResult[]> {
  return api.get<SearchResult[]>(`/shows/${showId}/related`);
}

export function fetchRelatedMovies(movieId: number): Promise<SearchResult[]> {
  return api.get<SearchResult[]>(`/movies/${movieId}/related`);
}
