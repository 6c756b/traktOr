import { api } from "./client";

export type EpisodeShape = { number: number; title: string | null };
export type SeasonShape = { number: number; year: number | null; episodes: EpisodeShape[] };

export type EpisodeProgress = { number: number; completed: boolean; lastWatchedAt: string | null };
export type SeasonProgress = { number: number; aired: number; completed: number; episodes: EpisodeProgress[] };

export function fetchSeasonShape(showId: number): Promise<SeasonShape[]> {
  return api.get<SeasonShape[]>(`/shows/${showId}/season-shape`);
}

export function fetchProgress(showId: number): Promise<SeasonProgress[]> {
  return api.get<SeasonProgress[]>(`/shows/${showId}/progress`);
}

export function watchEpisode(showId: number, season: number, number: number) {
  return api.post("/watch/episode", { showId, season, number });
}

export function unwatchEpisode(showId: number, season: number, number: number) {
  return api.post("/unwatch/episode", { showId, season, number });
}

export function watchSeason(showId: number, season: number) {
  return api.post("/watch/season", { showId, season });
}

export type EpisodeRef = { season: number; number: number };

export function watchEpisodes(showId: number, episodes: EpisodeRef[]) {
  return api.post("/watch/episodes", { showId, episodes });
}
