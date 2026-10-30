import { NetworkTree } from "@/components/network-tree";

export default function ReseauPage() {
  return (
    <div className="space-y-4">
      <div className="space-y-1">
        <h1 className="text-xl font-semibold">Réseau</h1>
        <p className="text-sm text-zinc-700">Plaque, sous-plaques, splitters et PCO. Touchez une ligne pour la déplier.</p>
      </div>
      <NetworkTree />
    </div>
  );
}
