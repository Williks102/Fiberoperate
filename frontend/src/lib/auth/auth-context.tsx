"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { ApiError, apiFetch } from "@/lib/api";
import type { User } from "@/lib/types";
import { pushOutbox } from "@/lib/offline/engine";
import { countPending } from "@/lib/offline/outbox";
import { countPendingPhotos, uploadPendingPhotos } from "@/lib/offline/photos";
import { clearLocalData, loadUser, saveUser } from "@/lib/offline/visits";
import { clearToken, getToken, setToken } from "./token";

interface AuthState {
  user: User | null;
  /** Vrai tant qu'on ne sait pas si une session existe (premier chargement). */
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

/** Des résultats ou des photos du terrain n'ont pas encore été envoyés : se déconnecter les effacerait. */
export class UnsentResultsError extends Error {
  constructor() {
    super("Des résultats ou des photos saisis sur ce téléphone n'ont pas encore été envoyés. Reconnectez-vous au réseau, puis réessayez.");
  }
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
      .then((me) => {
        setUser(me);
        if (me) void saveUser(me).catch(() => {}); // dernier utilisateur connu, pour rouvrir l'application sans réseau
      })
      .catch(async (error) => {
        if (error instanceof ApiError && error.status === 401) {
          // Jeton refusé : on repart d'une session propre, sans rien laisser des données du poste.
          clearToken();
          void clearLocalData({ keepUnsent: true }); // les résultats pas encore envoyés attendent la reconnexion

          return;
        }
        // Réseau coupé ou serveur qui ne répond pas (réveil, 502, 429…) : le jeton reste valable, on garde le dernier utilisateur connu.
        const known = token ? await loadUser().catch(() => null) : null;
        if (known) setUser(known);
      })
      .finally(() => setLoading(false));
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const res = await apiFetch<{ token: string; user: User }>("/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password, device_name: "web" }),
    });
    // Un autre technicien sur le même téléphone ne retrouve pas les visites du précédent.
    const previous = await loadUser().catch(() => null);
    if (previous && previous.id !== res.user.id) await clearLocalData();
    await saveUser(res.user).catch(() => {});
    setToken(res.token);
    setUser(res.user);
  }, []);

  const logout = useCallback(async () => {
    // Jamais de déconnexion qui perdrait une saisie : on tente d'abord l'envoi, et on refuse s'il en reste.
    const unsent = async () => (await countPending().catch(() => 0)) + (await countPendingPhotos().catch(() => 0));
    if ((await unsent()) > 0) {
      await pushOutbox().catch(() => {});
      await uploadPendingPhotos().catch(() => {});
      if ((await unsent()) > 0) throw new UnsentResultsError();
    }
    const token = getToken();
    clearToken();
    setUser(null);
    await clearLocalData();
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
