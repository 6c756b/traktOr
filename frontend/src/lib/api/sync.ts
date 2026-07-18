import { api } from "./client";

export type SyncResult = {
  shows: number;
  showsSkipped: number;
  movies: number;
  ratings: number;
  lists: number;
  watchlist: number;
  hiddenShows: number;
};

export type SyncStateRow = {
  resource: string;
  status: "idle" | "running" | "error";
  last_synced_at: string | null;
  last_error: string | null;
};

export function triggerFullSync(): Promise<SyncResult> {
  return api.post<SyncResult>("/sync/full");
}

export function fetchSyncStatus(): Promise<SyncStateRow[]> {
  return api.get<SyncStateRow[]>("/sync/status");
}
