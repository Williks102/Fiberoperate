"use client";

import Link from "next/link";
import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { Pager } from "@/components/pager";
import { useAuth } from "@/lib/auth/auth-context";
import type { Paged, Team } from "@/lib/types";
import { useApi } from "@/lib/use-api";

export default function EquipesPage() {
  const { user } = useAuth();
  const canManage = user?.role === "admin" || user?.role === "dt";
  const [page, setPage] = useState(1);
  const { reload, ...state } = useApi<Paged<Team>>(`/teams?per_page=20&page=${page}`);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">Équipes</h1>
        {canManage && (
          <Link href="/equipes/nouveau" className="flex h-11 items-center rounded-lg bg-primary hover:bg-primary-hover px-4 text-sm font-medium text-white">
            Nouvelle équipe
          </Link>
        )}
      </div>

      <AsyncList state={state} reload={reload} empty={canManage ? "Aucune équipe. Créez la première." : "Aucune équipe ne vous est rattachée."}>
        {(teams) => (
          <>
            <ul className="space-y-2">
              {teams.map((team) => (
                <li key={team.id} className="space-y-1 rounded-xl border border-slate-200 bg-white p-4">
                  <p className="font-medium">
                    {canManage ? (
                      <Link href={`/equipes/${team.id}`} className="underline-offset-2 hover:underline">
                        {team.name}
                      </Link>
                    ) : (
                      team.name
                    )}
                  </p>
                  <p className="text-sm text-slate-700">
                    Coordinateur : {team.coordinator?.name ?? "non défini"} · Chef d&apos;équipe : {team.leader?.name ?? "non défini"}
                  </p>
                  <p className="text-sm text-slate-700">
                    {team.members.length === 0
                      ? "Aucun membre"
                      : `${team.members.length} membre${team.members.length > 1 ? "s" : ""} : ${team.members.map((m) => m.name).join(", ")}`}
                  </p>
                </li>
              ))}
            </ul>
            {state.status === "ok" && <Pager page={state.data.meta.current_page} lastPage={state.data.meta.last_page} onChange={setPage} />}
          </>
        )}
      </AsyncList>
    </div>
  );
}
