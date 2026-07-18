import { api } from "./client";

export type WatchlistItemType = "show" | "movie";

export function removeFromWatchlist(itemType: WatchlistItemType, id: number): Promise<void> {
  return api.del(`/watchlist/${itemType}/${id}`);
}

export function addToWatchlist(itemType: WatchlistItemType, id: number): Promise<void> {
  return api.post(`/watchlist`, { itemType, id });
}
