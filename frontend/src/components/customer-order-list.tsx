"use client";

import Link from "next/link";
import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { inputClass } from "@/components/field";
import { Pager } from "@/components/pager";
import { Badge } from "@/components/ui/badge";
import { formatDate } from "@/lib/format";
import { useAuth } from "@/lib/auth/auth-context";
import { ORDER_TONES, STUDY_RESULT_LABELS } from "@/lib/labels";
import type { CustomerOrder, OrderStatusValue, Paged } from "@/lib/types";
import { useApi } from "@/lib/use-api";
import { useDebounced } from "@/lib/use-debounced";

export type Stage = "instances" | "raccordables" | "raccordes";

const STATUSES: Record<Stage, { value: OrderStatusValue; label: string }[]> = {
  instances: [
    { value: "received", label: "Reçue" },
    { value: "in_study", label: "En étude" },
    { value: "studied", label: "Étude sans client raccordable" },
  ],
  raccordables: [
    { value: "studied", label: "Étudiée, à programmer" },
    { value: "appointment_set", label: "Installation programmée" },
    { value: "postponed", label: "Reportée" },
    { value: "failed", label: "Échec" },
  ],
  raccordes: [
    { value: "installed", label: "Installée" },
    { value: "activated", label: "Activée" },
  ],
};

const TEXTS: Record<Stage, { title: string; empty: string }> = {
  instances: { title: "Instances", empty: "Aucune instance. Importez la liste des clients pour commencer." },
  raccordables: { title: "Raccordables", empty: "Aucun client raccordable pour l'instant : ils arrivent ici une fois leur étude réalisée." },
  raccordes: { title: "Suivi raccordement", empty: "Aucun client raccordé pour l'instant." },
};

const STAGE_PAGES: Record<Stage, string> = { instances: "Instances", raccordables: "Raccordables", raccordes: "Suivi raccordement" };

/** Recherche dans toutes les étapes : montre les clients trouvés ailleurs que dans la liste affichée, avec leur étape. */
function OtherStageMatches({ q, stage }: { q: string; stage: Stage }) {
  const result = useApi<Paged<CustomerOrder>>(`/customer-orders?${new URLSearchParams({ q, per_page: "50" })}`);
  if (result.status !== "ok") return null;
  const others = result.data.data.filter((o) => o.stage !== stage);
  if (others.length === 0) return null;

  return (
    <section aria-label="Trouvés dans les autres étapes" className="space-y-2 rounded-xl border border-dashed border-slate-300 p-3">
      <h2 className="text-sm font-semibold">Trouvés dans les autres étapes</h2>
      <ul className="space-y-2">
        {others.map((order) => (
          <li key={order.id}>
            <Link href={`/clients/${order.id}`} className="block space-y-1 rounded-lg bg-white p-3 hover:ring-1 hover:ring-primary">
              <div className="flex items-start justify-between gap-3">
                <p className="font-medium">{order.name}</p>
                <Badge tone={ORDER_TONES[order.status.value]}>{order.status.label}</Badge>
              </div>
              <p className="text-sm text-slate-700">
                {order.customer_ref} · dans {STAGE_PAGES[order.stage]}
              </p>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}

/** Une des trois listes du raccordement : instances (étude à faire), raccordables (étude faite), raccordés (installés). */
export function CustomerOrderList({ stage }: { stage: Stage }) {
  const { user } = useAuth();
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const q = useDebounced(search.trim());

  const params = new URLSearchParams({ per_page: "20", page: String(page), stage });
  if (q) params.set("q", q);
  if (status) params.set("status", status);
  const { reload, ...state } = useApi<Paged<CustomerOrder>>(`/customer-orders?${params}`);
  const canImport = user?.role === "admin" || user?.role === "coordinator";

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">{TEXTS[stage].title}</h1>
        {stage === "instances" && canImport && (
          <Link href="/clients/importer" className="flex h-11 items-center rounded-lg bg-primary px-4 text-sm font-medium text-white hover:bg-primary-hover">
            Importer les instances
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
            placeholder="Nom, ID client, téléphone ou ligne FT"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            className={inputClass}
          />
        </div>
        <div className="space-y-1 sm:w-64">
          <label htmlFor="status" className="text-sm font-medium">
            Avancement
          </label>
          <select
            id="status"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
            className={inputClass}
          >
            <option value="">Tous les avancements</option>
            {STATUSES[stage].map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      <AsyncList state={state} reload={reload} empty={q || status ? "Aucun client ne correspond à votre recherche." : TEXTS[stage].empty}>
        {(orders) => (
          <>
            <ul className="space-y-2">
              {orders.map((order) => (
                <li key={order.id}>
                  <Link href={`/clients/${order.id}`} className="block space-y-1 rounded-xl border border-slate-200 bg-white p-4 hover:border-primary">
                    <div className="flex items-start justify-between gap-3">
                      <p className="font-medium">{order.name}</p>
                      <div className="flex flex-wrap justify-end gap-1">
                        {order.study_result && order.study_result !== "feasible" && <Badge tone="warning">{STUDY_RESULT_LABELS[order.study_result]}</Badge>}
                        <Badge tone={ORDER_TONES[order.status.value]}>{order.status.label}</Badge>
                      </div>
                    </div>
                    <p className="text-sm text-slate-700">
                      {order.customer_ref}
                      {order.phone_1 ? ` · ${order.phone_1}` : ""}
                    </p>
                    {order.interventions?.[0] && (
                      <p className="text-sm font-medium text-primary-ink">
                        {order.interventions[0].type === "study" ? "Étude" : "Installation"} prévue le {formatDate(order.interventions[0].planned_date)} · {order.interventions[0].technician ?? "—"}
                      </p>
                    )}
                    <p className="text-sm text-slate-700">
                      {order.pco ? `PCO ${order.pco.code}` : order.zone ? `Sous-plaque ${order.zone.code}` : "Pas encore rattaché à un PCO"}
                    </p>
                  </Link>
                </li>
              ))}
            </ul>
            {state.status === "ok" && <Pager page={state.data.meta.current_page} lastPage={state.data.meta.last_page} onChange={setPage} />}
          </>
        )}
      </AsyncList>

      {q.length >= 2 && <OtherStageMatches q={q} stage={stage} />}
    </div>
  );
}
