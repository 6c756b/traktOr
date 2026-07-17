import { api } from "./client";

export type ContinueWatchingItem = {
  id: number;
  slug: string;
  title: string;
  posterUrl: string | null;
  backdropUrl: string | null;
  genres: string[];
  newEpisodesCount: number;
  lastWatchedAt: string | null;
  nextEpisode: {
    season: number;
    number: number;
    title: string | null;
    firstAired: string | null;
  };
};

export type SortOrder = "new" | "waiting";

export type ContinueWatchingResponse = {
  items: ContinueWatchingItem[];
  /** true when the last full sync is over 20 minutes old -- the data may be slightly
   *  stale, and the caller should then trigger POST /sync/full in the background itself. */
  stale: boolean;
};

export function fetchContinueWatching(sort: SortOrder = "new"): Promise<ContinueWatchingResponse> {
  return api.get<ContinueWatchingResponse>(`/continue-watching?sort=${sort}`);
}

export function markEpisodeWatched(
  showId: number,
  season: number,
  number: number
): Promise<{ item: ContinueWatchingItem | null }> {
  return api.post("/watch/episode", { showId, season, number });
}
