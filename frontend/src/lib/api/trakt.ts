import { api } from "./client";

export type TraktUser = {
  username: string | null;
  name: string | null;
  avatar: string | null;
  joinedAt: string | null;
};

export async function fetchTraktUser(): Promise<TraktUser> {
  return api.get<TraktUser>("/trakt/user");
}
