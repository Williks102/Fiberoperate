"use client";

import Link from "next/link";
import { AsyncList } from "@/components/async-list";
import { Badge } from "@/components/ui/badge";
import { clientName, visitDay } from "@/lib/format";
import type { Paged, Visit } from "@/lib/types";
import { useApi } from "@/lib/use-api";

/** Les visites du technicien : ses études et installations à faire, avec de quoi joindre le client et le retrouver. */
export default function MesVisitesPage() {
  const { reload, ...state } = useApi<Paged<Visit>>("/my-visits?per_page=100");

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold">Mes visites</h1>

      <AsyncList state={state} reload={reload} empty="Aucune visite prévue pour l'instant. Le coordinateur vous confiera les prochaines.">
        {(visits) => (
          <ul className="space-y-3">
            {visits.map((visit) => {
              const day = visitDay(visit.planned_date);
              const phones = [visit.order.phone_1, visit.order.phone_2].filter((p, i, all): p is string => !!p && all.indexOf(p) === i);

              return (
                <li key={visit.id} className="space-y-2 rounded-xl border border-slate-200 bg-white p-4">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <Badge tone={visit.type === "study" ? "info" : "success"}>{visit.type === "study" ? "Étude" : "Installation"}</Badge>
                    <Badge tone={day.late ? "danger" : day.label === "Aujourd'hui" ? "warning" : "neutral"}>{day.label}</Badge>
                  </div>

                  <div>
                    <p className="font-medium">{clientName(visit.order.name)}</p>
                    <p className="text-sm text-slate-700">
                      {visit.order.customer_ref}
                      {visit.order.pco ? ` · PCO ${visit.order.pco}` : visit.order.zone ? ` · Sous-plaque ${visit.order.zone}` : ""}
                    </p>
                  </div>

                  {visit.order.address_raw && <p className="text-sm">{visit.order.address_raw}</p>}
                  {visit.type === "customer_connection" && (
                    <p className="rounded-lg bg-primary-soft p-2 text-sm text-primary-ink">
                      {visit.order.ont_serial || visit.order.fixed_line
                        ? `Box : ${[visit.order.ont_serial && `SN ${visit.order.ont_serial}`, visit.order.fixed_line && `ligne fixe ${visit.order.fixed_line}`].filter(Boolean).join(" · ")}`
                        : "Box pas encore attribuée : demandez-la au coordinateur."}
                    </p>
                  )}
                  {visit.observation && <p className="rounded-lg bg-slate-50 p-2 text-sm text-slate-700">{visit.observation}</p>}

                  <div className="flex flex-wrap gap-2 pt-1">
                    {phones.map((phone) => (
                      <a key={phone} href={`tel:${phone}`} className="flex h-11 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium">
                        Appeler {phone}
                      </a>
                    ))}
                    <Link href={`/clients/${visit.order.id}`} className="flex h-11 items-center rounded-lg bg-primary px-4 text-sm font-medium text-white hover:bg-primary-hover">
                      {visit.type === "study" ? "Saisir l'étude" : "Saisir l'installation"}
                    </Link>
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </AsyncList>
    </div>
  );
}
