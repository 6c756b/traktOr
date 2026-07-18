import { api } from "./client";
import { session, type SessionState } from "../stores/session";

export async function fetchSession(): Promise<void> {
  const data = await api.get<Omit<SessionState, "checked">>("/auth/session");
  session.set({ checked: true, ...data });
}

export async function login(password: string): Promise<void> {
  await api.post("/auth/login", { password });
  try {
    await fetchSession();
  } catch {
    // The login itself already succeeded (the server accepted the password and set the
    // session cookie) -- a failure here is just the follow-up session-details fetch
    // (network blip), not a login failure. Set what's already known to be true so the
    // caller doesn't misreport this as "wrong password"; traktConnected corrects itself
    // on the next fetchSession() call (e.g. after the app's initial settings load).
    session.set({ checked: true, authenticated: true, traktConnected: false });
  }
}

export async function logout(): Promise<void> {
  try {
    await api.post("/auth/logout");
  } finally {
    // Reset local session state even if the request itself failed (network blip) -- the
    // user's intent is unambiguous, and leaving the UI stuck in an "authenticated" state
    // after a failed logout click would be worse than a spurious extra server round trip
    // on their next action.
    session.set({ checked: true, authenticated: false, traktConnected: false });
  }
}
