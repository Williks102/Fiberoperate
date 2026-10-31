import "fake-indexeddb/auto";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { resetDb } from "./db";
import { pushOutbox } from "./engine";
import { countPending, enqueue, markOutcomeSeen, readOutbox, readUnseenOutcomes } from "./outbox";

const json = (body: unknown, status = 200) => Promise.resolve(new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } }));

/** Réponse de l'API : une issue par opération reçue, choisie par `outcome(uuid)`. */
function server(outcome: (operation: { customer_order_id: string }, index: number) => Record<string, unknown> | null) {
  const calls: { client_uuid: string; occurred_at: string }[][] = [];
  vi.stubGlobal(
    "fetch",
    vi.fn((url: string, init?: RequestInit) => {
      if (url.includes("/my-visits")) return json({ data: [], meta: {} });
      if (url.includes("/sync/push")) {
        const operations = JSON.parse(String(init?.body)).operations as { client_uuid: string; occurred_at: string; customer_order_id: string }[];
        calls.push(operations);

        return json({ data: operations.flatMap((o, i) => { const r = outcome(o, i); return r ? [{ client_uuid: o.client_uuid, ...r }] : []; }) });
      }

      return json({ data: [] });
    }),
  );

  return calls;
}

const study = (orderId = "o1", name = "Koné Awa") => enqueue({ type: "study.result", customer_order_id: orderId, payload: { result: "customer_absent" }, client_name: name });

beforeEach(async () => {
  await resetDb();
});
afterEach(() => vi.unstubAllGlobals());

describe("file d'envoi du terrain", () => {
  it("garde la saisie avec son heure, son appareil et un identifiant propre au téléphone", async () => {
    const row = await study();

    expect(row.client_uuid).toMatch(/^[0-9a-f-]{36}$/);
    expect(new Date(row.occurred_at).getTime()).toBeLessThanOrEqual(Date.now());
    expect(await countPending()).toBe(1);
  });

  it("retire une opération appliquée, et seulement quand le serveur l'a confirmée", async () => {
    await study();
    const calls = server(() => ({ status: "applied" }));

    const result = await pushOutbox();

    expect(result).toEqual({ applied: 1, failed: 0 });
    expect(calls).toHaveLength(1);
    expect(await countPending()).toBe(0);
  });

  it("garde tout en file quand le réseau est coupé, et compte les essais", async () => {
    await study();
    vi.stubGlobal("fetch", vi.fn(() => Promise.reject(new TypeError("réseau"))));

    await pushOutbox();
    await pushOutbox();

    const [row] = await readOutbox();
    expect(row.attempts).toBe(2);
    expect(row.last_error).toBeTruthy();
    expect(await countPending()).toBe(1);
  });

  it("garde un conflit avec sa raison et le nom du client, et le retire de la file", async () => {
    await study("o1", "Koné Awa");
    server(() => ({ status: "conflict", message: "Cette visite n'est plus la vôtre." }));

    expect(await pushOutbox()).toEqual({ applied: 0, failed: 1 });

    expect(await countPending()).toBe(0);
    const [outcome] = await readUnseenOutcomes();
    expect(outcome).toMatchObject({ status: "conflict", message: "Cette visite n'est plus la vôtre.", client_name: "Koné Awa", customer_order_id: "o1" });

    await markOutcomeSeen(outcome.client_uuid);
    expect(await readUnseenOutcomes()).toHaveLength(0);
  });

  it("traite un doublon selon son issue d'origine", async () => {
    await study("o1");
    await study("o2");
    let n = 0;
    server(() => (n++ === 0 ? { status: "duplicate", original_status: "applied" } : { status: "duplicate", original_status: "rejected", message: "Saisie invalide." }));

    expect(await pushOutbox()).toEqual({ applied: 1, failed: 1 });
    expect(await countPending()).toBe(0);
  });

  it("ne retire pas une opération sans réponse ni une réponse inattendue", async () => {
    await study("o1");
    await study("o2");
    server((op) => (op.customer_order_id === "o1" ? { status: "applied" } : null));

    expect(await pushOutbox()).toEqual({ applied: 1, failed: 0 });

    const left = await readOutbox();
    expect(left.map((r) => r.customer_order_id)).toEqual(["o2"]);
  });

  it("envoie dans l'ordre où les saisies ont eu lieu, par lots de 100", async () => {
    for (let i = 0; i < 130; i++) await enqueue({ type: "study.result", customer_order_id: `o${i}`, payload: {} });
    const calls = server(() => ({ status: "applied" }));

    expect(await pushOutbox()).toEqual({ applied: 130, failed: 0 });

    expect(calls.map((c) => c.length)).toEqual([100, 30]);
    const times = calls.flat().map((o) => o.occurred_at);
    expect([...times].sort()).toEqual(times);
  });

  it("n'envoie qu'une fois quand deux envois sont demandés en même temps", async () => {
    await study();
    const calls = server(() => ({ status: "applied" }));

    const [a, b] = await Promise.all([pushOutbox(), pushOutbox()]);

    expect(calls).toHaveLength(1);
    expect(a).toEqual(b);
  });

  it("ne fait rien quand la file est vide", async () => {
    const calls = server(() => ({ status: "applied" }));

    expect(await pushOutbox()).toEqual({ applied: 0, failed: 0 });
    expect(calls).toHaveLength(0);
  });
});
