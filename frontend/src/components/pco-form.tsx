"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { Badge } from "@/components/ui/badge";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import { pcoStatusLabel } from "@/lib/labels";
import type { Jdv, Paged, Pco, Plaque, Zone } from "@/lib/types";
import { useApi } from "@/lib/use-api";

function ZoneSelect({ plaqueId, value, onChange }: { plaqueId: string; value: string; onChange: (id: string) => void }) {
  const zones = useApi<Paged<Zone>>(`/zones?plaque_id=${plaqueId}&per_page=100`);

  return (
    <select id="zone" value={value} onChange={(e) => onChange(e.target.value)} className={inputClass} required>
      <option value="">Choisir une sous-plaque</option>
      {zones.status === "ok" &&
        zones.data.data.map((z) => (
          <option key={z.id} value={z.id}>
            {z.code}
          </option>
        ))}
    </select>
  );
}

function JdvSelect({ zoneId, value, onChange }: { zoneId: string; value: string; onChange: (id: string) => void }) {
  const jdvs = useApi<Paged<Jdv>>(`/jdvs?zone_id=${zoneId}&per_page=100`);

  return (
    <select id="jdv" value={value} onChange={(e) => onChange(e.target.value)} className={inputClass} required>
      <option value="">Choisir un splitter</option>
      {jdvs.status === "ok" &&
        jdvs.data.data.map((j) => (
          <option key={j.id} value={j.id}>
            {j.code}
          </option>
        ))}
    </select>
  );
}

/** Une décimale saisie avec une virgule ou un point ; vide = pas de valeur. */
const toNumberOrNull = (value: string) => (value.trim() === "" ? null : value.trim().replace(",", "."));

/** Création (pco absent) ou modification d'un PCO par l'administrateur. Le statut ne se change pas ici. */
export function PcoForm({ pco }: { pco?: Pco }) {
  const router = useRouter();
  const editing = Boolean(pco);

  const [plaqueId, setPlaqueId] = useState(pco?.zone?.plaque?.id ?? "");
  const [zoneId, setZoneId] = useState(pco?.zone_id ?? "");
  const [jdvId, setJdvId] = useState(pco?.jdv_id ?? "");
  const [code, setCode] = useState(pco?.code ?? "");
  const [latitude, setLatitude] = useState(pco?.latitude ?? "");
  const [longitude, setLongitude] = useState(pco?.longitude ?? "");
  const [slot, setSlot] = useState(pco?.olt_slot ?? "");
  const [port, setPort] = useState(pco?.olt_port ?? "");
  const [strand, setStrand] = useState(pco?.fiber_strand ?? "");
  const [comment, setComment] = useState(pco?.comment ?? "");

  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<{ type: "error" | "success"; text: string } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const plaques = useApi<Paged<Plaque>>("/plaques?per_page=100");
  const error = (field: string) => errors[field]?.[0];
  // L'erreur d'un champ disparaît dès qu'on le corrige (les autres restent jusqu'au prochain envoi).
  const clearError = (...fields: string[]) => setErrors((current) => Object.fromEntries(Object.entries(current).filter(([key]) => !fields.includes(key))));

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setErrors({});
    setMessage(null);
    setSubmitting(true);

    const body = {
      jdv_id: jdvId,
      code,
      latitude: toNumberOrNull(latitude),
      longitude: toNumberOrNull(longitude),
      olt_slot: slot || null,
      olt_port: port || null,
      fiber_strand: strand || null,
      comment: comment || null,
    };

    try {
      await apiFetch(editing ? `/pcos/${pco!.id}` : "/pcos", { method: editing ? "PATCH" : "POST", body: JSON.stringify(body) }, getToken());
      if (editing) {
        setMessage({ type: "success", text: "Modifications enregistrées." });
      } else {
        router.push("/pco");
      }
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.errors);
        setMessage({ type: "error", text: Object.keys(e.errors).length ? "Corrigez les champs indiqués." : e.message });
      } else {
        setMessage({ type: "error", text: "Une erreur est survenue." });
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="space-y-4 pb-20" noValidate>
      {message && (
        <p role={message.type === "error" ? "alert" : "status"} className={`rounded-lg p-3 text-sm ${message.type === "error" ? "bg-red-50 text-red-800" : "bg-green-50 text-green-800"}`}>
          {message.text}
        </p>
      )}

      {editing ? (
        <p className="rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-700">
          Plaque <span className="font-medium text-ink">{pco?.zone?.plaque?.name ?? "—"}</span> · Sous-plaque <span className="font-medium text-ink">{pco?.zone?.code ?? "—"}</span>. Un PCO ne change pas de sous-plaque : seul son splitter peut changer.
        </p>
      ) : (
        <>
          <Field id="plaque" label="Plaque">
            <select
              id="plaque"
              value={plaqueId}
              onChange={(e) => {
                setPlaqueId(e.target.value);
                setZoneId("");
                setJdvId("");
              }}
              className={inputClass}
              required
            >
              <option value="">Choisir une plaque</option>
              {plaques.status === "ok" &&
                plaques.data.data.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
            </select>
          </Field>
          {plaqueId && (
            <Field id="zone" label="Sous-plaque">
              <ZoneSelect
                key={plaqueId}
                plaqueId={plaqueId}
                value={zoneId}
                onChange={(id) => {
                  setZoneId(id);
                  setJdvId("");
                }}
              />
            </Field>
          )}
        </>
      )}

      {zoneId && (
        <Field id="jdv" label="Splitter" error={error("jdv_id")}>
          <JdvSelect key={zoneId} zoneId={zoneId} value={jdvId} onChange={setJdvId} />
        </Field>
      )}

      <Field id="code" label="Code du PCO" hint="Par exemple FAY03_PCO001." error={error("code")}>
        <input id="code" value={code} onChange={(e) => (setCode(e.target.value), clearError("code"))} className={inputClass} required />
      </Field>

      <fieldset className="space-y-3">
        <legend className="text-sm font-medium">Coordonnées prévues (facultatif)</legend>
        <p className="text-xs text-slate-700">Dans la Côte d&apos;Ivoire. Les coordonnées mesurées au relevé final seront enregistrées à part.</p>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field id="latitude" label="Latitude" error={error("latitude")}>
            <input id="latitude" inputMode="decimal" placeholder="5.3600" value={latitude} onChange={(e) => (setLatitude(e.target.value), clearError("latitude", "longitude"))} className={inputClass} />
          </Field>
          <Field id="longitude" label="Longitude" error={error("longitude")}>
            <input id="longitude" inputMode="decimal" placeholder="-4.0083" value={longitude} onChange={(e) => (setLongitude(e.target.value), clearError("latitude", "longitude"))} className={inputClass} />
          </Field>
        </div>
      </fieldset>

      <div className="grid gap-3 sm:grid-cols-3">
        <Field id="slot" label="Slot (facultatif)" error={error("olt_slot")}>
          <input id="slot" value={slot} onChange={(e) => setSlot(e.target.value)} className={inputClass} />
        </Field>
        <Field id="port" label="Port (facultatif)" error={error("olt_port")}>
          <input id="port" value={port} onChange={(e) => setPort(e.target.value)} className={inputClass} />
        </Field>
        <Field id="strand" label="Brin (facultatif)" error={error("fiber_strand")}>
          <input id="strand" value={strand} onChange={(e) => setStrand(e.target.value)} className={inputClass} />
        </Field>
      </div>

      <Field id="comment" label="Commentaire (facultatif)" error={error("comment")}>
        <textarea id="comment" value={comment} onChange={(e) => setComment(e.target.value)} rows={3} className={`${inputClass} h-auto py-2`} />
      </Field>

      {editing && pco && (
        <p className="rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-700">
          Statut : <span className="font-medium text-ink">{pcoStatusLabel(pco)}</span>
          {pco.is_blocked && <span className="ml-2"><Badge tone="danger">Bloqué</Badge></span>}
          <span className="mt-1 block text-xs">Le statut ne se modifie pas ici : chaque changement de statut est historisé.</span>
        </p>
      )}

      <div className="fixed inset-x-0 bottom-14 z-10 flex gap-3 border-t border-slate-200 bg-white/95 p-3 shadow-[0_-6px_16px_rgba(15,23,42,0.06)] backdrop-blur md:static md:border-0 md:bg-transparent md:p-0 md:shadow-none md:backdrop-blur-none">
        <button type="submit" disabled={submitting || !jdvId} className="h-11 flex-1 rounded-lg bg-primary hover:bg-primary-hover font-medium text-white disabled:opacity-60 md:flex-none md:px-6">
          {submitting ? "Enregistrement…" : editing ? "Enregistrer" : "Créer le PCO"}
        </button>
        <button type="button" onClick={() => router.push("/pco")} className="h-11 rounded-lg border border-slate-300 px-4 font-medium">
          Retour
        </button>
      </div>
    </form>
  );
}
