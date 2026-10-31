"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import { VENDOR_LABELS, ZONE_STATUS_LABELS } from "@/lib/labels";
import type { Paged, Plaque, Zone, ZoneStatus } from "@/lib/types";
import { useApi } from "@/lib/use-api";

// La clôture d'une sous-plaque est validée par le DT : elle n'est pas proposée ici.
const EDITABLE_STATUSES: ZoneStatus[] = ["planned", "deploying", "in_operation"];

/** Création (zone absente) ou modification d'une sous-plaque par l'administrateur. */
export function ZoneForm({ zone }: { zone?: Zone }) {
  const router = useRouter();
  const editing = Boolean(zone);

  const [plaqueId, setPlaqueId] = useState(zone?.plaque_id ?? "");
  const [code, setCode] = useState(zone?.code ?? "");
  const [name, setName] = useState(zone?.name ?? "");
  const [vendor, setVendor] = useState<string>(zone?.vendor ?? "");
  const [status, setStatus] = useState<ZoneStatus>(zone?.status ?? "planned");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<{ type: "error" | "success"; text: string } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const plaques = useApi<Paged<Plaque>>("/plaques?per_page=100");
  const plaqueOptions = plaques.status === "ok" ? plaques.data.data : [];

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setErrors({});
    setMessage(null);
    setSubmitting(true);

    // La plaque d'une sous-plaque existante ne change pas : ses splitters et PCO y sont rattachés.
    const body = { ...(editing ? {} : { plaque_id: plaqueId }), code, name: name || null, vendor: vendor || null, status };

    try {
      await apiFetch(editing ? `/zones/${zone!.id}` : "/zones", { method: editing ? "PATCH" : "POST", body: JSON.stringify(body) }, getToken());
      if (editing) {
        setMessage({ type: "success", text: "Modifications enregistrées." });
      } else {
        router.push("/sous-plaques");
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

      <Field id="plaque" label="Plaque" hint={editing ? "La plaque d'une sous-plaque existante ne se change pas." : undefined} error={errors.plaque_id?.[0]}>
        <select id="plaque" value={plaqueId} onChange={(e) => setPlaqueId(e.target.value)} disabled={editing} className={inputClass} required>
          <option value="">Choisir une plaque</option>
          {(editing && zone?.plaque && !plaqueOptions.some((p) => p.id === zone.plaque_id) ? [zone.plaque as Plaque] : []).concat(plaqueOptions).map((p) => (
            <option key={p.id} value={p.id}>
              {p.name}
            </option>
          ))}
        </select>
      </Field>

      <Field id="code" label="Code de la sous-plaque" hint="Par exemple FAY03 ou ACA003." error={errors.code?.[0]}>
        <input id="code" value={code} onChange={(e) => setCode(e.target.value)} className={inputClass} required />
      </Field>

      <Field id="name" label="Nom (facultatif)" error={errors.name?.[0]}>
        <input id="name" value={name} onChange={(e) => setName(e.target.value)} className={inputClass} />
      </Field>

      <Field id="vendor" label="Fabricant (facultatif)" hint="Huawei appelle la sous-plaque « hub », Nokia « SRO »." error={errors.vendor?.[0]}>
        <select id="vendor" value={vendor} onChange={(e) => setVendor(e.target.value)} className={inputClass}>
          <option value="">Non renseigné</option>
          {(Object.keys(VENDOR_LABELS) as (keyof typeof VENDOR_LABELS)[]).map((v) => (
            <option key={v} value={v}>
              {VENDOR_LABELS[v]}
            </option>
          ))}
        </select>
      </Field>

      <Field
        id="status"
        label="Statut"
        hint={zone?.status === "closed" ? "Cette sous-plaque est clôturée (validation du DT)." : "La clôture est validée par le DT."}
        error={errors.status?.[0]}
      >
        <select id="status" value={status} onChange={(e) => setStatus(e.target.value as ZoneStatus)} disabled={zone?.status === "closed"} className={inputClass}>
          {(zone?.status === "closed" ? [...EDITABLE_STATUSES, "closed" as ZoneStatus] : EDITABLE_STATUSES).map((s) => (
            <option key={s} value={s}>
              {ZONE_STATUS_LABELS[s]}
            </option>
          ))}
        </select>
      </Field>

      <div className="fixed inset-x-0 bottom-14 z-10 flex gap-3 border-t border-slate-200 bg-white/95 p-3 shadow-[0_-6px_16px_rgba(15,23,42,0.06)] backdrop-blur md:static md:border-0 md:bg-transparent md:p-0 md:shadow-none md:backdrop-blur-none">
        <button type="submit" disabled={submitting} className="h-11 flex-1 rounded-lg bg-primary hover:bg-primary-hover font-medium text-white disabled:opacity-60 md:flex-none md:px-6">
          {submitting ? "Enregistrement…" : editing ? "Enregistrer" : "Créer la sous-plaque"}
        </button>
        <button type="button" onClick={() => router.push("/sous-plaques")} className="h-11 rounded-lg border border-slate-300 px-4 font-medium">
          Retour
        </button>
      </div>
    </form>
  );
}
