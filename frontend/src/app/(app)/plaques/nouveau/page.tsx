import { PlaqueForm } from "@/components/plaque-form";

export default function NouvellePlaquePage() {
  return (
    <div className="max-w-xl space-y-4">
      <h1 className="text-xl font-semibold">Nouvelle plaque</h1>
      <PlaqueForm />
    </div>
  );
}
