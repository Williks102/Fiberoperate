<?php

namespace App\Domain\Photos;

use RuntimeException;

/**
 * Envoi direct des photos vers Cloudinary, signé par l'API : le téléphone n'a jamais le secret. Les photos sont en mode
 * `authenticated` (aucune URL publique) et rangées sous `{dossier}/{commande}/{uuid de la photo}`, un chemin que le serveur impose.
 */
final class Cloudinary
{
    public const TYPE = 'authenticated';

    /** @var array{cloud: string, key: string, secret: string} */
    private array $credentials;

    public function __construct(?string $url = null, private readonly string $folder = 'ftth')
    {
        $url ??= config('services.cloudinary.url');
        $parts = $url ? parse_url($url) : false;

        $this->credentials = $parts && ($parts['scheme'] ?? '') === 'cloudinary' && isset($parts['user'], $parts['pass'], $parts['host'])
            ? ['cloud' => $parts['host'], 'key' => urldecode($parts['user']), 'secret' => urldecode($parts['pass'])]
            : ['cloud' => '', 'key' => '', 'secret' => ''];
    }

    public static function fromConfig(): self
    {
        return new self(config('services.cloudinary.url'), (string) config('services.cloudinary.folder', 'ftth'));
    }

    public function isConfigured(): bool
    {
        return $this->credentials['cloud'] !== '';
    }

    /** Identifiant imposé à la photo : une photo ne peut être rangée qu'à sa place (commande et uuid de la saisie). */
    public function publicId(string $orderId, string $photoUuid): string
    {
        return "{$this->folder}/{$orderId}/{$photoUuid}";
    }

    /**
     * Paramètres d'un envoi signé, à donner au téléphone (clé publique, jamais le secret).
     *
     * @return array{upload_url: string, api_key: string, timestamp: int, signature: string, public_id: string, type: string}
     */
    public function uploadParameters(string $orderId, string $photoUuid, ?int $timestamp = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudinary non configuré.');
        }

        $params = ['public_id' => $this->publicId($orderId, $photoUuid), 'timestamp' => $timestamp ?? time(), 'type' => self::TYPE];

        return [
            'upload_url' => "https://api.cloudinary.com/v1_1/{$this->credentials['cloud']}/image/upload",
            'api_key' => $this->credentials['key'],
            'timestamp' => $params['timestamp'],
            'signature' => $this->sign($params),
            'public_id' => $params['public_id'],
            'type' => $params['type'],
        ];
    }

    /**
     * Signature Cloudinary : paramètres triés par nom, joints en « nom=valeur » par « & », suivis du secret, puis SHA-1.
     *
     * @param  array<string, scalar>  $params  sans `file`, `api_key`, `resource_type` ni `cloud_name`
     */
    public function sign(array $params): string
    {
        ksort($params);
        $toSign = implode('&', array_map(fn ($key, $value) => "{$key}={$value}", array_keys($params), $params));

        return sha1($toSign.$this->credentials['secret']);
    }
}
