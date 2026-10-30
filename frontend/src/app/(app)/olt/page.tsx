"use client";

import Link from "next/link";
import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { Pager } from "@/components/pager";
import { useAuth } from "@/lib/auth/auth-context";
import type { Olt, Paged } from "@/lib/types";
import { useApi } from "@/lib/use-api";
import { useDebounced } from "@/lib/use-debounced";

export default function OltPage() {
  const { user } = useAuth();
  const canManage = user?.role === "admin";
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const q = useDebounced(search.trim());

  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (q) params.set("q", q);
  const { reload, ...state } = useApi<Paged<Olt>>(`/olts?${params}`);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">OLT</h1>
        {canManage && (
          <Link href="/olt/nouveau" className="flex h-11 items-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white">
            Nouvel OLT
          </Link>
        )}
      </div>

      <div className="space-y-1">
        <label htmlFor="search" className="text-sm font-medium">
          Rechercher
        </label>
        <input
          id="search"
          type="search"
          placeholder="Nom de l'OLT"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setPage(1);
          }}
          className="h-11 w-full rounded-lg border border-zinc-300 px-3 text-base"
        />
      </div>

      <AsyncList state={state} reload={reload} empty={q ? "Aucun OLT ne correspond à votre recherche." : "Aucun OLT. Ils sont créés à l'import du réseau, ou ici."}>
        {(olts) => (
          <>
            <ul className="space-y-2">
              {olts.map((olt) => (
                <li key={olt.id} className="space-y-1 rounded-xl border border-zinc-200 bg-white p-4">
                  <p className="font-medium">
                    {canManage ? (
                      <Link href={`/olt/${olt.id}`} className="underline-offset-2 hover:underline">
                        {olt.name}
                      </Link>
                    ) : (
                      olt.name
                    )}
                  </p>
                  {olt.notes && <p className="text-sm text-zinc-700">{olt.notes}</p>}
                </li>
              ))}
            </ul>
            {state.status === "ok" && <Pager page={state.data.meta.current_page} lastPage={state.data.meta.last_page} onChange={setPage} />}
          </>
        )}
      </AsyncList>
    </div>
  );
}
