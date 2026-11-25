<?php

namespace App\Http\Middleware;

use App\Services\SecurityAudit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class EnforceRequestWhitelist
{
    public static function routeKey(Route $route): string
    {
        $name = $route->getName();
        if ($name !== null && ! str_starts_with($name, 'generated::')) {
            return $name;
        }

        return 'uri:'.preg_replace('~^livewire-[0-9a-f]{8}/~', 'livewire/', $route->uri());
    }

    public function handle(Request $request, Closure $next): Response
    {
        $mode = config('security.whitelist_mode');
        if ($mode === 'off') {
            return $next($request);
        }

        $failure = $this->failure($request);
        if ($failure !== null) {
            app(SecurityAudit::class)->record('whitelist_'.$failure, $request);
            abort_unless($mode === 'report', $failure === 'value' ? 422 : 403);
        }

        return $next($request);
    }

    private function failure(Request $request): ?string
    {
        $key = self::routeKey($request->route());
        $rule = config('request_whitelist.routes')[$key] ?? null;
        $method = $request->isMethod('HEAD') ? 'GET' : $request->method();
        if ($rule === null || $method !== $rule[0] || strlen($request->server('QUERY_STRING', '')) > 2048) {
            return 'shape';
        }

        $query = $request->query();
        if (array_diff_key($query, $rule[1] ?? [])) {
            return 'shape';
        }
        if (! $this->fieldsMatch($query, $rule[1] ?? [])) {
            return 'value';
        }

        if ($key === 'livewire.update') {
            return $this->livewireFailure($request);
        }

        $maxBytes = match ($key) {
            'livewire.upload-file', 'storage.local.upload' => 3 * 1024 * 1024,
            'logout' => 2048,
            default => 0,
        };
        if ((int) $request->server('CONTENT_LENGTH', 0) > $maxBytes || strlen($request->getContent()) > $maxBytes) {
            return 'shape';
        }

        if ($key === 'livewire.upload-file') {
            $files = $request->allFiles();
            if ($request->getContentTypeFormat() !== 'form' || $request->request->all()
                || array_keys($files) !== ['files'] || ! is_array($files['files'])
                || ! array_is_list($files['files']) || count($files['files']) !== 1) {
                return 'shape';
            }
            $file = $files['files'][0];

            return $file instanceof UploadedFile && $file->isValid() && $file->getSize() <= 2 * 1024 * 1024 ? null : 'value';
        }

        if ($key === 'storage.local.upload') {
            // The framework validates the signed URL and consumes the raw stream.
            return $request->request->all() || $request->allFiles() ? 'shape' : null;
        }

        $fields = $request->request->all();
        if ($request->allFiles() || array_diff_key($fields, $rule[2] ?? [])
            || ($key === 'logout' && $request->getContentTypeFormat() !== 'form')) {
            return 'shape';
        }

        return $this->fieldsMatch($fields, $rule[2] ?? []) ? null : 'value';
    }

    private function livewireFailure(Request $request): ?string
    {
        $body = $request->getContent();
        if (! $request->isJson() || strlen($body) > 1024 * 1024) {
            return 'shape';
        }

        try {
            $payload = json_decode($body, true, 24, JSON_THROW_ON_ERROR);
            $components = $payload['components'] ?? null;
            if (! is_array($payload) || array_diff_key($payload, ['_token' => true, 'components' => true])
                || (isset($payload['_token']) && ! $this->matches($payload['_token'], 's:128'))
                || ! is_array($components) || ! array_is_list($components) || count($components) < 1 || count($components) > 10) {
                return 'shape';
            }
            foreach ($components as $component) {
                $failure = $this->componentFailure($component);
                if ($failure !== null) {
                    return $failure;
                }
            }
        } catch (JsonException) {
            return 'shape';
        }

        return null;
    }

    private function componentFailure(mixed $component): ?string
    {
        if (! is_array($component) || array_diff_key($component, ['snapshot' => true, 'updates' => true, 'calls' => true])
            || ! is_string($component['snapshot'] ?? null) || ! is_array($component['updates'] ?? null)
            || ! is_array($component['calls'] ?? null) || ! array_is_list($component['calls'])
            || count($component['calls']) > 20 || count($component['updates']) > 32) {
            return 'shape';
        }

        // Livewire independently authenticates the snapshot checksum and authorizes actions.
        $snapshot = json_decode($component['snapshot'], true, 64, JSON_THROW_ON_ERROR);
        $name = $snapshot['memo']['name'] ?? null;
        $rule = is_string($name) ? config('request_whitelist.components')[$name] ?? null : null;
        if ($rule === null || array_diff_key($component['updates'], $rule[0])) {
            return 'shape';
        }
        if (! $this->fieldsMatch($component['updates'], $rule[0])) {
            return 'value';
        }

        foreach ($component['calls'] as $call) {
            if (! is_array($call) || array_diff_key($call, ['method' => true, 'params' => true, 'metadata' => true, 'path' => true])
                || ! is_string($call['method'] ?? null) || ! is_array($call['params'] ?? null) || ! array_is_list($call['params'])
                || (isset($call['metadata']) && (! is_array($call['metadata']) || count($call['metadata']) > 12))
                || (isset($call['path']) && $call['path'] !== '')) {
                return 'shape';
            }

            $params = $call['params'];
            $method = $call['method'];
            $types = $rule[1][$method] ?? null;
            if ($types === null && isset($rule[2])) {
                $types = match ($method) {
                    '_startUpload' => ['s:32', 'file-info', 'bool'],
                    '_finishUpload' => count($params) === 4 ? ['s:32', 'uploads', 'bool', 'bool'] : ['s:32', 'uploads', 'bool'],
                    '_uploadErrored' => ['s:32', '?s:8192', 'bool'],
                    '_removeUpload' => ['s:32', 's:1024'],
                    default => null,
                };
                if ($types !== null && (! is_string($params[0] ?? null) || ! preg_match($rule[2], $params[0]))) {
                    return 'shape';
                }
            }
            if ($types === null) {
                return 'shape';
            }
            if (count($params) !== count($types) || ! $this->fieldsMatch($params, $types)) {
                return 'value';
            }
        }

        return null;
    }

    private function fieldsMatch(array $values, array $types): bool
    {
        foreach ($values as $key => $value) {
            if (! isset($types[$key]) || ! $this->matches($value, $types[$key])) {
                return false;
            }
        }

        return true;
    }

    private function matches(mixed $value, string $type): bool
    {
        if (str_starts_with($type, '?')) {
            return $value === null || $this->matches($value, substr($type, 1));
        }
        if (str_starts_with($type, 's:')) {
            return is_string($value) && mb_strlen($value) <= (int) substr($type, 2);
        }

        return match ($type) {
            'bool' => is_bool($value),
            'int' => is_int($value) && abs($value) <= 1000000,
            'input-int' => is_int($value) || (is_string($value) && preg_match('/^[0-9]{0,6}$/D', $value) === 1),
            'uuid' => is_string($value) && Str::isUuid($value),
            'browser' => is_string($value) && preg_match('/^[A-Za-z0-9_-]{24,64}$/D', $value) === 1,
            'hex' => is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1,
            'page' => is_string($value) && preg_match('/^[0-9]{1,12}$/D', $value) === 1,
            'uuids' => $this->listMatches($value, 99, fn ($id) => $this->matches($id, 'uuid')),
            'uploads' => $this->listMatches($value, 1, fn ($path) => $this->matches($path, 's:1024')) && count($value) === 1,
            'file-info' => $this->listMatches($value, 1, fn ($file) => is_array($file)
                && count($file) === 3 && $this->fieldsMatch($file, ['name' => 's:255', 'size' => 'file-size', 'type' => 's:100']))
                && count($value) === 1,
            'file-size' => is_int($value) && $value >= 0 && $value <= 2 * 1024 * 1024,
            'candidates' => $this->listMatches($value, 99, fn ($row) => is_array($row) && $this->fieldsMatch($row, [
                'number' => 'input-int', 'name' => 's:160', 'vision' => 's:600', 'missions' => 's:2000',
            ])),
            default => false,
        };
    }

    private function listMatches(mixed $value, int $max, Closure $matches): bool
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > $max) {
            return false;
        }
        foreach ($value as $item) {
            if (! $matches($item)) {
                return false;
            }
        }

        return true;
    }
}
