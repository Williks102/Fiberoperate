import { afterEach, describe, expect, it, vi } from "vitest";
import { apiFetch, errorMessage } from "./api";

describe("errorMessage", () => {
  it("préfère le premier message de validation", () => {
    expect(errorMessage(422, { errors: { email: ["Identifiants incorrects."] } })).toBe("Identifiants incorrects.");
  });

  it("explique le blocage après trop d'essais", () => {
    expect(errorMessage(429, null)).toContain("Trop de tentatives");
  });

  it("reprend le message d'un compte désactivé", () => {
    expect(errorMessage(403, { message: "Ce compte est désactivé.", code: "ACCOUNT_DISABLED" })).toBe("Ce compte est désactivé.");
  });

  it("remplace le refus générique de Laravel (anglais) par un message français", () => {
    expect(errorMessage(403, { message: "This action is unauthorized." })).toBe("Vous n'avez pas le droit d'effectuer cette action.");
  });

  it("reste compréhensible sur une erreur serveur", () => {
    expect(errorMessage(500, null)).toContain("serveur");
  });
});

describe("apiFetch", () => {
  afterEach(() => vi.unstubAllGlobals());

  it("laisse le navigateur fixer le Content-Type d'un envoi de fichier", async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ ok: true }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);

    await apiFetch("/imports/network", { method: "POST", body: new FormData() }, "jeton");

    const headers = fetchMock.mock.calls[0][1].headers as Record<string, string>;
    expect(headers["Content-Type"]).toBeUndefined();
    expect(headers.Authorization).toBe("Bearer jeton");
  });

  it("envoie du JSON par défaut", async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response("{}", { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);

    await apiFetch("/users", { method: "POST", body: "{}" });

    expect((fetchMock.mock.calls[0][1].headers as Record<string, string>)["Content-Type"]).toBe("application/json");
  });
});
