<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Photos\Cloudinary;
use App\Http\Controllers\Controller;
use App\Http\Requests\SignUploadRequest;
use App\Http\Requests\StorePhotoRequest;
use App\Models\Photo;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/** Photos de terrain : signature d'envoi direct vers Cloudinary, puis enregistrement idempotent de la photo envoyée. */
class PhotoController extends Controller
{
    public function sign(SignUploadRequest $request, Cloudinary $cloudinary): JsonResponse
    {
        if (! $cloudinary->isConfigured()) {
            return $this->notConfigured();
        }

        return response()->json(['data' => $cloudinary->uploadParameters($request->validated('customer_order_id'), $request->validated('client_uuid'))]);
    }

    public function store(StorePhotoRequest $request, Cloudinary $cloudinary): JsonResponse
    {
        $data = $request->validated();

        $existing = Photo::query()->where('client_uuid', $data['client_uuid'])->first();
        if ($existing !== null) {
            // Renvoi (réseau coupé après l'enregistrement) : on rend la photo déjà enregistrée, sans rien créer.
            return $existing->user_id === $request->user()->getKey()
                ? response()->json(['data' => $this->present($existing)])
                : response()->json(['message' => 'Cette photo est déjà enregistrée par un autre utilisateur.', 'code' => 'PHOTO_CONFLICT'], 409);
        }

        // Une photo ne se range qu'à la place que le serveur lui a donnée : pas d'identifiant choisi par le téléphone.
        if ($data['public_id'] !== $cloudinary->publicId($data['customer_order_id'], $data['client_uuid'])) {
            return response()->json(['message' => "Cette photo n'est pas rangée au bon endroit.", 'errors' => ['public_id' => ['Identifiant de photo inattendu.']]], 422);
        }

        $data['taken_at'] = CarbonImmutable::parse($data['taken_at'])->min(now()); // jamais dans le futur
        $photo = Photo::create($data + ['user_id' => $request->user()->getKey()]);

        return response()->json(['data' => $this->present($photo)], 201);
    }

    /** @return array<string, mixed> */
    private function present(Photo $photo): array
    {
        return ['id' => $photo->id, 'client_uuid' => $photo->client_uuid, 'customer_order_id' => $photo->customer_order_id, 'taken_at' => $photo->taken_at->toIso8601String(), 'bytes' => $photo->bytes];
    }

    private function notConfigured(): JsonResponse
    {
        return response()->json(['message' => "L'envoi de photos n'est pas encore configuré sur le serveur. Prévenez l'administrateur.", 'code' => 'PHOTOS_NOT_CONFIGURED'], 503);
    }
}
