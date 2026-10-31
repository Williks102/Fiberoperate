import "fake-indexeddb/auto";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { getDb, type PhotoRow } from "./db";
import { resetDb } from "./db";
import { attachPhotos, countPendingPhotos, discardPhoto, fitWithin, uploadPendingPhotos } from "./photos";

const json = (body: unknown, status = 200) => Promise.resolve(new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } }));

const photo = (uuid: string, extra: Partial<PhotoRow> = {}): PhotoRow => ({
  client_uuid: uuid,
  customer_order_id: "order-1",
  blob: new Blob(["jpeg"], { type: "image/jpeg" }),
  width: 1200,
  height: 900,
  hash: "a".repeat(64),
  taken_at: new Date().toISOString(),
  attached: 1,
  attempts: 0,
  last_error: null,
  ...extra,
});

/** API + Cloudinary simulés ; `fail` fait échouer une étape. Garde la trace de ce qui est appelé. */
function servers(fail?: "sign" | "cloudinary" | "register" | "notconfigured") {
  const calls: string[] = [];
  const forms: FormData[] = [];
  vi.stubGlobal(
    "fetch",
    vi.fn((url: string, init?: RequestInit) => {
      if (url.includes("/uploads/sign")) {
        calls.push("sign");
        const { customer_order_id, client_uuid } = JSON.parse(String(init?.body));
        if (fail === "notconfigured") return json({ message: "Photos non configurées.", code: "PHOTOS_NOT_CONFIGURED" }, 503);
        if (fail === "sign") return Promise.reject(new TypeError("réseau"));

        return json({ data: { upload_url: "https://api.cloudinary.com/v1_1/demo/image/upload", api_key: "123", timestamp: 1700000000, signature: "sig", public_id: `ftth/${customer_order_id}/${client_uuid}`, type: "authenticated" } });
      }
      if (url.includes("cloudinary.com")) {
        calls.push("cloudinary");
        forms.push(init?.body as FormData);
        if (fail === "cloudinary") return Promise.resolve(new Response("{}", { status: 401 }));

        return json({ public_id: (init?.body as FormData).get("public_id"), format: "jpg", bytes: 111, width: 1200, height: 900 });
      }
      if (url.includes("/photos")) {
        calls.push("register");
        if (fail === "register") return Promise.reject(new TypeError("réseau"));

        return json({ data: {} }, 201);
      }

      return json({ data: [] });
    }),
  );

  return { calls, forms };
}

beforeEach(async () => {
  await resetDb();
});
afterEach(() => vi.unstubAllGlobals());

describe("réduction des photos", () => {
  it("garde les proportions, ne dépasse pas 1600 px et n'agrandit jamais", () => {
    expect(fitWithin(4000, 3000)).toEqual({ width: 1600, height: 1200 });
    expect(fitWithin(3000, 4000)).toEqual({ width: 1200, height: 1600 });
    expect(fitWithin(800, 600)).toEqual({ width: 800, height: 600 });
    expect(fitWithin(1, 5000)).toEqual({ width: 1, height: 1600 });
  });
});

describe("envoi des photos en arrière-plan", () => {
  it("signe, envoie à Cloudinary avec la signature, enregistre, puis retire la photo du téléphone", async () => {
    await getDb().photos.put(photo("p1"));
    const { calls, forms } = servers();

    expect(await uploadPendingPhotos()).toEqual({ sent: 1, failed: 0 });

    expect(calls).toEqual(["sign", "cloudinary", "register"]);
    const form = forms[0];
    expect(form.get("api_key")).toBe("123");
    expect(form.get("signature")).toBe("sig");
    expect(form.get("type")).toBe("authenticated");
    expect(form.get("public_id")).toBe("ftth/order-1/p1");
    expect(form.get("file")).toBeInstanceOf(Blob);
    expect(await countPendingPhotos()).toBe(0);
    expect(await getDb().photos.count()).toBe(0);
  });

  it("n'envoie que les photos rattachées à un résultat", async () => {
    await getDb().photos.put(photo("brouillon", { attached: 0 }));
    const { calls } = servers();

    expect(await uploadPendingPhotos()).toEqual({ sent: 0, failed: 0 });
    expect(calls).toEqual([]);

    await attachPhotos(["brouillon"]);
    expect(await uploadPendingPhotos()).toEqual({ sent: 1, failed: 0 });
  });

  it("garde la photo, avec son nombre d'essais, quand le réseau ou Cloudinary échoue, à chaque étape", async () => {
    for (const step of ["sign", "cloudinary", "register"] as const) {
      await resetDb();
      await getDb().photos.put(photo("p1"));
      servers(step);

      expect(await uploadPendingPhotos()).toEqual({ sent: 0, failed: 1 });

      const kept = await getDb().photos.get("p1");
      expect(kept?.attempts, step).toBe(1);
      expect(kept?.last_error, step).toBeTruthy();
    }
  });

  it("s'arrête quand les photos ne sont pas configurées sur le serveur, sans insister sur les suivantes", async () => {
    await getDb().photos.put(photo("p1", { taken_at: "2026-01-01T00:00:00.000Z" }));
    await getDb().photos.put(photo("p2", { taken_at: "2026-01-02T00:00:00.000Z" }));
    const { calls } = servers("notconfigured");

    expect(await uploadPendingPhotos()).toEqual({ sent: 0, failed: 1 });

    expect(calls).toEqual(["sign"]);
    expect((await getDb().photos.get("p1"))?.last_error).toContain("non configurées");
    expect((await getDb().photos.get("p2"))?.attempts).toBe(0);
  });

  it("jette les photos abandonnées depuis plus d'un jour, jamais les récentes ni les rattachées", async () => {
    const old = new Date(Date.now() - 2 * 24 * 3600 * 1000).toISOString();
    await getDb().photos.put(photo("vieille-abandonnee", { attached: 0, taken_at: old }));
    await getDb().photos.put(photo("recente-abandonnee", { attached: 0 }));
    await getDb().photos.put(photo("ancienne-rattachee", { attached: 1, taken_at: old }));
    servers("sign");

    await uploadPendingPhotos();

    const left = (await getDb().photos.toArray()).map((p) => p.client_uuid).sort();
    expect(left).toEqual(["ancienne-rattachee", "recente-abandonnee"]);
  });

  it("n'envoie qu'une fois quand deux envois sont demandés en même temps", async () => {
    await getDb().photos.put(photo("p1"));
    const { calls } = servers();

    await Promise.all([uploadPendingPhotos(), uploadPendingPhotos()]);

    expect(calls.filter((c) => c === "register")).toHaveLength(1);
  });

  it("retire une photo prise par erreur", async () => {
    await getDb().photos.put(photo("p1", { attached: 0 }));

    await discardPhoto("p1");

    expect(await getDb().photos.count()).toBe(0);
  });
});
