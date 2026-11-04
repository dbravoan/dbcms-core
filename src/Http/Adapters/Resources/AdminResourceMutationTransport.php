<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Adapters\Resources;

use DbCms\Core\Http\Requests\Admin\ResourceMutationRequest;
use DbCms\Core\Services\Capabilities\ResourceCrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/** Web transport adapter; all validation and mutations belong to shared CRUD. */
final class AdminResourceMutationTransport
{
    public function __construct(private readonly ResourceCrudService $resources) {}

    public function create(string $key, ResourceMutationRequest $request): RedirectResponse
    {
        $result = $this->resources->create($key, $request->validated());
        $name = (string) ($result['name'] ?? '');
        $message = match ($key) {
            'market' => "Mercado {$name} creado.", 'language' => "Idioma {$name} ({$result['code']}) creado con éxito.",
            'user' => "Usuario {$name} creado exitosamente.", 'menu_item' => 'Elemento añadido al menú.', default => 'Menú creado exitosamente.',
        };

        return redirect()->route($this->index($key))->with('success', $message);
    }

    public function update(string $key, Model $model, ResourceMutationRequest $request): RedirectResponse
    {
        try {
            $result = $this->resources->update($key, $model->getKey(), $request->validated());
        } catch (ValidationException $exception) {
            if ($key === 'user') {
                return redirect()->route($this->index($key))->withErrors($exception->errors());
            }
            throw $exception;
        }
        $name = (string) ($result['name'] ?? '');
        $message = match ($key) {
            'market' => "Mercado {$name} actualizado.", 'language' => "Idioma {$name} actualizado.", 'user' => "Rol actualizado para {$name}.", default => 'Recurso actualizado.'
        };

        return redirect()->route($this->index($key))->with('success', $message);
    }

    public function delete(string $key, Model $model): RedirectResponse
    {
        try {
            $this->resources->delete($key, $model->getKey());
        } catch (ValidationException $exception) {
            return $this->blocked($key, $exception);
        }
        $name = (string) ($model->getAttributes()['name'] ?? '');
        $message = match ($key) {
            'market' => "Mercado eliminado: {$name}.", 'language' => "Idioma eliminado: {$name}.", 'user' => "Usuario {$name} eliminado correctamente.", 'menu' => "Menú {$name} eliminado.", default => 'Elemento eliminado.'
        };

        return redirect()->route($this->index($key))->with('success', $message);
    }

    public function toggle(string $key, Model $model): RedirectResponse
    {
        try {
            $result = $this->resources->toggle($key, $model->getKey());
        } catch (ValidationException $exception) {
            return $this->blocked($key, $exception);
        }
        $noun = $key === 'market' ? 'mercado' : 'idioma';

        return redirect()->route($this->index($key))->with('success', "Estado del {$noun} {$result['name']} actualizado.");
    }

    private function index(string $key): string
    {
        return match ($key) {
            'market', 'language' => 'admin.markets.index', 'user' => 'admin.users.index', default => 'admin.menus.index'
        };
    }

    private function blocked(string $key, ValidationException $exception): RedirectResponse
    {
        $redirect = redirect()->route($this->index($key));

        return $key === 'user' ? $redirect->withErrors($exception->errors()) : $redirect->with('error', collect($exception->errors())->flatten()->first());
    }
}
