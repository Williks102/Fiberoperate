"use client";

import Link from "next/link";
import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { inputClass } from "@/components/field";
import { Pager } from "@/components/pager";
import { Badge } from "@/components/ui/badge";
import { useAuth } from "@/lib/auth/auth-context";
import { ORDER_TONES } from "@/lib/labels";
import type { CustomerOrder, OrderStatusValue, Paged } from "@/lib/types";
import { useApi } from "@/lib/use-api";
import { useDebounced } from "@/lib/use-debounced";

const STATUSES: { value: OrderStatusValue; label: string }[] = [
  { value: "received", label: "Reçue" },
  { value: "in_study", label: "En étude" },
  { value: "studied", label: "Étudiée" },
  { value: "appointment_set", label: "Installation programmée" },
  { value: "installed", label: "Installée" },
  { value: "postponed", label: "Reportée" },
  { value: "failed", label: "Échec" },
  { value: "activated", label: "Activée" },
];

/** Les commandes des clients et leur avancement. */
export default function ClientsPage() {
  const { user } = useAuth();
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const q = useDebounced(search.trim());

  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (q) params.set("q", q);
  if (status) params.set("status", status);
  const { reload, ...state } = useApi<Paged<CustomerOrder>>(`/customer-orders?${params}`);
  const canImport = user?.role === "admin" || user?.role === "coordinator";

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">Clients</h1>
        {canImport && (
          <Link href="/clients/importer" className="flex h-11 items-center rounded-lg bg-primary px-4 text-sm font-medium text-white hover:bg-primary-hover">
            Importer des clients
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
            {STATUSES.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      <AsyncList state={state} reload={reload} empty={q || status ? "Aucun client ne correspond à votre recherche." : "Aucun client pour l'instant. Importez la liste des clients pour commencer."}>
        {(orders) => (
          <>
            <ul className="space-y-2">
              {orders.map((order) => (
                <li key={order.id}>
                  <Link href={`/clients/${order.id}`} className="block space-y-1 rounded-xl border border-slate-200 bg-white p-4 hover:border-primary">
                    <div className="flex items-start justify-between gap-3">
                      <p className="font-medium">{order.name}</p>
                      <Badge tone={ORDER_TONES[order.status.value]}>{order.status.label}</Badge>
                    </div>
                    <p className="text-sm text-slate-700">
                      {order.customer_ref}
                      {order.phone_1 ? ` · ${order.phone_1}` : ""}
                    </p>
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
    </div>
  );
}
