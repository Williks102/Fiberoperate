"use client";

import { Bell } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";

const REFRESH_MS = 30_000;

/** Cloche de l'en-tête : nombre de notifications non lues, mis à jour au changement d'écran et toutes les 30 s. */
export function NotificationBell() {
  const pathname = usePathname();
  const [unread, setUnread] = useState(0);

  useEffect(() => {
    let cancelled = false;
    const refresh = () =>
      apiFetch<{ meta: { unread_count: number } }>("/notifications?per_page=1", {}, getToken())
        .then((res) => !cancelled && setUnread(res.meta.unread_count))
        .catch(() => {});
    refresh();
    const timer = setInterval(refresh, REFRESH_MS);
    return () => {
      cancelled = true;
      clearInterval(timer);
    };
  }, [pathname]);

  return (
    <Link
      href="/notifications"
      aria-label={unread > 0 ? `Notifications : ${unread} non lue${unread > 1 ? "s" : ""}` : "Notifications"}
      className="relative flex h-11 w-11 items-center justify-center rounded-lg border border-zinc-300"
    >
      <Bell aria-hidden="true" className="h-5 w-5" />
      {unread > 0 && (
        <span aria-hidden="true" className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-700 px-1 text-xs font-semibold text-white">
          {unread > 9 ? "9+" : unread}
        </span>
      )}
    </Link>
  );
}
