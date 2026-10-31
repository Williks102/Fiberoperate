import { describe, expect, it } from "vitest";
import { clientName, formatDate, visitDay, formatDateTime, formatFcfa } from "./format";

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

describe("visitDay", () => {
  const today = new Date(2026, 9, 31); // 31/10/2026

  it("dit si la visite est en retard, aujourd'hui, demain ou plus tard", () => {
    expect(visitDay("2026-10-30", today)).toEqual({ label: "En retard (30/10/2026)", late: true });
    expect(visitDay("2026-10-31", today)).toEqual({ label: "Aujourd'hui", late: false });
    expect(visitDay("2026-11-01", today)).toEqual({ label: "Demain", late: false });
    expect(visitDay("2026-11-05", today)).toEqual({ label: "05/11/2026", late: false });
    expect(visitDay("n'importe quoi", today).label).toBe("—");
  });
});
