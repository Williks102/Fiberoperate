"use client";

import { useId, useState } from "react";
import { inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import { formatFcfa } from "@/lib/format";
import type { Paged } from "@/lib/types";
import { useApi } from "@/lib/use-api";
import { useDebounced } from "@/lib/use-debounced";

interface QuoteLine {
  id: string;
  price_item_id: string;
  ref: string | null;
  designation: string;
  section: string;
  unit: string | null;
  unit_price: number | null;
  study_quantity: number;
  study_total: number | null;
  done_quantity: number | null;
  done_total: number | null;
  gap_total: number | null;
}

interface Quote {
  lines: QuoteLine[];
  totals: { study: number; done: number; gap: number; missing_price: number };
  can: { study: boolean; done: boolean };
}

interface CatalogItem {
  id: string;
  ref: string | null;
  designation: string;
  section: string;
  unit: string | null;
  unit_price: number | null;
}

/** Devis du projet : lignes du catalogue de prix, quantités d'étude (avant-projet) puis réalisées (déploiement), écart. */
export function ProjectQuotePanel({ projectId }: { projectId: string }) {
  const { reload, ...state } = useApi<{ data: Quote }>(`/projects/${projectId}/quote`);
  const [error, setError] = useState<string | null>(null);

  async function call(path: string, method: "PUT" | "DELETE", body?: object) {
    setError(null);
    try {
      await apiFetch(`/projects/${projectId}/quote/${path}`, { method, body: body ? JSON.stringify(body) : undefined }, getToken());
      reload();
    } catch (e) {
      setError(e instanceof ApiError ? (Object.values(e.errors)[0]?.[0] ?? e.message) : "Une erreur est survenue.");
    }
  }

  if (state.status === "loading") return <div aria-busy="true" className="h-24 animate-pulse rounded-xl bg-slate-200" />;
  if (state.status === "error") return null; // pas de droit sur le devis : la section n'apparaît pas

  const { lines, totals, can } = state.data.data;

  return (
    <section aria-labelledby="devis" className="space-y-3">
      <h2 id="devis" className="text-lg font-semibold">
        Devis
      </h2>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {[
          ["Prévu", formatFcfa(totals.study)],
          ["Réalisé", formatFcfa(totals.done)],
          ["Écart (réalisé − prévu)", formatFcfa(totals.gap)],
        ].map(([label, value]) => (
          <div key={label} className="rounded-xl border border-slate-200 bg-white p-3">
            <dt className="text-xs text-slate-700">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
          </div>
        ))}
      </dl>
      {totals.missing_price > 0 && <p className="text-sm text-slate-700">{totals.missing_price} ligne(s) sans prix dans le catalogue : elles comptent pour 0.</p>}
      {error && (
        <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
          {error}
        </p>
      )}

      {lines.length === 0 ? (
        <p className="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-700">{can.study ? "Aucune ligne. Cherchez un article du catalogue pour commencer." : "Aucune ligne dans le devis."}</p>
      ) : (
        <ul className="space-y-2">
          {lines.map((line) => (
            <li key={line.id} className="space-y-2 rounded-xl border border-slate-200 bg-white p-4 text-sm">
              <p className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-medium">
                  {line.ref && <span className="mr-2 text-slate-700">{line.ref}</span>}
                  {line.designation}
                </span>
                <span className="tabular-nums text-slate-700">
                  {formatFcfa(line.unit_price)}
                  {line.unit && line.unit_price != null ? ` / ${line.unit}` : ""}
                </span>
              </p>
              <div className="grid gap-2 sm:grid-cols-2">
                <QuantityField label="Quantité d'étude" value={line.study_quantity} total={line.study_total} editable={can.study} onSave={(v) => call("lines", "PUT", { price_item_id: line.price_item_id, study_quantity: v })} />
                <QuantityField label="Quantité réalisée" value={line.done_quantity} total={line.done_total} editable={can.done} onSave={(v) => call("lines", "PUT", { price_item_id: line.price_item_id, done_quantity: v })} />
              </div>
              {line.gap_total != null && <p className="text-slate-700">Écart : {formatFcfa(line.gap_total)}</p>}
              {can.study && (
                <button onClick={() => call(`lines/${line.id}`, "DELETE")} className="h-11 rounded-lg border border-slate-300 px-4 font-medium">
                  Retirer la ligne
                </button>
              )}
            </li>
          ))}
        </ul>
      )}

      {can.study && <AddLine onAdd={(id, quantity) => call("lines", "PUT", { price_item_id: id, study_quantity: quantity })} />}
    </section>
  );
}

function QuantityField({ label, value, total, editable, onSave }: { label: string; value: number | null; total: number | null; editable: boolean; onSave: (value: number) => void }) {
  const [draft, setDraft] = useState(value == null ? "" : String(value));
  const id = useId();

  return (
    <div className="space-y-1">
      <label htmlFor={id} className="text-xs text-slate-700">
        {label}
      </label>
      {editable ? (
        <input
          id={id}
          inputMode="numeric"
          value={draft}
          onChange={(e) => setDraft(e.target.value.replace(/\D/g, ""))}
          onBlur={() => draft !== "" && Number(draft) !== value && onSave(Number(draft))}
          className={inputClass}
        />
      ) : (
        <p id={id} className="font-medium tabular-nums">
          {value ?? "—"}
        </p>
      )}
      <p className="text-xs text-slate-700">Montant : {formatFcfa(total)}</p>
    </div>
  );
}

function AddLine({ onAdd }: { onAdd: (priceItemId: string, quantity: number) => void }) {
  const [search, setSearch] = useState("");
  const [quantity, setQuantity] = useState("1");
  const q = useDebounced(search.trim());
  const result = useApi<Paged<CatalogItem>>(`/price-items?per_page=6&q=${encodeURIComponent(q)}`);

  return (
    <div className="space-y-2 rounded-xl border border-slate-200 bg-white p-4">
      <h3 className="font-semibold">Ajouter une ligne du catalogue</h3>
      <div className="grid gap-2 sm:grid-cols-3">
        <div className="space-y-1 sm:col-span-2">
          <label htmlFor="quote-search" className="text-sm font-medium">
            Article
          </label>
          <input id="quote-search" type="search" placeholder="Référence ou désignation" value={search} onChange={(e) => setSearch(e.target.value)} className={inputClass} />
        </div>
        <div className="space-y-1">
          <label htmlFor="quote-quantity" className="text-sm font-medium">
            Quantité d&apos;étude
          </label>
          <input id="quote-quantity" inputMode="numeric" value={quantity} onChange={(e) => setQuantity(e.target.value.replace(/\D/g, ""))} className={inputClass} />
        </div>
      </div>
      {q !== "" && result.status === "ok" && (
        <ul className="space-y-2">
          {result.data.data.length === 0 && <li className="text-sm text-slate-700">Aucun article ne correspond.</li>}
          {result.data.data.map((item) => (
            <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 p-3 text-sm">
              <span>
                {item.ref && <span className="mr-2 text-slate-700">{item.ref}</span>}
                {item.designation} · {formatFcfa(item.unit_price)}
              </span>
              <button
                onClick={() => (onAdd(item.id, Number(quantity || 0)), setSearch(""))}
                disabled={quantity === ""}
                className="h-11 rounded-lg bg-primary hover:bg-primary-hover px-4 font-medium text-white disabled:opacity-60"
              >
                Ajouter
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
