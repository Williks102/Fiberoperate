const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public code?: string,
    /** Erreurs de validation par champ (422). */
    public errors: Record<string, string[]> = {},
  ) {
    super(message);
  }
}

interface ErrorBody {
  message?: string;
  code?: string;
  errors?: Record<string, string[]>;
}

/** Message lisible pour un utilisateur peu technique, en français. */
export function errorMessage(status: number, body: ErrorBody | null): string {
  if (status === 429) return "Trop de tentatives. Réessayez dans une minute.";
  if (status === 401) return "Votre session a expiré. Reconnectez-vous.";
  // Seuls les refus métier de l'API (avec un code) sont déjà rédigés en français.
  if (status === 403 && body?.code && body.message) return body.message;
  if (status === 403) return "Vous n'avez pas le droit d'effectuer cette action.";
  // Un 503 volontaire de l'API (fonction pas encore configurée, avec un code) est déjà rédigé en français.
  if (status === 503 && body?.code && body.message) return body.message;
  const firstValidation = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined;
  if (firstValidation) return firstValidation;
  if (status >= 500) return "Le serveur a rencontré un problème. Réessayez dans un instant.";
  return body?.message ?? "Une erreur est survenue.";
}

/** Appel JSON vers l'API Laravel (jeton Sanctum Bearer). */
export async function apiFetch<T>(path: string, init: RequestInit = {}, token?: string | null): Promise<T> {
  let response: Response;
  try {
    // Un envoi de fichier (FormData) fixe lui-même son Content-Type, avec la frontière du multipart.
    const isForm = typeof FormData !== "undefined" && init.body instanceof FormData;
    response = await fetch(`${API_URL}/api/v1${path}`, {
      ...init,
      headers: {
        Accept: "application/json",
        ...(isForm ? {} : { "Content-Type": "application/json" }),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...init.headers,
      },
    });
  } catch {
    throw new ApiError(0, "Impossible de joindre le serveur. Vérifiez votre connexion.");
  }

  if (response.status === 204) return undefined as T;

  const body = (await response.json().catch(() => null)) as (T & ErrorBody) | null;

  if (!response.ok) {
    throw new ApiError(response.status, errorMessage(response.status, body), body?.code, body?.errors);
  }

  return body as T;
}

/** Télécharge un fichier protégé par le jeton (un lien direct n'enverrait pas l'en-tête) et le propose à l'enregistrement. */
export async function apiDownload(path: string, fileName: string, token?: string | null): Promise<void> {
  let response: Response;
  try {
    response = await fetch(`${API_URL}/api/v1${path}`, { headers: token ? { Authorization: `Bearer ${token}` } : {} });
  } catch {
    throw new ApiError(0, "Impossible de joindre le serveur. Vérifiez votre connexion.");
  }
  if (!response.ok) {
    throw new ApiError(response.status, errorMessage(response.status, (await response.json().catch(() => null)) as ErrorBody | null));
  }

  const url = URL.createObjectURL(await response.blob());
  const link = document.createElement("a");
  link.href = url;
  link.download = fileName;
  link.click();
  URL.revokeObjectURL(url);
}
