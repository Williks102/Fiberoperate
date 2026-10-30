"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { AsyncList } from "@/components/async-list";
import { Pager } from "@/components/pager";
import { apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import { formatDateTime } from "@/lib/format";
import type { Paged } from "@/lib/types";
import { useApi } from "@/lib/use-api";

interface AppNotification {
  id: string;
  title: string;
  message: string;
  link: string | null;
  read: boolean;
  created_at: string | null;
}

type Page = Paged<AppNotification> & { meta: Paged<AppNotification>["meta"] & { unread_count: number } };

export default function NotificationsPage() {
  const router = useRouter();
  const [page, setPage] = useState(1);
  const { reload, ...state } = useApi<Page>(`/notifications?per_page=20&page=${page}`);

  async function open(n: AppNotification) {
    if (!n.read) await apiFetch(`/notifications/${n.id}/read`, { method: "POST" }, getToken()).catch(() => {});
    if (n.link) router.push(n.link);
    else reload();
  }

  async function readAll() {
    await apiFetch("/notifications/read-all", { method: "POST" }, getToken());
    reload();
  }

  return (
    <div className="max-w-2xl space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">Notifications</h1>
        {state.status === "ok" && state.data.meta.unread_count > 0 && (
          <button onClick={readAll} className="h-11 rounded-lg border border-zinc-300 px-4 text-sm font-medium">
            Tout marquer comme lu
          </button>
        )}
      </div>

      <AsyncList state={state} reload={reload} empty="Aucune notification pour l'instant.">
        {(items) => (
          <>
            <ul className="space-y-2">
              {items.map((n) => (
                <li key={n.id}>
                  <button
                    onClick={() => open(n)}
                    className={`w-full space-y-1 rounded-xl border p-4 text-left ${n.read ? "border-zinc-200 bg-white" : "border-slate-900 bg-white"}`}
                  >
                    <span className="flex flex-wrap items-center justify-between gap-2">
                      <span className="font-medium">{n.title}</span>
                      {!n.read && <span className="rounded-full bg-slate-900 px-2 py-0.5 text-xs font-medium text-white">Nouveau</span>}
                    </span>
                    <span className="block text-sm text-zinc-700">{n.message}</span>
                    <span className="block text-xs text-zinc-700">{formatDateTime(n.created_at)}</span>
                  </button>
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
