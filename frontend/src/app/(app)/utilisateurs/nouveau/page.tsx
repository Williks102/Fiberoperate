import { UserForm } from "@/components/user-form";

export default function NouvelUtilisateurPage() {
  return (
    <div className="max-w-xl space-y-4">
      <h1 className="text-xl font-semibold">Nouvel utilisateur</h1>
      <UserForm />
    </div>
  );
}
