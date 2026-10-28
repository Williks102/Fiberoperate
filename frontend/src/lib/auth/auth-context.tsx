"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { ApiError, apiFetch } from "@/lib/api";
import type { User } from "@/lib/types";
import { clearToken, getToken, setToken } from "./token";

interface AuthState {
  user: User | null;
  /** Vrai tant qu'on ne sait pas si une session existe (premier chargement). */
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = getToken();

    // Pas de jeton : aucune requête, mais on passe quand même par une promesse pour finir le chargement.
    const session = token ? apiFetch<{ data: User }>("/auth/me", {}, token).then((res) => res.data) : Promise.resolve(null);

    session
      .then(setUser)
      .catch((error) => {
        // Jeton refusé : on repart d'une session propre. Réseau coupé : on garde le jeton.
        if (error instanceof ApiError && error.status > 0) clearToken();
      })
      .finally(() => setLoading(false));
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const res = await apiFetch<{ token: string; user: User }>("/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password, device_name: "web" }),
    });
    setToken(res.token);
    setUser(res.user);
  }, []);

  const logout = useCallback(async () => {
    const token = getToken();
    clearToken();
    setUser(null);
    if (token) await apiFetch("/auth/logout", { method: "POST" }, token).catch(() => {});
  }, []);

  const value = useMemo(() => ({ user, loading, login, logout }), [user, loading, login, logout]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const context = useContext(AuthContext);
  if (!context) throw new Error("useAuth doit être utilisé dans <AuthProvider>");
  return context;
}
