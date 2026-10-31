"use client";

import Link from "next/link";
import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import type { Paged, Sro, Zone } from "@/lib/types";
import { useApi } from "@/lib/use-api";

/** Nombres proposés dans la liste ; la saisie reste libre. */
const SUGGESTED_COUNTS = [32, 64];

interface Generated {
  sro_created: boolean;
  splitters: number;
  pcos: number;
}

/** Génère la SRO, les splitters (8 PCO chacun) et les PCO d'une sous-plaque ; le BE ajuste ensuite. */
export function GenerateNetworkPanel({ zone }: { zone: Zone }) {
  const sro = useApi<Paged<Sro>>(`/sros?zone_id=${zone.id}`);
  const hasSro = sro.status === "ok" && sro.data.data.length > 0;

  const [count, setCount] = useState("64");
  const [sroCode, setSroCode] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [done, setDone] = useState<Generated | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setErrors({});
    setMessage(null);
    setSubmitting(true);

    try {
      const body = { pco_count: Number(count), ...(hasSro ? {} : { sro_code: sroCode }) };
      const res = await apiFetch<{ data: Generated }>(`/zones/${zone.id}/generate`, { method: "POST", body: JSON.stringify(body) }, getToken());
      setDone(res.data);
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.errors);
        setMessage(e.errors.zone?.[0] ?? (Object.keys(e.errors).length ? "Corrigez les champs indiqués." : e.message));
      } else {
        setMessage("Une erreur est survenue.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  if (done) {
    return (
      <section role="status" className="space-y-2 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
        <p className="font-medium">
          Réseau généré : {done.sro_created ? "1 SRO, " : ""}
          {done.splitters} splitters et {done.pcos} PCO.
        </p>
        <Link href="/plaques" className="inline-flex h-11 items-center rounded-lg border border-green-300 bg-white px-4 font-medium">
          Voir les plaques
        </Link>
      </section>
    );
  }

  return (
    <section className="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
      <div>
        <h2 className="text-base font-semibold">Générer le réseau</h2>
        <p className="text-sm text-slate-700">
          Crée la SRO, les splitters (SPL001…, 8 PCO chacun) et les PCO ({zone.code}_PCO001…). Possible une seule fois, tant que la sous-plaque n&apos;a ni splitter ni PCO.
          Le technicien du BE ajuste ensuite selon son plan.
        </p>
      </div>

      <form onSubmit={onSubmit} className="space-y-3" noValidate>
        {message && (
          <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
            {message}
          </p>
        )}

        <Field id="pco-count" label="Nombre de PCO" hint="Libre : 32 et 64 sont proposés." error={errors.pco_count?.[0]}>
          <input id="pco-count" list="pco-counts" inputMode="numeric" value={count} onChange={(e) => setCount(e.target.value.replace(/\D/g, ""))} className={inputClass} required />
          <datalist id="pco-counts">
            {SUGGESTED_COUNTS.map((n) => (
              <option key={n} value={n} />
            ))}
          </datalist>
        </Field>

        {sro.status === "ok" && !hasSro && (
          <Field id="sro-code" label="Numéro de la SRO" hint="Les lettres de la sous-plaque et le numéro donné par l'opérateur, par exemple ABB456." error={errors.sro_code?.[0]}>
            <input id="sro-code" value={sroCode} onChange={(e) => setSroCode(e.target.value)} className={inputClass} required />
          </Field>
        )}
        {hasSro && <p className="text-sm text-slate-700">La SRO de cette sous-plaque existe déjà ({sro.data.data[0].code}) : elle est conservée.</p>}

        <button type="submit" disabled={submitting || sro.status !== "ok"} className="h-11 rounded-lg bg-primary hover:bg-primary-hover px-6 font-medium text-white disabled:opacity-60">
          {submitting ? "Génération…" : "Générer le réseau"}
        </button>
      </form>
    </section>
  );
}
