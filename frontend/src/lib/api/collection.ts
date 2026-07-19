import { api } from "./client";

export function addToCollection(id: number): Promise<void> {
  return api.post("/collection", { id });
}

export function removeFromCollection(id: number): Promise<void> {
  return api.del(`/collection/${id}`);
}

export function collectSeason(showId: number, season: number): Promise<void> {
  return api.post("/collection/season", { showId, season });
}

export function uncollectSeason(showId: number, season: number): Promise<void> {
  return api.del(`/collection/season/${showId}/${season}`);
}
