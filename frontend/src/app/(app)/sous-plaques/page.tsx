"use client";

import Link from "next/link";
import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { Pager } from "@/components/pager";
import { inputClass } from "@/components/field";
import { VENDOR_LABELS, ZONE_STATUS_LABELS } from "@/lib/labels";
import type { Paged, Plaque, Zone } from "@/lib/types";
import { useApi } from "@/lib/use-api";
import { useDebounced } from "@/lib/use-debounced";

/** Administration des sous-plaques (zones) ; la consultation passe par l'écran Réseau. */
export default function SousPlaquesPage() {
  const [search, setSearch] = useState("");
  const [plaqueId, setPlaqueId] = useState("");
  const [page, setPage] = useState(1);
  const q = useDebounced(search.trim());

  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (q) params.set("q", q);
  if (plaqueId) params.set("plaque_id", plaqueId);
  const { reload, ...state } = useApi<Paged<Zone>>(`/zones?${params}`);
  const plaques = useApi<Paged<Plaque>>("/plaques?per_page=100");

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">Sous-plaques</h1>
        <Link href="/sous-plaques/nouveau" className="flex h-11 items-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white">
          Nouvelle sous-plaque
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
            placeholder="Code ou nom"
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

      <AsyncList state={state} reload={reload} empty={q || plaqueId ? "Aucune sous-plaque ne correspond à votre recherche." : "Aucune sous-plaque. Elles sont créées à l'import du réseau, ou ici."}>
        {(zones) => (
          <>
            <ul className="space-y-2">
              {zones.map((zone) => (
                <li key={zone.id} className="space-y-1 rounded-xl border border-zinc-200 bg-white p-4">
                  <p className="font-medium">
                    <Link href={`/sous-plaques/${zone.id}`} className="underline-offset-2 hover:underline">
                      {zone.name ? `${zone.code} · ${zone.name}` : zone.code}
                    </Link>
                  </p>
                  <p className="text-sm text-zinc-600">
                    Plaque {zone.plaque?.name ?? "—"} · {zone.vendor ? VENDOR_LABELS[zone.vendor] : "Fabricant non renseigné"} · {ZONE_STATUS_LABELS[zone.status]}
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
