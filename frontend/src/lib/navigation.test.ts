import { describe, expect, it } from "vitest";
import { BOTTOM_BAR_MAX, bottomBarFor, groupsFor, isActive, isGroupActive, NAV_GROUPS, navFor } from "./navigation";
import type { Role } from "./types";

const ROLES: Role[] = ["admin", "dg", "dt", "supervisor", "coordinator", "technician"];
const labels = (role: Role) => groupsFor(role).map((g) => g.label);

describe("menu par activité", () => {
  it("montre l'accueil à tous les rôles", () => {
    for (const role of ROLES) expect(navFor(role)[0].href).toBe("/");
  });

  it("range les écrans du réseau dans Déploiement et les comptes dans Administration", () => {
    const names = (label: string) => NAV_GROUPS.find((g) => g.label === label)!.items.map((i) => i.href);
    expect(names("Déploiement")).toEqual(expect.arrayContaining(["/reseau", "/plaques", "/sous-plaques", "/splitters", "/olt", "/importer"]));
    expect(names("Administration")).toEqual(expect.arrayContaining(["/equipes", "/utilisateurs"]));
  });

  it("limite chaque rôle aux activités et écrans auxquels il a droit", () => {
    expect(labels("admin")).toEqual(["Déploiement", "Administration"]);
    expect(labels("supervisor")).toEqual(["Déploiement"]);
    expect(labels("technician")).toEqual(["Déploiement"]);
    expect(groupsFor("supervisor")[0].items.map((i) => i.href)).toEqual(["/reseau", "/olt"]);
    expect(groupsFor("technician")[0].items.map((i) => i.href)).toEqual(["/reseau"]);
    expect(groupsFor("coordinator").find((g) => g.label === "Administration")!.items.map((i) => i.href)).toEqual(["/equipes"]);
  });

  it("masque une activité dont aucun écran n'est accessible", () => {
    NAV_GROUPS.push({ href: "/secret", label: "Secret", description: "", items: [{ href: "/secret/a", label: "A", roles: ["admin"] }] });
    try {
      expect(labels("admin")).toContain("Secret");
      expect(labels("technician")).not.toContain("Secret");
    } finally {
      NAV_GROUPS.pop();
    }
  });

  it("repère l'écran actif et l'activité active", () => {
    expect(isActive("/", "/")).toBe(true);
    expect(isActive("/plaques", "/")).toBe(false);
    expect(isActive("/plaques/abc", "/plaques")).toBe(true);
    expect(isActive("/plaquesX", "/plaques")).toBe(false);
    const deploiement = NAV_GROUPS[0];
    expect(isGroupActive("/deploiement", deploiement)).toBe(true);
    expect(isGroupActive("/splitters/12", deploiement)).toBe(true);
    expect(isGroupActive("/utilisateurs", deploiement)).toBe(false);
  });
});

describe("barre du bas (téléphone)", () => {
  it("montre l'accueil et les activités, sans « Plus » tant que tout tient", () => {
    const { main, hasMore } = bottomBarFor("admin");
    expect(main.map((m) => m.label)).toEqual(["Accueil", "Déploiement", "Administration"]);
    expect(hasMore).toBe(false);
    expect(bottomBarFor("technician").main.map((m) => m.label)).toEqual(["Accueil", "Déploiement"]);
  });

  it("ne dépasse jamais 4 entrées et range le reste dans « Plus »", () => {
    const extra = ["Études", "Raccordement", "Suivi Rac"].map((label, i) => ({ href: `/x${i}`, label, description: "", items: [{ href: `/x${i}/a`, label: "A" }] }));
    NAV_GROUPS.push(...extra);
    try {
      for (const role of ROLES) expect(bottomBarFor(role).main.length).toBeLessThanOrEqual(BOTTOM_BAR_MAX);
      const admin = bottomBarFor("admin");
      expect(admin.hasMore).toBe(true);
      expect(admin.main.map((m) => m.label)).toEqual(["Accueil", "Déploiement", "Administration", "Études"]);
    } finally {
      NAV_GROUPS.splice(-3, 3);
    }
  });
});
