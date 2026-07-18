import { session } from "../stores/session";

// import.meta.env.BASE_URL always ends in exactly one "/" (Vite guarantee) -- "/api" at the
// domain root, "/traktor/api" when built into a subfolder via VITE_BASE_PATH.
const BASE = `${import.meta.env.BASE_URL}api`;

export class ApiError extends Error {
  /** Stable, language-neutral error code from the backend (e.g. "wrong_password"), if present. */
  constructor(public status: number, message: string, public code?: string) {
    super(message);
  }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${BASE}${path}`, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...init?.headers,
    },
    credentials: "same-origin",
  });

  if (!res.ok) {
    const body = await res.text();
    let code: string | undefined;
    try {
      code = JSON.parse(body).error;
    } catch {
      // Response wasn't JSON -- no error code available, apiErrorMessage() falls back.
    }
    if (res.status === 401) {
      // The PHP session expired or was invalidated server-side (host restart, cookie
      // cleared, etc.) -- reset locally so App.svelte's routing effect redirects to /login,
      // instead of every caller showing its own unrelated-looking error message.
      session.set({ checked: true, authenticated: false, traktConnected: false });
    }
    throw new ApiError(res.status, code || body || res.statusText, code);
  }

  if (res.status === 204) {
    return undefined as T;
  }

  return res.json() as Promise<T>;
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, data?: unknown) =>
    request<T>(path, { method: "POST", body: data ? JSON.stringify(data) : undefined }),
  del: <T>(path: string) => request<T>(path, { method: "DELETE" }),
};
