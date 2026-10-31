"use client";

import { useMemo, useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { useAuth } from "@/lib/auth/auth-context";
import { getToken } from "@/lib/auth/token";
import { submitOperation } from "@/lib/offline/submit";
import { useLive } from "@/lib/offline/use-live";
import { readStudyDefaults, searchPcos, type StudyDefaults } from "@/lib/offline/visits";
import { useDebounced } from "@/lib/use-debounced";
import type { CustomerOrder } from "@/lib/types";
import { useApi } from "@/lib/use-api";

type Result = "feasible" | "not_feasible" | "customer_absent";
const RESULTS: { value: Result; label: string }[] = [
  { value: "feasible", label: "Raccordable" },
  { value: "not_feasible", label: "Non raccordable" },
  { value: "customer_absent", label: "Client absent" },
];
const POLES = [
  { key: "concrete_poles_existing", label: "Béton existants", type: "concrete" },
  { key: "concrete_poles_to_install", label: "Béton à installer", type: "concrete" },
  { key: "metal_poles_existing", label: "Métalliques existants", type: "metal" },
  { key: "metal_poles_to_install", label: "Métalliques à installer", type: "metal" },
] as const;

type Choice = { id: string; code: string; zone: string | null; splitter: string | null };
const NO_CHOICES: Choice[] = [];

const digits = (v: string) => v.replace(/\D/g, "");

/** Formulaire d'étude : résultat, PCO de rattachement, poteaux par type, distance proposée (poteaux × écart), observations. */
export function StudyFormPanel({ order, showInternal, onDone }: { order: Pick<CustomerOrder, "id" | "name">; showInternal: boolean; onDone: () => void }) {
  const { user } = useAuth();
  // Le technicien lit ses listes sur le téléphone (elles marchent sans réseau) ; les autres rôles, sur le serveur.
  const onPhone = user?.role === "technician";
  const remoteDefaults = useApi<{ data: StudyDefaults }>(onPhone ? null : "/customer-orders/study-defaults");
  const localDefaults = useLive<StudyDefaults | null>(readStudyDefaults, null);
  const defaults = onPhone ? localDefaults : remoteDefaults.status === "ok" ? remoteDefaults.data.data : null;
  const [result, setResult] = useState<Result | "">("");
  const [pcoSearch, setPcoSearch] = useState("");
  const [pco, setPco] = useState<{ id: string; code: string } | null>(null);
  const [poles, setPoles] = useState<Record<string, string>>({});
  const [distance, setDistance] = useState<string | null>(null);
  const [problem, setProblem] = useState("");
  const [comments, setComments] = useState("");
  const [internal, setInternal] = useState("");
  const [position, setPosition] = useState<{ latitude: number; longitude: number } | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const q = useDebounced(pcoSearch.trim());

  const spacing = defaults?.pole_spacing_m ?? 50;
  const counted = defaults?.counted_pole_types ?? ["concrete", "metal"];
  const suggested = POLES.filter((p) => counted.includes(p.type)).reduce((sum, p) => sum + Number(digits(poles[p.key] ?? "") || 0), 0) * spacing;
  const shownDistance = distance ?? String(suggested);

  const remoteChoices = useApi<{ data: Choice[] }>(onPhone ? null : `/customer-orders/study-pcos?${new URLSearchParams(q ? { q } : {})}`);
  const searchLocal = useMemo(() => () => searchPcos(q), [q]);
  const localChoices = useLive<Choice[]>(searchLocal, NO_CHOICES);
  const choices = onPhone ? localChoices : remoteChoices.status === "ok" ? remoteChoices.data.data : NO_CHOICES;

  function locate() {
    navigator.geolocation?.getCurrentPosition(
      (p) => setPosition({ latitude: Number(p.coords.latitude.toFixed(7)), longitude: Number(p.coords.longitude.toFixed(7)) }),
      () => setErrors((e) => ({ ...e, latitude: "Position indisponible : autorisez la localisation ou laissez vide." })),
    );
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setErrors({});
    if (!result) {
      setErrors({ result: "Indiquez le résultat de l'étude." });
      return;
    }
    const feasible = result === "feasible";
    const body: Record<string, unknown> = {
      result,
      pco_id: feasible ? (pco?.id ?? null) : null,
      problem: result === "not_feasible" ? problem.trim() || null : null,
      field_comments: comments.trim() || null,
      internal_comments: internal.trim() || null,
      latitude: position?.latitude ?? null,
      longitude: position?.longitude ?? null,
    };
    if (feasible) {
      for (const p of POLES) body[p.key] = Number(digits(poles[p.key] ?? "") || 0);
      body.distance_m = Number(digits(shownDistance) || 0);
    }
    setBusy(true);
    try {
      if (user?.role === "technician") {
        // Le technicien saisit sur le terrain : la saisie est gardée sur le téléphone puis envoyée, même si le réseau lâche.
        const outcome = await submitOperation({ type: "study.result", customer_order_id: order.id, payload: body, client_name: order.name });
        if (outcome.state === "applied") onDone();
        else if (outcome.state !== "queued") setErrors({ form: outcome.message });
        return; // « en attente » : la fiche l'indique d'elle-même
      }
      await apiFetch(`/customer-orders/${order.id}/study-result`, { method: "POST", body: JSON.stringify(body) }, getToken());
      onDone();
    } catch (err) {
      if (err instanceof ApiError) {
        const flat = Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0]]));
        setErrors(Object.keys(flat).length ? flat : { form: err.message });
      } else {
        setErrors({ form: "Une erreur est survenue." });
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <section aria-labelledby="study-form" className="space-y-4 rounded-xl border border-slate-300 bg-white p-4">
      <h2 id="study-form" className="text-lg font-semibold">
        Résultat de l&apos;étude
      </h2>
      {(errors.form || errors.status) && (
        <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
          {errors.form ?? errors.status}
        </p>
      )}

      <form onSubmit={submit} className="space-y-4" noValidate>
        <fieldset className="space-y-1.5">
          <legend className="text-sm font-medium text-ink">Le client est</legend>
          <div className="grid gap-2 sm:grid-cols-3">
            {RESULTS.map((r) => (
              <label key={r.value} className={`flex h-11 cursor-pointer items-center justify-center rounded-xl border text-sm font-medium ${result === r.value ? "border-primary bg-primary-soft text-primary-ink" : "border-slate-300 bg-white"}`}>
                <input type="radio" name="result" value={r.value} checked={result === r.value} onChange={() => setResult(r.value)} className="sr-only" />
                {r.label}
              </label>
            ))}
          </div>
          {errors.result && (
            <p role="alert" className="text-sm text-red-700">
              {errors.result}
            </p>
          )}
        </fieldset>

        {result === "feasible" && (
          <>
            <Field id="pco-search" label="PCO de rattachement" hint={pco ? `Choisi : ${pco.code}` : "Tapez le code (ex. FAY03 ou PCO001) puis touchez un PCO."} error={errors.pco_id}>
              <input id="pco-search" type="search" value={pcoSearch} onChange={(e) => setPcoSearch(e.target.value)} className={inputClass} aria-invalid={!!errors.pco_id} />
            </Field>
            {choices.length === 0 && onPhone && (
              <p className="text-sm text-slate-700">Aucun PCO sur ce téléphone pour cette recherche. La liste se télécharge avec vos visites, quand le réseau est là.</p>
            )}
            {choices.length > 0 && (
              <ul className="max-h-48 space-y-1 overflow-y-auto">
                {choices.map((c) => (
                  <li key={c.id}>
                    <button type="button" onClick={() => setPco({ id: c.id, code: c.code })} className={`flex min-h-11 w-full items-center justify-between rounded-lg border px-3 text-left text-sm ${pco?.id === c.id ? "border-primary bg-primary-soft" : "border-slate-200"}`}>
                      <span className="font-medium">{c.code}</span>
                      <span className="text-slate-700">{c.splitter ? `Splitter ${c.splitter}` : ""}</span>
                    </button>
                  </li>
                ))}
              </ul>
            )}

            <div className="grid grid-cols-2 gap-3">
              {POLES.map((p) => (
                <Field key={p.key} id={p.key} label={`Poteaux ${p.label.toLowerCase()}`} error={errors[p.key]}>
                  <input id={p.key} inputMode="numeric" value={poles[p.key] ?? ""} onChange={(e) => setPoles({ ...poles, [p.key]: digits(e.target.value) })} placeholder="0" className={inputClass} />
                </Field>
              ))}
            </div>

            <Field
              id="distance"
              label="Distance PCO – client (m)"
              hint={`Proposée : poteaux ${counted.includes("concrete") && counted.includes("metal") ? "béton et métalliques" : counted.includes("concrete") ? "béton" : "métalliques"} × ${spacing} m. Modifiable.`}
              error={errors.distance_m}
            >
              <input id="distance" inputMode="numeric" value={shownDistance} onChange={(e) => setDistance(digits(e.target.value))} className={inputClass} />
            </Field>
          </>
        )}

        {result === "not_feasible" && (
          <Field id="problem" label="Problème rencontré" error={errors.problem}>
            <textarea id="problem" rows={3} value={problem} onChange={(e) => setProblem(e.target.value)} className={inputClass + " h-auto py-2"} aria-invalid={!!errors.problem} />
          </Field>
        )}

        <Field id="comments" label="Commentaires de terrain (facultatif)" error={errors.field_comments}>
          <textarea id="comments" rows={2} value={comments} onChange={(e) => setComments(e.target.value)} className={inputClass + " h-auto py-2"} />
        </Field>
        {showInternal && (
          <Field id="internal" label="Commentaires internes (facultatif)" hint="Visibles par l'encadrement seulement." error={errors.internal_comments}>
            <textarea id="internal" rows={2} value={internal} onChange={(e) => setInternal(e.target.value)} className={inputClass + " h-auto py-2"} />
          </Field>
        )}

        <div className="space-y-1.5">
          <button type="button" onClick={locate} className="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium">
            {position ? `Position : ${position.latitude}, ${position.longitude}` : "Enregistrer ma position"}
          </button>
          {errors.latitude && (
            <p role="alert" className="text-sm text-red-700">
              {errors.latitude}
            </p>
          )}
        </div>

        <button type="submit" disabled={busy} className="h-11 rounded-lg bg-primary px-6 font-medium text-white hover:bg-primary-hover disabled:opacity-60">
          {busy ? "Enregistrement…" : "Enregistrer l'étude"}
        </button>
      </form>
    </section>
  );
}
