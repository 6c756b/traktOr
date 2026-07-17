import { writable } from "svelte/store";

export type SessionState = {
  checked: boolean;
  authenticated: boolean;
  traktConnected: boolean;
};

export const session = writable<SessionState>({
  checked: false,
  authenticated: false,
  traktConnected: false,
});
