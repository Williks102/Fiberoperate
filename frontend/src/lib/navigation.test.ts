import { describe, expect, it } from "vitest";
import { BOTTOM_BAR_MAX, bottomBarFor, groupsFor, isActive, isGroupActive, NAV_GROUPS, navFor } from "./navigation";
import type { Role } from "./types";

const ROLES: Role[] = ["admin", "dg", "dt", "supervisor", "coordinator", "technician", "be_manager"];
const labels = (role: Role) => groupsFor(role).map((g) => g.label);

describe("menu par activité", () => {
  it("montre l'accueil à tous les rôles", () => {
    for (const role of ROLES) expect(navFor(role)[0].href).toBe("/");
  });

  it("range les écrans du réseau dans Déploiement et les comptes dans Administration", () => {
    const names = (label: string) => NAV_GROUPS.find((g) => g.label === label)!.items.map((i) => i.href);
    expect(names("Déploiement")).toEqual(expect.arrayContaining(["/plaques", "/sous-plaques", "/sro", "/splitters", "/pco", "/olt"]));
    expect(names("Administration")).toEqual(expect.arrayContaining(["/equipes", "/utilisateurs"]));
  });

  it("montre les écrans du réseau au chef du BE et aux techniciens habilités « dessin » (le PCO aussi aux « relevés »)", () => {
    const hrefs = (role: Role, skills: Parameters<typeof groupsFor>[1] = []) => groupsFor(role, skills).find((g) => g.label === "Déploiement")!.items.map((i) => i.href);
    const drawing = ["/plaques", "/sous-plaques", "/sro", "/splitters", "/pco"];

    expect(hrefs("technician", ["drawing"])).toEqual(drawing);
    expect(hrefs("technician", ["survey"])).toEqual(["/plaques", "/pco"]);
    expect(hrefs("technician")).toEqual(["/plaques"]);
    expect(hrefs("be_manager")).toEqual([...drawing, "/olt"]);
  });

  it("limite chaque rôle aux activités et écrans auxquels il a droit", () => {
    expect(labels("admin")).toEqual(["Projet", "Déploiement", "Raccordement", "Administration"]);
    expect(labels("supervisor")).toEqual(["Projet", "Déploiement"]);
    expect(labels("coordinator")).toEqual(["Déploiement", "Raccordement", "Administration"]);
    expect(labels("technician")).toEqual(["Déploiement"]);
    expect(groupsFor("technician", ["drawing"]).map((g) => g.label)).toEqual(["Projet", "Déploiement"]);
    expect(groupsFor("technician", ["survey"]).map((g) => g.label)).toEqual(["Projet", "Déploiement"]);
    expect(groupsFor("technician", ["deployment"]).map((g) => g.label)).toEqual(["Déploiement"]);
    expect(groupsFor("supervisor")[1].items.map((i) => i.href)).toEqual(["/plaques", "/olt"]);
    expect(groupsFor("technician")[0].items.map((i) => i.href)).toEqual(["/plaques"]);
    expect(labels("be_manager")).toEqual(["Projet", "Déploiement"]);
    expect(groupsFor("be_manager")[1].items.map((i) => i.href)).toEqual(["/plaques", "/sous-plaques", "/sro", "/splitters", "/pco", "/olt"]);
    expect(groupsFor("coordinator").find((g) => g.label === "Administration")!.items.map((i) => i.href)).toEqual(["/equipes"]);
  });

  it("montre les clients à la direction, au coordinateur et aux techniciens « étude » ou « raccordement client »", () => {
    const hasClients = (role: Role, skills: Parameters<typeof groupsFor>[1] = []) => groupsFor(role, skills).some((g) => g.items.some((i) => i.href === "/clients"));

    for (const role of ["admin", "dg", "dt", "coordinator"] as Role[]) expect(hasClients(role)).toBe(true);
    expect(hasClients("technician", ["study"])).toBe(true);
    expect(hasClients("technician", ["customer_connection"])).toBe(true);
    expect(hasClients("technician", ["deployment"])).toBe(false);
    expect(hasClients("supervisor")).toBe(false);
    expect(hasClients("be_manager")).toBe(false);
  });

  it("masque une activité dont aucun écran n'est accessible", () => {
    NAV_GROUPS.push({ href: "/secret", label: "Secret", description: "", icon: "admin", items: [{ href: "/secret/a", label: "A", roles: ["admin"] }] });
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
    const deploiement = NAV_GROUPS.find((g) => g.label === "Déploiement")!;
    expect(isGroupActive("/deploiement", deploiement)).toBe(true);
    expect(isGroupActive("/splitters/12", deploiement)).toBe(true);
    expect(isGroupActive("/utilisateurs", deploiement)).toBe(false);
  });
});

describe("barre du bas (téléphone)", () => {
  it("montre l'accueil et les activités, avec une icône chacune", () => {
    const { main } = bottomBarFor("admin");
    expect(main.map((m) => m.label)).toEqual(["Accueil", "Projet", "Déploiement", "Raccordement", "Administration"]);
    expect(main.map((m) => m.icon)).toEqual(["home", "project", "network", "customer", "admin"]);
    expect(bottomBarFor("technician").main.map((m) => m.label)).toEqual(["Accueil", "Déploiement"]);
  });

  it("ne dépasse jamais 5 icônes, le menu complet restant dans le tiroir", () => {
    const extra = ["Études", "Suivi Rac"].map((label, i) => ({ href: `/x${i}`, label, description: "", icon: "admin" as const, items: [{ href: `/x${i}/a`, label: "A" }] }));
    NAV_GROUPS.push(...extra);
    try {
      for (const role of ROLES) expect(bottomBarFor(role).main.length).toBeLessThanOrEqual(BOTTOM_BAR_MAX);
      expect(bottomBarFor("admin").main.map((m) => m.label)).toEqual(["Accueil", "Projet", "Déploiement", "Raccordement", "Administration"]);
      expect(bottomBarFor("admin").groups.length).toBe(6);
    } finally {
      NAV_GROUPS.splice(-2, 2);
    }
  });
});
