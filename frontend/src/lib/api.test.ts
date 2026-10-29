import { describe, expect, it } from "vitest";
import { errorMessage } from "./api";

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
