import { api } from "./client";

export type ItemType = "show" | "movie";

export function rateItem(itemType: ItemType, id: number, rating: number): Promise<{ rating: number }> {
  return api.post<{ rating: number }>("/rate", { itemType, id, rating });
}

export function unrateItem(itemType: ItemType, id: number): Promise<void> {
  return api.del(`/rate/${itemType}/${id}`);
}
