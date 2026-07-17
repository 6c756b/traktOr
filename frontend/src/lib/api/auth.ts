import { api } from "./client";
import { session, type SessionState } from "../stores/session";

export async function fetchSession(): Promise<void> {
  const data = await api.get<Omit<SessionState, "checked">>("/auth/session");
  session.set({ checked: true, ...data });
}

export async function login(password: string): Promise<void> {
  await api.post("/auth/login", { password });
  await fetchSession();
}

export async function logout(): Promise<void> {
  await api.post("/auth/logout");
  session.set({ checked: true, authenticated: false, traktConnected: false });
}
