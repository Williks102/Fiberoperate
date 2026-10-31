"use client";

import Link from "next/link";
import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { inputClass } from "@/components/field";
import { Pager } from "@/components/pager";
import { Badge } from "@/components/ui/badge";
import { useAuth } from "@/lib/auth/auth-context";
import { MISSION_LABELS, ROLE_LABELS } from "@/lib/labels";
import type { Paged, Role, User } from "@/lib/types";
import { useApi } from "@/lib/use-api";
import { useDebounced } from "@/lib/use-debounced";

export default function UtilisateursPage() {
  const { user: me } = useAuth();
  const canManage = me?.role === "admin";
  const [search, setSearch] = useState("");
  const [role, setRole] = useState<"" | Role>("");
  const [page, setPage] = useState(1);
  const q = useDebounced(search.trim());

  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (q) params.set("q", q);
  if (role) params.set("role", role);

  const { reload, ...state } = useApi<Paged<User>>(`/users?${params}`);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">Utilisateurs</h1>
        {canManage && (
          <Link href="/utilisateurs/nouveau" className="flex h-11 items-center rounded-lg bg-primary hover:bg-primary-hover px-4 text-sm font-medium text-white">
            Nouvel utilisateur
          </Link>
        )}
      </div>

      <div className="flex flex-col gap-2 sm:flex-row">
        <div className="flex-1 space-y-1">
          <label htmlFor="search" className="text-sm font-medium">
            Rechercher
          </label>
          <input
            id="search"
            type="search"
            placeholder="Nom ou adresse e-mail"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            className={inputClass}
          />
        </div>
        <div className="space-y-1 sm:w-56">
          <label htmlFor="role" className="text-sm font-medium">
            Rôle
          </label>
          <select
            id="role"
            value={role}
            onChange={(e) => {
              setRole(e.target.value as "" | Role);
              setPage(1);
            }}
            className={inputClass}
          >
            <option value="">Tous les rôles</option>
            {(Object.keys(ROLE_LABELS) as Role[]).map((r) => (
              <option key={r} value={r}>
                {ROLE_LABELS[r]}
              </option>
            ))}
          </select>
        </div>
      </div>

      <AsyncList state={state} reload={reload} empty="Aucun utilisateur ne correspond à votre recherche.">
        {(users) => (
          <>
            <ul className="space-y-2">
              {users.map((user) => (
                <li
                  key={user.id}
                  className="grid gap-1 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-[2fr_2fr_1.2fr_auto] md:items-center md:gap-4"
                >
                  <span className="font-medium">
                    {canManage ? (
                      <Link href={`/utilisateurs/${user.id}`} className="underline-offset-2 hover:underline">
                        {user.name}
                      </Link>
                    ) : (
                      user.name
                    )}
                  </span>
                  <span className="break-all text-sm text-slate-700">{user.email}</span>
                  <span className="text-sm">
                    {user.role_label}
                    {user.skills && user.skills.length > 0 && (
                      <span className="block text-xs text-slate-700">
                        {user.skills.map((skill) => MISSION_LABELS[skill]).join(", ")}
                      </span>
                    )}
                  </span>
                  <Badge tone={user.is_active ? "success" : "neutral"}>{user.is_active ? "Actif" : "Désactivé"}</Badge>
                </li>
              ))}
            </ul>
            {state.status === "ok" && (
              <Pager page={state.data.meta.current_page} lastPage={state.data.meta.last_page} onChange={setPage} />
            )}
          </>
        )}
      </AsyncList>
    </div>
  );
}
