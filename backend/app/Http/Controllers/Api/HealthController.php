<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ollama\OllamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(OllamaClient $ollama): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'ollama' => $this->checkOllama($ollama),
        ];

        $healthy = collect($checks)->every(fn (array $check) => $check['ok']);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $pgvector = DB::scalar("select extversion from pg_extension where extname = 'vector'");

            return [
                'ok' => $pgvector !== null,
                'pgvector' => $pgvector,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkOllama(OllamaClient $ollama): array
    {
        try {
            $installed = $ollama->installedModels();
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $required = [config('services.ollama.embed_model'), config('services.ollama.chat_model')];
        $missing = array_values(array_filter(
            $required,
            fn (string $model) => ! $this->isInstalled($model, $installed),
        ));

        return [
            'ok' => $missing === [],
            'models' => $installed,
            'missing' => $missing,
        ];
    }

    /**
     * Ollama reports untagged models as "name:latest".
     */
    private function isInstalled(string $model, array $installed): bool
    {
        $name = str_contains($model, ':') ? $model : "{$model}:latest";

        return in_array($name, $installed, true);
    }
}
