import { apiFetch, ApiError } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import { getDb, type PhotoRow } from "./db";

export const MAX_PHOTOS = 6;
const MAX_SIDE = 1600;
const JPEG_QUALITY = 0.7;
const ORPHAN_AFTER_MS = 24 * 60 * 60 * 1000;

/** Dimensions réduites pour tenir dans `max` px de côté (jamais agrandies), proportions gardées. */
export function fitWithin(width: number, height: number, max = MAX_SIDE): { width: number; height: number } {
  const ratio = Math.min(1, max / Math.max(width, height));

  return { width: Math.max(1, Math.round(width * ratio)), height: Math.max(1, Math.round(height * ratio)) };
}

/** Réduit et compresse la photo (JPEG, 1600 px au plus) : quelques centaines de Ko au lieu de plusieurs Mo, utile en 3G. */
export async function compressImage(file: Blob): Promise<{ blob: Blob; width: number; height: number }> {
  const bitmap = await createImageBitmap(file);
  const { width, height } = fitWithin(bitmap.width, bitmap.height);
  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = height;
  const context = canvas.getContext("2d");
  if (!context) throw new Error("Impossible de traiter la photo sur ce téléphone.");
  context.drawImage(bitmap, 0, 0, width, height);
  bitmap.close();
  const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, "image/jpeg", JPEG_QUALITY));
  if (!blob) throw new Error("Impossible de traiter la photo sur ce téléphone.");

  return { blob, width, height };
}

export async function sha256Hex(blob: Blob): Promise<string> {
  const digest = await crypto.subtle.digest("SHA-256", await blob.arrayBuffer());

  return Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, "0")).join("");
}

/** Prend une photo : compressée, empreinte calculée, gardée sur le téléphone (pas encore rattachée à un résultat). */
export async function savePhoto(customerOrderId: string, file: Blob): Promise<PhotoRow> {
  const { blob, width, height } = await compressImage(file);
  const row: PhotoRow = {
    client_uuid: crypto.randomUUID(),
    customer_order_id: customerOrderId,
    blob,
    width,
    height,
    hash: await sha256Hex(blob),
    taken_at: new Date().toISOString(),
    attached: 0,
    attempts: 0,
    last_error: null,
  };
  await getDb().photos.put(row);

  return row;
}

export const readPhoto = (uuid: string) => getDb().photos.get(uuid);

/** Retire une photo prise par erreur, avant la saisie du résultat. */
export const discardPhoto = (uuid: string) => getDb().photos.delete(uuid);

/** Rattache les photos au résultat saisi : à partir de là, elles sont envoyées en arrière-plan. */
export async function attachPhotos(uuids: string[]): Promise<void> {
  const db = getDb();
  await db.transaction("rw", db.photos, async () => {
    for (const uuid of uuids) await db.photos.update(uuid, { attached: 1 });
  });
}

/** Photos rattachées et pas encore enregistrées par l'API. */
export const countPendingPhotos = () => getDb().photos.where("attached").equals(1).count();

let uploading: Promise<{ sent: number; failed: number }> | null = null;

/**
 * Envoie les photos rattachées, une à une, en arrière-plan : signature de l'API, envoi direct à Cloudinary, puis enregistrement par
 * l'API (idempotent : un renvoi après coupure ne crée rien en double). Une photo n'est retirée du téléphone qu'une fois enregistrée ;
 * sinon elle reste, avec son nombre d'essais. Un seul envoi à la fois. Les photos abandonnées (jamais rattachées) sont jetées après un jour.
 */
export function uploadPendingPhotos(): Promise<{ sent: number; failed: number }> {
  uploading ??= run().finally(() => {
    uploading = null;
  });

  return uploading;
}

async function run(): Promise<{ sent: number; failed: number }> {
  const db = getDb();
  const limit = new Date(Date.now() - ORPHAN_AFTER_MS).toISOString();
  await db.photos.where("attached").equals(0).filter((p) => p.taken_at < limit).delete();

  const result = { sent: 0, failed: 0 };
  const token = getToken();

  for (const photo of await db.photos.where("attached").equals(1).sortBy("taken_at")) {
    try {
      await upload(photo, token);
      await db.photos.delete(photo.client_uuid);
      result.sent++;
    } catch (e) {
      result.failed++;
      await db.photos.update(photo.client_uuid, { attempts: photo.attempts + 1, last_error: e instanceof ApiError || e instanceof Error ? e.message : "Envoi impossible." });
      if (e instanceof ApiError && e.status === 503) break; // photos non configurées côté serveur : inutile d'insister sur les suivantes
    }
  }

  return result;
}

interface Signature {
  upload_url: string;
  api_key: string;
  timestamp: number;
  signature: string;
  public_id: string;
  type: string;
}

async function upload(photo: PhotoRow, token: string | null): Promise<void> {
  const { data: sign } = await apiFetch<{ data: Signature }>("/uploads/sign", { method: "POST", body: JSON.stringify({ customer_order_id: photo.customer_order_id, client_uuid: photo.client_uuid }) }, token);

  const form = new FormData();
  form.append("file", photo.blob, `${photo.client_uuid}.jpg`);
  form.append("api_key", sign.api_key);
  form.append("timestamp", String(sign.timestamp));
  form.append("signature", sign.signature);
  form.append("public_id", sign.public_id);
  form.append("type", sign.type);
  const response = await fetch(sign.upload_url, { method: "POST", body: form });
  if (!response.ok) throw new Error("L'envoi de la photo a été refusé. Elle sera renvoyée.");
  const uploaded = (await response.json()) as { public_id: string; format?: string; bytes?: number; width?: number; height?: number };

  await apiFetch(
    "/photos",
    {
      method: "POST",
      body: JSON.stringify({
        client_uuid: photo.client_uuid,
        customer_order_id: photo.customer_order_id,
        public_id: uploaded.public_id ?? sign.public_id,
        format: uploaded.format ?? "jpg",
        bytes: uploaded.bytes ?? photo.blob.size,
        width: uploaded.width ?? photo.width,
        height: uploaded.height ?? photo.height,
        hash: photo.hash,
        taken_at: photo.taken_at,
      }),
    },
    token,
  );
}
