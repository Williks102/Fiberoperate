"use client";

import Link from "next/link";
import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { Pager } from "@/components/pager";
import type { Paged, Plaque } from "@/lib/types";
import { useApi } from "@/lib/use-api";
import { useDebounced } from "@/lib/use-debounced";

/** Administration des plaques (administrateur) ; la consultation passe par l'écran Réseau. */
export default function PlaquesPage() {
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const q = useDebounced(search.trim());

  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (q) params.set("q", q);
  const { reload, ...state } = useApi<Paged<Plaque>>(`/plaques?${params}`);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">Plaques</h1>
        <Link href="/plaques/nouveau" className="flex h-11 items-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white">
          Nouvelle plaque
        </Link>
      </div>

      <div className="space-y-1">
        <label htmlFor="search" className="text-sm font-medium">
          Rechercher
        </label>
        <input
          id="search"
          type="search"
          placeholder="Nom de la plaque"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setPage(1);
          }}
          className="h-11 w-full rounded-lg border border-zinc-300 px-3 text-base"
        />
      </div>

      <AsyncList state={state} reload={reload} empty={q ? "Aucune plaque ne correspond à votre recherche." : "Aucune plaque. Elles sont créées à l'import du réseau, ou ici."}>
        {(plaques) => (
          <>
            <ul className="space-y-2">
              {plaques.map((plaque) => (
                <li key={plaque.id} className="space-y-1 rounded-xl border border-zinc-200 bg-white p-4">
                  <p className="font-medium">
                    <Link href={`/plaques/${plaque.id}`} className="underline-offset-2 hover:underline">
                      {plaque.name}
                    </Link>
                  </p>
                  <p className="text-sm text-zinc-700">
                    OLT : {plaque.olt?.name ?? "non renseigné"} · Superviseur : {plaque.supervisor?.name ?? "non défini"}
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
