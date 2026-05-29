<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SecretsManager
{
    /**
     * Settings that contain credentials or operational secrets.
     *
     * @return array<int, string>
     */
    public function sensitiveKeys(): array
    {
        return [
            'OPENAI_API_KEY',
            'GEMINI_API_KEY',
            'GROQ_API_KEY',
            'ELEVENLABS_API_KEY',
            'ELEVENLABS_VOICE_ID',
            'ONESIGNAL_APP_ID',
            'ONESIGNAL_REST_API_KEY',
            'CLOUDFLARE_API_TOKEN',
            'CLOUDFLARE_ZONE_ID',
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'MEILISEARCH_KEY',
            'FFMPEG_PATH',
        ];
    }

    /**
     * Get a secret by key. Provider determined by SECRETS_PROVIDER env (env|aws).
     * If not found, returns $default.
     */
    public function get(string $key, $default = null)
    {
        $provider = env('SECRETS_PROVIDER', 'env');
        $envKey = strtoupper(str_replace('.', '_', $key));

        if ($provider === 'aws') {
            $val = $this->getFromAws($key);
            if ($val !== null) return $val;
        }

        // First try DB-stored settings, then fallback to environment variables.
        try {
            if (Schema::hasTable('settings')) {
                $setting = Setting::where('key', $envKey)->first();
                if ($setting !== null) {
                    $stored = trim((string) $setting->value);
                    if ($stored !== '') {
                        return $stored;
                    }
                }
            }
        } catch (QueryException $e) {
            Log::warning('Settings table unavailable, falling back to env config', ['key' => $envKey, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::warning('Error reading settings table, falling back to env config', ['key' => $envKey, 'error' => $e->getMessage()]);
        }

        $value = env($envKey, $default);

        // Support simple encrypted env values. If a value starts with "ENC:"
        // it is expected to be base64(iv + ciphertext) encrypted with AES-256-CBC
        // using the key from SECRETS_ENCRYPTION_KEY. If decryption fails,
        // return the original value so callers can handle it.
        if (is_string($value) && str_starts_with($value, 'ENC:')) {
            $enc = substr($value, 4);
            $decrypted = $this->decryptEnvValue($enc);
            if ($decrypted !== null) {
                return $decrypted;
            }
        }

        return $value;
    }

    protected function decryptEnvValue(string $b64): ?string
    {
        $key = env('SECRETS_ENCRYPTION_KEY');
        if (empty($key)) {
            Log::warning('SECRETS_ENCRYPTION_KEY not set; cannot decrypt secret');
            return null;
        }

        $raw = base64_decode($b64, true);
        if ($raw === false || strlen($raw) < 17) {
            return null;
        }

        // Expect first 16 bytes to be IV
        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);

        try {
            $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
            return $decrypted === false ? null : $decrypted;
        } catch (\Throwable $e) {
            Log::warning('Failed to decrypt env secret', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function getFromAws(string $secretId)
    {
        if (!class_exists('\Aws\\SecretsManager\\SecretsManagerClient')) {
            Log::warning('AWS SecretsManager client not available. Install aws/aws-sdk-php to enable.');
            return null;
        }

        try {
            $client = new \Aws\SecretsManager\SecretsManagerClient([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            ]);

            $result = $client->getSecretValue(['SecretId' => $secretId]);
            if (isset($result['SecretString'])) {
                $secret = $result['SecretString'];
                // Try to decode JSON, otherwise return string
                $decoded = json_decode($secret, true);
                return $decoded === null ? $secret : $decoded;
            }

            if (isset($result['SecretBinary'])) {
                return $result['SecretBinary'];
            }
        } catch (\Throwable $e) {
            Log::warning('Error fetching secret from AWS Secrets Manager', ['id' => $secretId, 'error' => $e->getMessage()]);
            return null;
        }

        return null;
    }

    /**
     * Helper to return all known secrets from ENV (not from AWS).
     */
    public function allFromEnv(): array
    {
        // Note: Laravel does not expose all env keys; this returns a curated list of keys useful for this app.
        $keys = [
            'OPENAI_API_KEY', 'GEMINI_API_KEY', 'GROQ_API_KEY',
            'MEILISEARCH_KEY', 'ELEVENLABS_API_KEY', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY',
        ];

        $out = [];
        foreach ($keys as $k) {
            $out[$k] = env($k);
        }
        return $out;
    }
}
