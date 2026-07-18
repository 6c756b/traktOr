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
};

export function searchTrakt(query: string): Promise<SearchResult[]> {
  return api.get<SearchResult[]>(`/search?q=${encodeURIComponent(query)}`);
}
