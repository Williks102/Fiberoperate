"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import type { Jdv, Paged, Zone } from "@/lib/types";
import { useApi } from "@/lib/use-api";

/** Création (jdv absent) ou modification d'un splitter par l'administrateur. */
export function JdvForm({ jdv }: { jdv?: Jdv }) {
  const router = useRouter();
  const editing = Boolean(jdv);

  const [zoneId, setZoneId] = useState(jdv?.zone_id ?? "");
  const [code, setCode] = useState(jdv?.code ?? "");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<{ type: "error" | "success"; text: string } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const zones = useApi<Paged<Zone>>("/zones?per_page=100");
  const zoneOptions = zones.status === "ok" ? zones.data.data : [];
  const zoneLabel = (z: { code: string; plaque?: { name: string } | null }) => (z.plaque ? `${z.plaque.name} · ${z.code}` : z.code);

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setErrors({});
    setMessage(null);
    setSubmitting(true);

    // La sous-plaque d'un splitter existant ne change pas : ses PCO y sont rattachés.
    const body = editing ? { code } : { zone_id: zoneId, code };

    try {
      await apiFetch(editing ? `/jdvs/${jdv!.id}` : "/jdvs", { method: editing ? "PATCH" : "POST", body: JSON.stringify(body) }, getToken());
      if (editing) {
        setMessage({ type: "success", text: "Modifications enregistrées." });
      } else {
        router.push("/splitters");
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

      <Field id="zone" label="Sous-plaque" hint={editing ? "La sous-plaque d'un splitter existant ne se change pas." : undefined} error={errors.zone_id?.[0]}>
        <select id="zone" value={zoneId} onChange={(e) => setZoneId(e.target.value)} disabled={editing} className={inputClass} required>
          <option value="">Choisir une sous-plaque</option>
          {(editing && jdv?.zone && !zoneOptions.some((z) => z.id === jdv.zone_id) ? [{ id: jdv.zone.id, code: jdv.zone.code, plaque: jdv.zone.plaque }] : [])
            .concat(zoneOptions.map((z) => ({ id: z.id, code: z.code, plaque: z.plaque ?? null })))
            .map((z) => (
              <option key={z.id} value={z.id}>
                {zoneLabel(z)}
              </option>
            ))}
        </select>
      </Field>

      <Field id="code" label="Code du splitter" hint="Par exemple SPL001." error={errors.code?.[0]}>
        <input id="code" value={code} onChange={(e) => setCode(e.target.value)} className={inputClass} required />
      </Field>

      <div className="fixed inset-x-0 bottom-14 z-10 flex gap-3 border-t border-slate-200 bg-white p-3 md:static md:border-0 md:p-0">
        <button type="submit" disabled={submitting} className="h-11 flex-1 rounded-lg bg-primary hover:bg-primary-hover font-medium text-white disabled:opacity-60 md:flex-none md:px-6">
          {submitting ? "Enregistrement…" : editing ? "Enregistrer" : "Créer le splitter"}
        </button>
        <button type="button" onClick={() => router.push("/splitters")} className="h-11 rounded-lg border border-slate-300 px-4 font-medium">
          Retour
        </button>
      </div>
    </form>
  );
}
