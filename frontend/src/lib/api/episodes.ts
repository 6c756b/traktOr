import { api } from "./client";

export type Episode = {
  number: number;
  title: string | null;
  completed: boolean;
  lastWatchedAt: string | null;
};

export type Season = {
  number: number;
  year: number | null;
  aired: number;
  completed: number;
  episodes: Episode[];
};

export function fetchEpisodes(showId: number): Promise<Season[]> {
  return api.get<Season[]>(`/shows/${showId}/episodes`);
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
