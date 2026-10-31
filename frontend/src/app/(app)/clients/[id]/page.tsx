"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { StudyFormPanel } from "@/components/study-form-panel";
import { InstallationResultPanel } from "@/components/installation-result-panel";
import { PlanningPanel } from "@/components/planning-panel";
import { Badge } from "@/components/ui/badge";
import { useAuth } from "@/lib/auth/auth-context";
import { clientName, formatDate, formatDateTime } from "@/lib/format";
import { ORDER_TONES, STUDY_RESULT_LABELS } from "@/lib/labels";
import type { CustomerOrder } from "@/lib/types";
import { useApi } from "@/lib/use-api";

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 py-2 sm:flex-row sm:gap-4">
      <dt className="text-sm text-slate-600 sm:w-48 sm:shrink-0">{label}</dt>
      <dd className="text-sm">{children || "—"}</dd>
    </div>
  );
}

/** Fiche d'un client : identité, localisation, avancement et historique. */
export default function ClientPage() {
  const { id } = useParams<{ id: string }>();
  const result = useApi<{ data: CustomerOrder }>(`/customer-orders/${id}`);

  return (
    <div className="space-y-5">
      <Link href="/instances" className="inline-flex h-11 items-center text-sm font-medium underline">
        ← Retour
      </Link>

      {result.status === "loading" && <div aria-busy="true" className="h-64 animate-pulse rounded-xl bg-slate-200" />}

      {result.status === "error" && (
        <div role="alert" className="space-y-3 rounded-xl bg-red-50 p-4 text-sm text-red-800">
          <p>{result.message}</p>
          <button onClick={result.reload} className="h-11 rounded-lg border border-red-300 px-4 font-medium">
            Réessayer
          </button>
        </div>
      )}

      {result.status === "ok" && <Detail order={result.data.data} reload={result.reload} />}
    </div>
  );
}

function Detail({ order, reload }: { order: CustomerOrder; reload: () => void }) {
  const { user } = useAuth();
  const canPlan = user?.role === "admin" || user?.role === "coordinator";
  const planned = order.interventions?.find((i) => i.type === "study" && i.status === "planned");
  const readyForInstallation =
    order.status.value === "appointment_set" || order.status.value === "postponed" || order.status.value === "failed" || (order.status.value === "studied" && order.study_result === "feasible");
  const plannedInstall = order.interventions?.find((i) => i.type === "customer_connection" && i.status === "planned");
  const canRecordInstall =
    order.status.value === "appointment_set" && !!plannedInstall && (canPlan || (user?.role === "technician" && plannedInstall.technician_id === user.id));
  const canRecord = order.status.value === "in_study" && !!planned && (canPlan || (user?.role === "technician" && planned.technician_id === user.id));

  return (
    <>
      <div className="flex items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">{clientName(order.name)}</h1>
          <p className="text-sm text-slate-700">ID client {order.customer_ref}</p>
        </div>
        <Badge tone={ORDER_TONES[order.status.value]}>{order.status.label}</Badge>
      </div>

      {canPlan && order.status.value === "received" && <PlanningPanel mode="study" order={order} onDone={reload} />}

      {canPlan && readyForInstallation && <PlanningPanel mode="installation" order={order} reprogram={order.status.value === "appointment_set"} onDone={reload} />}

      {canRecordInstall && <InstallationResultPanel order={order} onDone={reload} />}

      {canRecord && <StudyFormPanel order={order} showInternal={canPlan} onDone={reload} />}

      <div className="grid gap-4 lg:grid-cols-2">
        <section className="rounded-xl border border-slate-200 bg-white p-4">
          <h2 className="font-semibold">Le client</h2>
          <dl className="divide-y divide-slate-100">
            <Row label="Téléphone">{[order.phone_1, order.phone_2].filter(Boolean).join(" · ")}</Row>
            <Row label="Adresse">{order.address_raw}</Row>
            <Row label="Compte client">{order.account_number}</Row>
            <Row label="Ligne FT">{order.ft_line}</Row>
            <Row label="Enregistré le">{order.registered_at ? formatDate(order.registered_at) : null}</Row>
            <Row label="Envoyé au prestataire le">{order.sent_to_contractor_at ? formatDate(order.sent_to_contractor_at) : null}</Row>
          </dl>
        </section>

        <section className="rounded-xl border border-slate-200 bg-white p-4">
          <h2 className="font-semibold">Raccordement</h2>
          <dl className="divide-y divide-slate-100">
            <Row label="Sous-plaque">{order.zone?.code}</Row>
            <Row label="PCO">{order.pco?.code}</Row>
            <Row label="Résultat de l'étude">{order.study_result ? STUDY_RESULT_LABELS[order.study_result] : null}</Row>
            <Row label="Distance au PCO">{order.distance_m != null ? `${order.distance_m} m` : null}</Row>
            <Row label="État de ligne opérateur">{order.operator_line_status}</Row>
            <Row label="Observation">{order.observation}</Row>
          </dl>
        </section>
      </div>

      {order.studies && order.studies.length > 0 && (
        <section className="rounded-xl border border-slate-200 bg-white p-4">
          <h2 className="font-semibold">Étude</h2>
          {order.studies.map((st) => (
            <dl key={st.id} className="divide-y divide-slate-100">
              <Row label="Résultat">{st.result_label}</Row>
              {st.result === "feasible" && (
                <>
                  <Row label="Poteaux béton">{`${st.concrete_poles_existing} existants · ${st.concrete_poles_to_install} à installer`}</Row>
                  <Row label="Poteaux métalliques">{`${st.metal_poles_existing} existants · ${st.metal_poles_to_install} à installer`}</Row>
                  <Row label="Distance">{st.distance_m != null ? `${st.distance_m} m` : null}</Row>
                </>
              )}
              <Row label="Problème rencontré">{st.problem}</Row>
              <Row label="Commentaires de terrain">{st.field_comments}</Row>
              {st.internal_comments ? <Row label="Commentaires internes">{st.internal_comments}</Row> : null}
            </dl>
          ))}
        </section>
      )}

      {order.interventions && order.interventions.length > 0 && (
        <section className="rounded-xl border border-slate-200 bg-white p-4">
          <h2 className="font-semibold">Interventions</h2>
          <ul className="mt-2 space-y-2">
            {order.interventions.map((i) => (
              <li key={i.id} className="text-sm">
                <span className="font-medium">{i.type === "study" ? "Étude" : "Installation"}</span> · {formatDate(i.planned_date)} · {i.technician ?? "—"} · {i.status_label}
                {i.outcome ? ` · ${i.outcome === "installed" ? "Installé" : i.outcome === "postponed" ? "Reporté" : "Échec"}` : ""}
                {i.reason ? ` : ${i.reason}` : ""}
                {i.observation ? <span className="block text-slate-700">{i.observation}</span> : null}
              </li>
            ))}
          </ul>
        </section>
      )}

      <section className="rounded-xl border border-slate-200 bg-white p-4">
        <h2 className="font-semibold">Historique</h2>
        {order.history && order.history.length > 0 ? (
          <ol className="mt-2 space-y-2">
            {order.history.map((h, i) => (
              <li key={i} className="text-sm">
                <span className="font-medium">{h.to_label}</span> · {formatDateTime(h.at)}
                {h.author ? ` · ${h.author}` : ""}
                {h.observation ? <span className="block text-slate-700">{h.observation}</span> : null}
              </li>
            ))}
          </ol>
        ) : (
          <p className="mt-2 text-sm text-slate-700">{"Aucun changement d'avancement pour l'instant."}</p>
        )}
      </section>
    </>
  );
}
