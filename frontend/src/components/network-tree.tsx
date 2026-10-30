"use client";

import Link from "next/link";
import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { useAuth } from "@/lib/auth/auth-context";
import { pcoStatusLabel, ZONE_STATUS_LABELS } from "@/lib/labels";
import type { Jdv, Paged, Pco, Plaque, Zone } from "@/lib/types";
import { useApi } from "@/lib/use-api";

const PER_PAGE = 100;

function Badge({ children, tone = "neutral" }: { children: React.ReactNode; tone?: "neutral" | "danger" }) {
  const colors = tone === "danger" ? "bg-red-100 text-red-800" : "bg-zinc-100 text-zinc-700";
  return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${colors}`}>{children}</span>;
}

/** Ligne dépliable ; les enfants ne sont chargés qu'à l'ouverture. */
function Node({
  title,
  badges,
  children,
}: {
  title: string;
  badges?: React.ReactNode;
  children: React.ReactNode;
}) {
  const [open, setOpen] = useState(false);

  return (
    <li className="rounded-xl border border-zinc-200 bg-white">
      <button
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        className="flex min-h-11 w-full items-center justify-between gap-3 p-3 text-left"
      >
        <span className="font-medium">{title}</span>
        <span className="flex flex-wrap items-center justify-end gap-2">
          {badges}
          <span aria-hidden="true" className="text-zinc-700">
            {open ? "▾" : "▸"}
          </span>
        </span>
      </button>
      {open && <div className="border-t border-zinc-100 p-3 pl-4">{children}</div>}
    </li>
  );
}

function Pcos({ jdvId }: { jdvId: string }) {
  const { reload, ...state } = useApi<Paged<Pco>>(`/pcos?jdv_id=${jdvId}&per_page=${PER_PAGE}`);

  return (
    <AsyncList state={state} reload={reload} empty="Aucun PCO sur ce splitter.">
      {(pcos) => (
        <ul className="space-y-2">
          {pcos.map((pco) => (
            <li key={pco.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-50 px-3 py-2 text-sm">
              <span className="font-medium">{pco.code}</span>
              <span className="flex items-center gap-2">
                {pco.is_blocked && <Badge tone="danger">Bloqué</Badge>}
                <Badge>{pcoStatusLabel(pco)}</Badge>
              </span>
            </li>
          ))}
        </ul>
      )}
    </AsyncList>
  );
}

function Jdvs({ zoneId }: { zoneId: string }) {
  const { reload, ...state } = useApi<Paged<Jdv>>(`/jdvs?zone_id=${zoneId}&per_page=${PER_PAGE}`);

  return (
    <AsyncList state={state} reload={reload} empty="Aucun splitter (JDV) dans cette zone.">
      {(jdvs) => (
        <ul className="space-y-2">
          {jdvs.map((jdv) => (
            <Node key={jdv.id} title={`Splitter ${jdv.code}`}>
              <Pcos jdvId={jdv.id} />
            </Node>
          ))}
        </ul>
      )}
    </AsyncList>
  );
}

function Zones({ plaqueId }: { plaqueId: string }) {
  const { reload, ...state } = useApi<Paged<Zone>>(`/zones?plaque_id=${plaqueId}&per_page=${PER_PAGE}`);

  return (
    <AsyncList state={state} reload={reload} empty="Aucune sous-plaque dans cette plaque.">
      {(zones) => (
        <ul className="space-y-2">
          {zones.map((zone) => (
            <Node
              key={zone.id}
              title={zone.name ? `${zone.code} · ${zone.name}` : zone.code}
              badges={
                <>
                  {zone.access_point_label && <Badge>{zone.access_point_label}</Badge>}
                  <Badge>{ZONE_STATUS_LABELS[zone.status]}</Badge>
                </>
              }
            >
              <Jdvs zoneId={zone.id} />
            </Node>
          ))}
        </ul>
      )}
    </AsyncList>
  );
}

/** Plaque → sous-plaques (zones) → splitters (JDV) → PCO, en lecture, dans le périmètre du rôle. */
export function NetworkTree() {
  const { user } = useAuth();
  // Administrateur, DT (superviseur), chef du BE et techniciens du BE ouvrent la fiche de modification.
  const canEditPlaque = user?.role === "admin" || user?.role === "dt" || user?.role === "be_manager" || (user?.role === "technician" && user.skills?.includes("drawing") === true);
  const { reload, ...state } = useApi<Paged<Plaque>>(`/plaques?per_page=${PER_PAGE}`);

  return (
    <AsyncList state={state} reload={reload} empty="Aucune plaque dans votre périmètre.">
      {(plaques) => (
        <ul className="space-y-2">
          {plaques.map((plaque) => (
            <Node key={plaque.id} title={`Plaque ${plaque.name}`}>
              <p className="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm text-zinc-700">
                <span>
                  OLT : {plaque.olt?.name ?? "non renseigné"} · Superviseur : {plaque.supervisor?.name ?? "non désigné"}
                </span>
                {canEditPlaque && (
                  <Link href={`/plaques/${plaque.id}`} className="inline-flex h-11 items-center rounded-lg border border-zinc-300 px-4 font-medium text-zinc-900">
                    Modifier la plaque
                  </Link>
                )}
              </p>
              <Zones plaqueId={plaque.id} />
            </Node>
          ))}
        </ul>
      )}
    </AsyncList>
  );
}
