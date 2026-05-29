<?php

namespace App\Services\AI;

class ResponseValidator
{
    public function validateDraft(array $draft): array
    {
        $required = ['title', 'ai_summary', 'body'];
        foreach ($required as $key) {
            if (empty($draft[$key])) {
                return ['valid' => false, 'reason' => "missing_{$key}"];
            }
        }

        // Ensure arrays are arrays
        $draft['tags'] = is_array($draft['tags'] ?? null) ? $draft['tags'] : [];
        $draft['key_points'] = is_array($draft['key_points'] ?? null) ? $draft['key_points'] : [];
        $draft['faqs'] = is_array($draft['faqs'] ?? null) ? $draft['faqs'] : [];

        // Basic length checks
        if (mb_strlen(strip_tags($draft['body'])) < 100) {
            return ['valid' => false, 'reason' => 'body_too_short'];
        }

        return ['valid' => true];
    }
}
