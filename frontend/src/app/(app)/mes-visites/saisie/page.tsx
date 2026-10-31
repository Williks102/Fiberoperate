"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useMemo } from "react";
import { InstallationResultPanel } from "@/components/installation-result-panel";
import { StudyFormPanel } from "@/components/study-form-panel";
import { Badge } from "@/components/ui/badge";
import { clientName, visitDay } from "@/lib/format";
import { pendingOrderIds } from "@/lib/offline/outbox";
import { useLive } from "@/lib/offline/use-live";
import { readVisit } from "@/lib/offline/visits";
import type { Visit } from "@/lib/types";

const NO_ORDERS: string[] = [];

/**
 * Saisie du résultat d'une visite, depuis « Mes visites ». Tout vient du téléphone (visite, motifs, réglages, PCO) : l'écran s'ouvre
 * et s'enregistre sans réseau ; la saisie part à la reconnexion (file d'envoi). La visite se choisit par `?visit=` : une seule page
 * pour toutes les visites, donc une seule page à garder pour l'usage hors ligne.
 */
export default function SaisiePage() {
  const id = useSearchParams().get("visit");
  const router = useRouter();
  const load = useMemo(() => () => (id ? readVisit(id) : Promise.resolve(undefined)), [id]);
  // null : lecture en cours ; undefined : cette visite n'est (plus) sur le téléphone.
  const visit = useLive<Visit | undefined | null>(load, null);
  const waiting = useLive(pendingOrderIds, NO_ORDERS);

  return (
    <div className="space-y-4">
      <Link href="/mes-visites" className="inline-flex h-11 items-center text-sm font-medium underline">
        ← Mes visites
      </Link>

      {visit === null && <div aria-busy="true" className="h-40 animate-pulse rounded-xl bg-slate-200" />}

      {visit === undefined && (
        <p role="status" className="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-700">
          Cette visite n&apos;est plus sur ce téléphone : elle a été réalisée, reprogrammée ou confiée à un autre technicien. Revenez à vos visites.
        </p>
      )}

      {visit && <Entry visit={visit} waiting={waiting.includes(visit.order.id)} onDone={() => router.push("/mes-visites")} />}
    </div>
  );
}

function Entry({ visit, waiting, onDone }: { visit: Visit; waiting: boolean; onDone: () => void }) {
  const day = visitDay(visit.planned_date);
  const phones = [visit.order.phone_1, visit.order.phone_2].filter((p, i, all): p is string => !!p && all.indexOf(p) === i);
  const order = { id: visit.order.id, name: visit.order.name };

  return (
    <>
      <section className="space-y-2 rounded-xl border border-slate-200 bg-white p-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <Badge tone={visit.type === "study" ? "info" : "success"}>{visit.type === "study" ? "Étude" : "Installation"}</Badge>
          <Badge tone={day.late ? "danger" : "neutral"}>{day.label}</Badge>
        </div>
        <h1 className="text-xl font-semibold">{clientName(visit.order.name)}</h1>
        <p className="text-sm text-slate-700">
          {visit.order.customer_ref}
          {visit.order.pco ? ` · PCO ${visit.order.pco}` : visit.order.zone ? ` · Sous-plaque ${visit.order.zone}` : ""}
        </p>
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
        </div>
      </section>

      {waiting ? (
        <section role="status" className="space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-4">
          <h2 className="font-semibold text-amber-900">Résultat enregistré sur ce téléphone</h2>
          <p className="text-sm text-amber-900">Il est en attente d&apos;envoi et partira automatiquement dès que le réseau est là. Vous n&apos;avez rien à refaire.</p>
          <Link href="/mes-visites" className="flex h-11 w-fit items-center rounded-lg bg-primary px-4 text-sm font-medium text-white hover:bg-primary-hover">
            Retour à mes visites
          </Link>
        </section>
      ) : visit.type === "study" ? (
        <StudyFormPanel order={order} showInternal={false} onDone={onDone} />
      ) : (
        <InstallationResultPanel order={order} onDone={onDone} />
      )}
    </>
  );
}
