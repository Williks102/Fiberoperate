import { describe, expect, it } from "vitest";
import { clientName, formatDate, formatDateTime, formatFcfa } from "./format";

describe("formatDateTime", () => {
  it("affiche jour/mois/année et l'heure d'Abidjan", () => {
    expect(formatDateTime("2026-10-30T18:35:00Z")).toBe("30/10/2026 18:35");
  });

  it("reste lisible sans date ou avec une date invalide", () => {
    expect(formatDateTime(null)).toBe("—");
    expect(formatDateTime("n'importe quoi")).toBe("—");
  });
});

describe("formatFcfa", () => {
  it("groupe les milliers et accepte l'absence de prix", () => {
    expect(formatFcfa(150000)).toBe("150 000 FCFA");
    expect(formatFcfa(0)).toBe("0 FCFA");
    expect(formatFcfa(null)).toBe("—");
  });
});

describe("formatDate", () => {
  it("met une date de l'API au format français", () => {
    expect(formatDate("2026-10-31")).toBe("31/10/2026");
    expect(formatDate("2026-10-31T00:00:00Z")).toBe("31/10/2026");
    expect(formatDate(null)).toBe("—");
    expect(formatDate("n'importe quoi")).toBe("—");
  });
});

describe("clientName", () => {
  it("remplace un nom absent", () => {
    expect(clientName("Koné Awa")).toBe("Koné Awa");
    expect(clientName("")).toBe("Client sans nom");
    expect(clientName(null)).toBe("Client sans nom");
  });
});
