import { api } from "./client";

export type NoteItemType = "show" | "movie";

export function saveNote(itemType: NoteItemType, id: number, note: string): Promise<void> {
  return api.post("/note", { itemType, id, note });
}
