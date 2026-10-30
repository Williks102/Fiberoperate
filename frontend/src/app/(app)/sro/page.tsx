"use client";

import Link from "next/link";
import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { inputClass } from "@/components/field";
import { Pager } from "@/components/pager";
import type { Paged, Plaque, Sro } from "@/lib/types";
import { useApi } from "@/lib/use-api";
import { useDebounced } from "@/lib/use-debounced";

/** Administration des SRO : une par sous-plaque, elles alimentent les splitters à partir de l'OLT. */
export default function SroPage() {
  const [search, setSearch] = useState("");
  const [plaqueId, setPlaqueId] = useState("");
  const [page, setPage] = useState(1);
  const q = useDebounced(search.trim());

  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (q) params.set("q", q);
  if (plaqueId) params.set("plaque_id", plaqueId);
  const { reload, ...state } = useApi<Paged<Sro>>(`/sros?${params}`);
  const plaques = useApi<Paged<Plaque>>("/plaques?per_page=100");

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">SRO</h1>
        <Link href="/sro/nouveau" className="flex h-11 items-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white">
          Nouvelle SRO
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
            placeholder="Nom de la SRO, par exemple ABB456"
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

      <AsyncList state={state} reload={reload} empty={q || plaqueId ? "Aucune SRO ne correspond à votre recherche." : "Aucune SRO. Ajoutez-en une pour chaque sous-plaque."}>
        {(sros) => (
          <>
            <ul className="space-y-2">
              {sros.map((sro) => (
                <li key={sro.id} className="space-y-1 rounded-xl border border-zinc-200 bg-white p-4">
                  <p className="font-medium">
                    <Link href={`/sro/${sro.id}`} className="underline-offset-2 hover:underline">
                      {sro.code}
                    </Link>
                  </p>
                  <p className="text-sm text-zinc-700">
                    Sous-plaque {sro.zone?.code ?? "—"} · Plaque {sro.zone?.plaque?.name ?? "—"}
                  </p>
                  <p className="text-sm text-zinc-700">
                    {sro.olt_slot || sro.olt_port || sro.fiber_strand
                      ? `Départ OLT : slot ${sro.olt_slot ?? "—"} · port ${sro.olt_port ?? "—"} · brin ${sro.fiber_strand ?? "—"}`
                      : "Départ de l'OLT non renseigné"}
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
