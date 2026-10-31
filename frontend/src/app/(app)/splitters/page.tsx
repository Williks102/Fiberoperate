"use client";

import Link from "next/link";
import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { inputClass } from "@/components/field";
import { Pager } from "@/components/pager";
import type { Jdv, Paged, Plaque } from "@/lib/types";
import { useApi } from "@/lib/use-api";
import { useDebounced } from "@/lib/use-debounced";

/** Administration des splitters ; la consultation passe par l'écran Réseau. */
export default function SplittersPage() {
  const [search, setSearch] = useState("");
  const [plaqueId, setPlaqueId] = useState("");
  const [page, setPage] = useState(1);
  const q = useDebounced(search.trim());

  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (q) params.set("q", q);
  if (plaqueId) params.set("plaque_id", plaqueId);
  const { reload, ...state } = useApi<Paged<Jdv>>(`/jdvs?${params}`);
  const plaques = useApi<Paged<Plaque>>("/plaques?per_page=100");

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">Splitters</h1>
        <Link href="/splitters/nouveau" className="flex h-11 items-center rounded-lg bg-primary hover:bg-primary-hover px-4 text-sm font-medium text-white">
          Nouveau splitter
        </Link>
      </div>

      <div className="flex flex-col gap-2 sm:flex-row">
        <div className="flex-1 space-y-1">
          <label htmlFor="search" className="text-sm font-medium">
            Rechercher
          </label>
          <input
            id="search"
            type="search"
            placeholder="Code du splitter, par exemple SPL001"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            className={inputClass}
          />
        </div>
        <div className="space-y-1 sm:w-56">
          <label htmlFor="plaque" className="text-sm font-medium">
            Plaque
          </label>
          <select
            id="plaque"
            value={plaqueId}
            onChange={(e) => {
              setPlaqueId(e.target.value);
              setPage(1);
            }}
            className={inputClass}
          >
            <option value="">Toutes les plaques</option>
            {plaques.status === "ok" &&
              plaques.data.data.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
          </select>
        </div>
      </div>

      <AsyncList state={state} reload={reload} empty={q || plaqueId ? "Aucun splitter ne correspond à votre recherche." : "Aucun splitter. Ils sont créés à l'import du réseau, ou ici."}>
        {(jdvs) => (
          <>
            <ul className="space-y-2">
              {jdvs.map((jdv) => (
                <li key={jdv.id} className="space-y-1 rounded-xl border border-slate-200 bg-white p-4">
                  <p className="font-medium">
                    <Link href={`/splitters/${jdv.id}`} className="underline-offset-2 hover:underline">
                      {jdv.code}
                    </Link>
                  </p>
                  <p className="text-sm text-slate-700">
                    Sous-plaque {jdv.zone?.code ?? "—"} · Plaque {jdv.zone?.plaque?.name ?? "—"}
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
