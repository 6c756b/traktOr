import { writable } from "svelte/store";

// import.meta.env.BASE_URL always ends in exactly one "/" -- "" at the domain root (dev
// and root hosting), otherwise e.g. "/traktor" when built into a subfolder via VITE_BASE_PATH.
const BASE_PATH = import.meta.env.BASE_URL.replace(/\/$/, "");

/** Bare path (e.g. "/library") -> full path including subfolder prefix. */
function withBase(path: string): string {
  return path.startsWith("http") ? path : `${BASE_PATH}${path}`;
}

/** Full path (e.g. from window.location) -> bare path for the route patterns. */
function stripBase(pathname: string): string {
  if (BASE_PATH && pathname.startsWith(BASE_PATH)) {
    return pathname.slice(BASE_PATH.length) || "/";
  }
  return pathname;
}

export const currentPath = writable(stripBase(window.location.pathname));
export const currentSearch = writable(window.location.search);

function updatePath(): void {
  currentPath.set(stripBase(window.location.pathname));
  currentSearch.set(window.location.search);
}

window.addEventListener("popstate", updatePath);

/** path is always a bare path (e.g. "/library"), the subfolder prefix is added here. */
export function navigate(path: string, replace = false): void {
  const fullPath = withBase(path);
  if (replace) {
    history.replaceState(null, "", fullPath);
  } else {
    history.pushState(null, "", fullPath);
  }
  updatePath();
}

export function link(node: HTMLAnchorElement) {
  const rawHref = node.getAttribute("href") ?? "";
  // Set a real href attribute to the full path, so middle-click/open-in-new-tab (which
  // bypass the click handler below) still land on the correct, subfolder-prefixed URL.
  if (rawHref && !rawHref.startsWith("http")) {
    node.setAttribute("href", withBase(rawHref));
  }

  function onClick(event: MouseEvent) {
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey ||
      !rawHref ||
      rawHref.startsWith("http") ||
      node.target
    ) {
      return;
    }
    event.preventDefault();
    navigate(rawHref);
  }

  node.addEventListener("click", onClick);
  return {
    destroy() {
      node.removeEventListener("click", onClick);
    },
  };
}

export function matchPath(pattern: string, path: string): Record<string, string> | null {
  const patternParts = pattern.split("/").filter(Boolean);
  const pathParts = path.split("/").filter(Boolean);
  if (patternParts.length !== pathParts.length) {
    return null;
  }

  const params: Record<string, string> = {};
  for (let i = 0; i < patternParts.length; i++) {
    const part = patternParts[i];
    if (part.startsWith(":")) {
      params[part.slice(1)] = decodeURIComponent(pathParts[i]);
    } else if (part !== pathParts[i]) {
      return null;
    }
  }
  return params;
}
