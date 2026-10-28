export type Role = "admin" | "dg" | "dt" | "supervisor" | "coordinator" | "technician";

/** Reflet de App\Http\Resources\UserResource. */
export interface User {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  role: Role;
  role_label: string;
  manager_id: string | null;
  is_active: boolean;
  skills?: string[];
}
