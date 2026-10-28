"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { useAuth } from "@/lib/auth/auth-context";

export default function Home() {
  const { user, loading, logout } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) router.replace("/login");
  }, [loading, user, router]);

  if (loading || !user) {
    return (
      <main className="flex flex-1 items-center justify-center p-6" aria-busy="true">
        <div className="h-6 w-40 animate-pulse rounded bg-zinc-200" />
      </main>
    );
  }

  return (
    <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6">
      <header className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">FTTH Gestion</h1>
        <button
          onClick={async () => {
            await logout();
            router.replace("/login");
          }}
          className="h-11 rounded-lg border border-zinc-300 px-4 text-sm font-medium"
        >
          Se déconnecter
        </button>
      </header>

      <section className="rounded-xl border border-zinc-200 bg-white p-5">
        <p className="text-lg">Bonjour {user.name}</p>
        <p className="text-sm text-zinc-600">
          {user.role_label} · {user.email}
        </p>
      </section>
    </main>
  );
}
