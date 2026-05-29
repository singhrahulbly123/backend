<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SecretsManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SettingAdminController extends Controller
{
    public function __construct(private readonly SecretsManager $secrets) {}

    protected function ensureSettingsTableExists(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    protected function ensureSettingsTableExists(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function index(): JsonResponse
    {
        $this->ensureSettingsTableExists();

        $knownKeys = $this->secrets->sensitiveKeys();
        $dbSettings = Setting::all()->keyBy('key');
        $settings = [];

        foreach ($knownKeys as $key) {
            $key = strtoupper($key);
            $value = null;
            $source = 'none';

            if (isset($dbSettings[$key])) {
                $value = $dbSettings[$key]->value;
                $source = 'db';
            } else {
                $envValue = env($key);
                if ($envValue !== null && trim((string)$envValue) !== '') {
                    $value = $envValue;
                    $source = 'env';
                }
            }

            $settings[$key] = $this->serializeSettingData($key, $value, $source);
        }

        foreach ($dbSettings as $key => $setting) {
            $key = strtoupper($key);
            if (!isset($settings[$key])) {
                $settings[$key] = $this->serializeSettingData($key, $setting->value, 'db');
            }
        }

        ksort($settings);

        return response()->json(['settings' => array_values($settings)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureSettingsTableExists();

        $data = $request->validate([
            'key' => ['required', 'string', 'max:255', 'regex:/^[A-Z0-9_\.]+$/'],
            'value' => ['nullable', 'string'],
        ]);

        $key = strtoupper($data['key']);
        $incomingValue = $data['value'] ?? null;

        if ($this->isSensitive($key) && $this->isMaskedPlaceholder($incomingValue)) {
            $setting = Setting::where('key', $key)->first();
            $val = $setting ? $setting->value : null;
            $source = $setting ? 'db' : 'none';

            if (!$setting) {
                $envVal = env($key);
                if ($envVal !== null && trim((string)$envVal) !== '') {
                    $val = $envVal;
                    $source = 'env';
                }
            }

            return response()->json(['setting' => $this->serializeSettingData($key, $val, $source)]);
        }

        if ($incomingValue === null || trim($incomingValue) === '') {
            Setting::where('key', $key)->delete();
            $envVal = env($key);
            $source = ($envVal !== null && trim((string)$envVal) !== '') ? 'env' : 'none';
            $val = ($envVal !== null && trim((string)$envVal) !== '') ? $envVal : null;

            return response()->json(['setting' => $this->serializeSettingData($key, $val, $source)]);
        }

        $setting = Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $incomingValue]
        );

        return response()->json(['setting' => $this->serializeSettingData($key, $setting->value, 'db')]);
    }

    protected function serializeSetting(Setting $setting): array
    {
        return $this->serializeSettingData($setting->key, $setting->value, 'db');
    }

    protected function serializeSettingData(string $key, ?string $value, string $source): array
    {
        $value = (string) ($value ?? '');
        $sensitive = $this->isSensitive($key);

        return [
            'key' => $key,
            'value' => $sensitive && $value !== '' ? $this->maskValue($value) : ($value === '' ? null : $value),
            'sensitive' => $sensitive,
            'has_value' => trim($value) !== '',
            'source' => $source,
        ];
    }

    protected function isSensitive(string $key): bool
    {
        return in_array(strtoupper($key), $this->secrets->sensitiveKeys(), true)
            || str_ends_with(strtoupper($key), '_API_KEY')
            || str_contains(strtoupper($key), 'SECRET')
            || str_contains(strtoupper($key), 'TOKEN');
    }

    protected function maskValue(?string $value): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        $tail = substr($value, -4);

        return str_repeat('*', max(8, min(20, strlen($value) - 4))).$tail;
    }

    protected function isMaskedPlaceholder(?string $value): bool
    {
        return is_string($value) && preg_match('/^\*{4,}.*$/', $value) === 1;
    }
}
