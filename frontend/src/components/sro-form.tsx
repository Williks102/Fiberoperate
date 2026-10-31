"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import type { Paged, Sro, Zone } from "@/lib/types";
import { useApi } from "@/lib/use-api";

const toNumberOrNull = (value: string) => (value.trim() === "" ? null : value.trim().replace(",", "."));

/** Création (sro absent) ou modification d'une SRO par l'administrateur : une SRO par sous-plaque, départ de l'OLT saisi ici. */
export function SroForm({ sro }: { sro?: Sro }) {
  const router = useRouter();
  const editing = Boolean(sro);

  const [zoneId, setZoneId] = useState(sro?.zone_id ?? "");
  const [code, setCode] = useState(sro?.code ?? "");
  const [latitude, setLatitude] = useState(sro?.latitude ?? "");
  const [longitude, setLongitude] = useState(sro?.longitude ?? "");
  const [slot, setSlot] = useState(sro?.olt_slot ?? "");
  const [port, setPort] = useState(sro?.olt_port ?? "");
  const [strand, setStrand] = useState(sro?.fiber_strand ?? "");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<{ type: "error" | "success"; text: string } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const zones = useApi<Paged<Zone>>("/zones?per_page=100");
  const zoneOptions = zones.status === "ok" ? zones.data.data : [];
  const zoneLabel = (z: { code: string; plaque?: { name: string } | null }) => (z.plaque ? `${z.plaque.name} · ${z.code}` : z.code);
  const clear = (...keys: string[]) => setErrors((prev) => Object.fromEntries(Object.entries(prev).filter(([k]) => !keys.includes(k))));

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setErrors({});
    setMessage(null);
    setSubmitting(true);

    const details = {
      code,
      latitude: toNumberOrNull(latitude),
      longitude: toNumberOrNull(longitude),
      olt_slot: slot || null,
      olt_port: port || null,
      fiber_strand: strand || null,
    };
    // La sous-plaque d'une SRO existante ne change pas.
    const body = editing ? details : { zone_id: zoneId, ...details };

    try {
      await apiFetch(editing ? `/sros/${sro!.id}` : "/sros", { method: editing ? "PATCH" : "POST", body: JSON.stringify(body) }, getToken());
      if (editing) {
        setMessage({ type: "success", text: "Modifications enregistrées." });
      } else {
        router.push("/sro");
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
        <p
          role={message.type === "error" ? "alert" : "status"}
          className={`rounded-lg p-3 text-sm ${message.type === "error" ? "bg-red-50 text-red-800" : "bg-green-50 text-green-800"}`}
        >
          {message.text}
        </p>
      )}

      <Field id="zone" label="Sous-plaque" hint={editing ? "La sous-plaque d'une SRO existante ne se change pas." : "Une seule SRO par sous-plaque."} error={errors.zone_id?.[0]}>
        <select id="zone" value={zoneId} onChange={(e) => setZoneId(e.target.value)} disabled={editing} className={inputClass} required>
          <option value="">Choisir une sous-plaque</option>
          {(editing && sro?.zone && !zoneOptions.some((z) => z.id === sro.zone_id) ? [{ id: sro.zone.id, code: sro.zone.code, plaque: sro.zone.plaque }] : [])
            .concat(zoneOptions.map((z) => ({ id: z.id, code: z.code, plaque: z.plaque ?? null })))
            .map((z) => (
              <option key={z.id} value={z.id}>
                {zoneLabel(z)}
              </option>
            ))}
        </select>
      </Field>

      <Field id="code" label="Nom de la SRO" hint="Les lettres de la sous-plaque et le numéro donné par l'opérateur, par exemple ABB456." error={errors.code?.[0]}>
        <input id="code" value={code} onChange={(e) => setCode(e.target.value)} className={inputClass} required />
      </Field>

      <fieldset className="space-y-3">
        <legend className="text-sm font-medium">Position GPS (facultatif)</legend>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field id="latitude" label="Latitude" error={errors.latitude?.[0]}>
            <input id="latitude" inputMode="decimal" placeholder="5.3600" value={latitude} onChange={(e) => (setLatitude(e.target.value), clear("latitude", "longitude"))} className={inputClass} />
          </Field>
          <Field id="longitude" label="Longitude" error={errors.longitude?.[0]}>
            <input id="longitude" inputMode="decimal" placeholder="-4.0083" value={longitude} onChange={(e) => (setLongitude(e.target.value), clear("latitude", "longitude"))} className={inputClass} />
          </Field>
        </div>
      </fieldset>

      <fieldset className="space-y-3">
        <legend className="text-sm font-medium">Départ de l&apos;OLT (facultatif)</legend>
        <div className="grid gap-3 sm:grid-cols-3">
          <Field id="slot" label="Slot" error={errors.olt_slot?.[0]}>
            <input id="slot" value={slot} onChange={(e) => setSlot(e.target.value)} className={inputClass} />
          </Field>
          <Field id="port" label="Port" error={errors.olt_port?.[0]}>
            <input id="port" value={port} onChange={(e) => setPort(e.target.value)} className={inputClass} />
          </Field>
          <Field id="strand" label="Brin de fibre" error={errors.fiber_strand?.[0]}>
            <input id="strand" value={strand} onChange={(e) => setStrand(e.target.value)} className={inputClass} />
          </Field>
        </div>
      </fieldset>

      <div className="fixed inset-x-0 bottom-14 z-10 flex gap-3 border-t border-slate-200 bg-white p-3 md:static md:border-0 md:p-0">
        <button type="submit" disabled={submitting} className="h-11 flex-1 rounded-lg bg-primary hover:bg-primary-hover font-medium text-white disabled:opacity-60 md:flex-none md:px-6">
          {submitting ? "Enregistrement…" : editing ? "Enregistrer" : "Créer la SRO"}
        </button>
        <button type="button" onClick={() => router.push("/sro")} className="h-11 rounded-lg border border-slate-300 px-4 font-medium">
          Retour
        </button>
      </div>
    </form>
  );
}
