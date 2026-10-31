"use client";

import { useEffect, useRef, useState } from "react";
import { discardPhoto, MAX_PHOTOS, savePhoto } from "@/lib/offline/photos";

interface Shot {
  uuid: string;
  url: string;
}

/**
 * Prise de photos de la visite (au moins une, obligatoire). Chaque photo est réduite et compressée sur le téléphone puis gardée
 * dans sa mémoire locale : elle survit à une coupure et part quand le réseau le permet. `onChange` donne les identifiants gardés.
 */
export function PhotoCapture({ orderId, onChange, error }: { orderId: string; onChange: (uuids: string[]) => void; error?: string }) {
  const [shots, setShots] = useState<Shot[]>([]);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);
  const input = useRef<HTMLInputElement>(null);
  const urls = useRef<string[]>([]);

  // Les aperçus sont des adresses temporaires : libérées à la fermeture de l'écran.
  useEffect(() => {
    const kept = urls.current;

    return () => kept.forEach((u) => URL.revokeObjectURL(u));
  }, []);

  async function add(files: FileList | null) {
    if (!files || files.length === 0) return;
    setBusy(true);
    setProblem(null);
    try {
      const added: Shot[] = [];
      for (const file of Array.from(files).slice(0, MAX_PHOTOS - shots.length)) {
        const row = await savePhoto(orderId, file);
        const url = URL.createObjectURL(row.blob);
        urls.current.push(url);
        added.push({ uuid: row.client_uuid, url });
      }
      const next = [...shots, ...added];
      setShots(next);
      onChange(next.map((s) => s.uuid));
    } catch (e) {
      setProblem(e instanceof Error ? e.message : "Impossible de traiter la photo.");
    } finally {
      setBusy(false);
      if (input.current) input.current.value = "";
    }
  }

  async function remove(uuid: string) {
    await discardPhoto(uuid);
    const next = shots.filter((s) => s.uuid !== uuid);
    setShots(next);
    onChange(next.map((s) => s.uuid));
  }

  const full = shots.length >= MAX_PHOTOS;

  return (
    <div className="space-y-2">
      <p className="text-sm font-medium text-ink">Photos, obligatoire (au moins une)</p>
      <div className="flex flex-wrap gap-2">
        {shots.map((s, i) => (
          <div key={s.uuid} className="relative h-24 w-24 overflow-hidden rounded-xl border border-slate-300">
            {/* eslint-disable-next-line @next/next/no-img-element -- aperçu local (adresse temporaire) : next/image ne s'applique pas */}
            <img src={s.url} alt={`Photo ${i + 1}`} className="h-full w-full object-cover" />
            <button type="button" onClick={() => void remove(s.uuid)} aria-label={`Retirer la photo ${i + 1}`} className="absolute right-1 top-1 flex h-11 w-11 items-center justify-center rounded-full bg-white/90 text-lg font-bold text-red-800 shadow">
              ×
            </button>
          </div>
        ))}
      </div>
      <input ref={input} type="file" accept="image/*" capture="environment" multiple className="sr-only" id={`photos-${orderId}`} onChange={(e) => void add(e.target.files)} disabled={busy || full} />
      <label htmlFor={`photos-${orderId}`} className={`inline-flex h-11 cursor-pointer items-center rounded-lg border border-slate-300 px-4 text-sm font-medium ${busy || full ? "opacity-60" : ""}`}>
        {busy ? "Traitement de la photo…" : full ? `${MAX_PHOTOS} photos au plus` : shots.length === 0 ? "Prendre une photo" : "Ajouter une photo"}
      </label>
      {(error || problem) && (
        <p role="alert" className="text-sm text-red-700">
          {error ?? problem}
        </p>
      )}
    </div>
  );
}
