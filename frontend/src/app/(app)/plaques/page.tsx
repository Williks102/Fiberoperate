import { NetworkTree } from "@/components/network-tree";

/** Les plaques et tout ce qu'on y déploie : sous-plaques, SRO, splitters et PCO. */
export default function PlaquesPage() {
  return (
    <div className="space-y-4">
      <div className="space-y-1">
        <h1 className="text-xl font-semibold">Plaques</h1>
        <p className="text-sm text-zinc-700">Une plaque, ses sous-plaques, leur SRO, leurs splitters et leurs PCO. Touchez une ligne pour la déplier. Une plaque se crée avec son projet.</p>
      </div>
      <NetworkTree />
    </div>
  );
}
