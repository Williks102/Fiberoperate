"use client";

import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { inputClass } from "@/components/field";
import { Pager } from "@/components/pager";
import { PCO_STATUS_LABELS, pcoStatusLabel } from "@/lib/labels";
import type { Paged, Pco, PcoStatus, Plaque } from "@/lib/types";
import { useApi } from "@/lib/use-api";
import { useDebounced } from "@/lib/use-debounced";

/** Administration des PCO (boîtiers) : recherche par code, filtres par plaque et statut. */
export default function PcoPage() {
  const [search, setSearch] = useState("");
  const [plaqueId, setPlaqueId] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const q = useDebounced(search.trim());

  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (q) params.set("q", q);
  if (plaqueId) params.set("plaque_id", plaqueId);
  if (status) params.set("status", status);
  const { reload, ...state } = useApi<Paged<Pco>>(`/pcos?${params}`);
  const plaques = useApi<Paged<Plaque>>("/plaques?per_page=100");

  const reset = (apply: () => void) => {
    apply();
    setPage(1);
  };

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold">PCO</h1>

      <div className="grid gap-2 sm:grid-cols-3">
        <div className="space-y-1">
          <label htmlFor="search" className="text-sm font-medium">
            Rechercher
          </label>
          <input
            id="search"
            type="search"
            placeholder="Code, par exemple FAY03_PCO001"
            value={search}
            onChange={(e) => reset(() => setSearch(e.target.value))}
            className={inputClass}
          />
        </div>
        <div className="space-y-1">
          <label htmlFor="plaque" className="text-sm font-medium">
            Plaque
          </label>
          <select id="plaque" value={plaqueId} onChange={(e) => reset(() => setPlaqueId(e.target.value))} className={inputClass}>
            <option value="">Toutes les plaques</option>
            {plaques.status === "ok" &&
              plaques.data.data.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
          </select>
        </div>
        <div className="space-y-1">
          <label htmlFor="status" className="text-sm font-medium">
            Statut
          </label>
          <select id="status" value={status} onChange={(e) => reset(() => setStatus(e.target.value))} className={inputClass}>
            <option value="">Tous les statuts</option>
            {(Object.keys(PCO_STATUS_LABELS) as PcoStatus[]).map((s) => (
              <option key={s} value={s}>
                {PCO_STATUS_LABELS[s]}
              </option>
            ))}
          </select>
        </div>
      </div>

      <AsyncList state={state} reload={reload} empty={q || plaqueId || status ? "Aucun PCO ne correspond à votre recherche." : "Aucun PCO. Ils sont créés à l'import du réseau."}>
        {(pcos) => (
          <>
            <ul className="space-y-2">
              {pcos.map((pco) => (
                <li key={pco.id} className="space-y-1 rounded-xl border border-zinc-200 bg-white p-4">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="font-medium">{pco.code}</p>
                    <span className="flex items-center gap-2">
                      {pco.is_blocked && <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-900">Bloqué</span>}
                      <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-900">{pcoStatusLabel(pco)}</span>
                    </span>
                  </div>
                  <p className="text-sm text-zinc-700">
                    Plaque {pco.zone?.plaque?.name ?? "—"} · Sous-plaque {pco.zone?.code ?? "—"} · Splitter {pco.jdv?.code ?? "—"}
                  </p>
                  <p className="text-sm text-zinc-700">{pco.latitude && pco.longitude ? `GPS : ${pco.latitude}, ${pco.longitude}` : "Pas de coordonnées GPS"}</p>
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
