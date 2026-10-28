const KEY = "ftth.token";

// Jeton gardé en localStorage : la PWA doit le conserver hors ligne (risque XSS accepté, voir CLAUDE.md).
export function getToken(): string | null {
  try {
    return localStorage.getItem(KEY);
  } catch {
    return null;
  }
}

export function setToken(token: string): void {
  try {
    localStorage.setItem(KEY, token);
  } catch {
    // stockage indisponible (navigation privée) : la session ne survivra pas au rechargement
  }
}

export function clearToken(): void {
  try {
    localStorage.removeItem(KEY);
  } catch {
    // rien à effacer
  }
}
