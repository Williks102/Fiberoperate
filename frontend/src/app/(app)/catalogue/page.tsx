"use client";

import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { inputClass } from "@/components/field";
import { Pager } from "@/components/pager";
import { PriceCatalogImportPanel } from "@/components/price-catalog-import-panel";
import { useAuth } from "@/lib/auth/auth-context";
import { formatFcfa } from "@/lib/format";
import type { Paged } from "@/lib/types";
import { useApi } from "@/lib/use-api";
import { useDebounced } from "@/lib/use-debounced";

interface PriceItem {
  id: string;
  kind: "supply" | "service";
  kind_label: string;
  section: string;
  ref: string | null;
  designation: string;
  unit: string | null;
  unit_price: number | null;
}

/** Catalogue de prix de l'opérateur (bordereau) : commun à tous les projets, mis à jour par l'administrateur. */
export default function CataloguePage() {
  const { user } = useAuth();
  const [search, setSearch] = useState("");
  const [kind, setKind] = useState("");
  const [section, setSection] = useState("");
  const [page, setPage] = useState(1);
  const q = useDebounced(search.trim());

  const params = new URLSearchParams({ per_page: "25", page: String(page) });
  if (q) params.set("q", q);
  if (kind) params.set("kind", kind);
  if (section) params.set("section", section);
  const { reload, ...state } = useApi<Paged<PriceItem>>(`/price-items?${params}`);
  const sections = useApi<{ data: { section: string; kind: string }[] }>("/price-items/sections");

  const filtered = Boolean(q || kind || section);
  const sectionOptions = sections.status === "ok" ? sections.data.data.filter((s) => !kind || s.kind === kind) : [];

  return (
    <div className="space-y-4">
      <div className="space-y-1">
        <h1 className="text-xl font-semibold">Catalogue de prix</h1>
        <p className="text-sm text-slate-700">Le bordereau de l&apos;opérateur : les prix unitaires dont le devis d&apos;un projet est fait.</p>
      </div>

      {user?.role === "admin" && <PriceCatalogImportPanel onImported={() => (reload(), setPage(1))} />}

      <div className="grid gap-2 sm:grid-cols-3">
        <div className="space-y-1">
          <label htmlFor="search" className="text-sm font-medium">
            Rechercher
          </label>
          <input id="search" type="search" placeholder="Référence ou désignation" value={search} onChange={(e) => (setSearch(e.target.value), setPage(1))} className={inputClass} />
        </div>
        <div className="space-y-1">
          <label htmlFor="kind" className="text-sm font-medium">
            Type
          </label>
          <select id="kind" value={kind} onChange={(e) => (setKind(e.target.value), setSection(""), setPage(1))} className={inputClass}>
            <option value="">Fournitures et prestations</option>
            <option value="supply">Fournitures</option>
            <option value="service">Prestations</option>
          </select>
        </div>
        <div className="space-y-1">
          <label htmlFor="section" className="text-sm font-medium">
            Rubrique
          </label>
          <select id="section" value={section} onChange={(e) => (setSection(e.target.value), setPage(1))} className={inputClass}>
            <option value="">Toutes les rubriques</option>
            {sectionOptions.map((s) => (
              <option key={`${s.kind}-${s.section}`} value={s.section}>
                {s.section}
              </option>
            ))}
          </select>
        </div>
      </div>

      <AsyncList state={state} reload={reload} empty={filtered ? "Aucun article ne correspond à votre recherche." : "Le catalogue est vide. L'administrateur l'importe depuis un fichier CSV."}>
        {(items) => (
          <>
            <ul className="space-y-2">
              {items.map((item) => (
                <li key={item.id} className="space-y-1 rounded-xl border border-slate-200 bg-white p-4">
                  <p className="flex flex-wrap items-center justify-between gap-2">
                    <span className="font-medium">
                      {item.ref && <span className="mr-2 text-slate-700">{item.ref}</span>}
                      {item.designation}
                    </span>
                    <span className="font-medium tabular-nums">
                      {formatFcfa(item.unit_price)}
                      {item.unit && item.unit_price != null && <span className="text-slate-700"> / {item.unit}</span>}
                    </span>
                  </p>
                  <p className="text-sm text-slate-700">
                    {item.kind_label} · {item.section}
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
