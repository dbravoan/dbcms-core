<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Capabilities\Resources\CapabilityResource;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Models\FilterPage;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\MediaFile;
use DbCms\Core\Models\Menu;
use DbCms\Core\Models\MenuItem;
use DbCms\Core\Models\SystemNotification;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Resources\LanguageMutationHandler;
use DbCms\Core\Services\Resources\MarketMutationHandler;
use DbCms\Core\Services\Resources\MenuItemMutationHandler;
use DbCms\Core\Services\Resources\MenuMutationHandler;
use DbCms\Core\Services\Resources\UserMutationHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Token;

/**
 * The platform resources agents may manage, mirroring the backoffice screens.
 *
 * Permissions are the very same ones the admin screens require, so an agent
 * never exceeds the person who issued the token.
 */
class PlatformResourceCatalog
{
    /**
     * @return list<CapabilityResource>
     */
    public static function all(): array
    {
        return [
            self::market(),
            self::language(),
            self::media(),
            self::menu(),
            self::menuItem(),
            self::notification(),
            self::user(),
            self::seoFilter(),
            self::module(),
            self::token(),
        ];
    }

    private static function market(): CapabilityResource
    {
        return new CapabilityResource(
            mutationHandler: MarketMutationHandler::class,
            key: 'market',
            label: 'Mercado',
            model: Market::class,
            permission: 'manage:modules',
            description: 'Mercados multi-región: moneda, idioma por defecto y activación.',
            fillable: ['code', 'name', 'flag', 'currency', 'default_locale', 'fallback_market_id', 'is_default', 'is_active'],
            rules: [
                'code' => ['required', 'string', 'max:10'],
                'name' => ['required', 'string', 'max:120'],
                'currency' => ['nullable', 'string', 'max:8'],
                'default_locale' => ['nullable', 'string', 'max:10'],
            ],
            searchable: ['code', 'name', 'currency'],
            orderBy: 'code',
        );
    }

    private static function language(): CapabilityResource
    {
        return new CapabilityResource(
            mutationHandler: LanguageMutationHandler::class,
            key: 'language',
            label: 'Idioma',
            model: Language::class,
            permission: 'manage:modules',
            description: 'Idiomas activos del CMS y su dirección de lectura.',
            fillable: ['code', 'name', 'native_name', 'flag', 'direction', 'is_default', 'is_active'],
            rules: [
                'code' => ['required', 'string', 'max:10'],
                'name' => ['required', 'string', 'max:120'],
                'direction' => ['nullable', 'in:ltr,rtl'],
            ],
            searchable: ['code', 'name', 'native_name'],
            orderBy: 'code',
        );
    }

    private static function media(): CapabilityResource
    {
        return new CapabilityResource(
            key: 'media',
            label: 'Archivo de medios',
            model: MediaFile::class,
            permission: 'manage:media',
            description: 'Biblioteca de medios: título, texto alternativo y pie de foto.',
            fillable: ['title', 'alt_text', 'caption'],
            rules: [
                'title' => ['nullable', 'string', 'max:255'],
                'alt_text' => ['nullable', 'string', 'max:255'],
                'caption' => ['nullable', 'string', 'max:500'],
            ],
            searchable: ['filename', 'original_name', 'title', 'alt_text'],
            columns: ['id', 'filename', 'original_name', 'mime_type', 'size_bytes', 'title', 'alt_text', 'url'],
            allowCreate: false,
            orderBy: 'id',
            orderDirection: 'desc',
        );
    }

    private static function menu(): CapabilityResource
    {
        return new CapabilityResource(
            mutationHandler: MenuMutationHandler::class,
            key: 'menu',
            label: 'Menú de navegación',
            model: Menu::class,
            permission: 'manage:menus',
            description: 'Menús del front office (cabecera, pie…).',
            fillable: ['slug', 'name', 'location', 'is_active'],
            rules: [
                'slug' => ['required', 'string', 'max:80'],
                'name' => ['required', 'string', 'max:120'],
                'location' => ['nullable', 'string', 'max:60'],
            ],
            searchable: ['slug', 'name', 'location'],
            orderBy: 'name',
        );
    }

    private static function menuItem(): CapabilityResource
    {
        return new CapabilityResource(
            mutationHandler: MenuItemMutationHandler::class,
            key: 'menu_item',
            label: 'Elemento de menú',
            model: MenuItem::class,
            permission: 'manage:menus',
            description: 'Elementos de un menú: enlace interno, externo o ancla, con jerarquía y orden.',
            fillable: ['menu_id', 'parent_id', 'type', 'target_id', 'url', 'order', 'is_active', 'target_blank'],
            rules: [
                'menu_id' => ['required', 'integer', 'exists:menus,id'],
                'type' => ['required', 'string', 'max:40'],
                'url' => ['nullable', 'string', 'max:500'],
                'order' => ['nullable', 'integer', 'min:0'],
            ],
            searchable: ['url', 'type'],
            orderBy: 'order',
        );
    }

    private static function notification(): CapabilityResource
    {
        return new CapabilityResource(
            key: 'notification',
            label: 'Notificación del sistema',
            model: SystemNotification::class,
            permission: null,
            description: 'Avisos del sistema para el equipo (leer y marcar como leído).',
            fillable: ['is_read', 'read_at'],
            rules: [
                'is_read' => ['nullable', 'boolean'],
            ],
            searchable: ['title', 'module', 'type'],
            columns: ['id', 'module', 'type', 'title', 'is_read', 'read_at', 'created_at'],
            allowCreate: false,
            allowDelete: true,
            orderBy: 'id',
            orderDirection: 'desc',
            scope: static function (Builder $query): void {
                $id = Auth::id();
                $query->where('is_visible', true)->where(function (Builder $inner) use ($id): void {
                    $inner->where(fn (Builder $global) => $global->whereNull('user_id')->where('is_global', true));
                    if ($id !== null) {
                        $inner->orWhere('user_id', (int) $id);
                    }
                });
            },
            afterUpdate: static function (Model $model, array $attributes): void {
                if (array_key_exists('is_read', $attributes)) {
                    $model->setAttribute('read_at', $attributes['is_read'] ? now() : null);
                    $model->save();
                }
            },
        );
    }

    private static function user(): CapabilityResource
    {
        return new CapabilityResource(
            mutationHandler: UserMutationHandler::class,
            key: 'user',
            label: 'Usuario del panel',
            model: User::class,
            permission: 'manage:users',
            description: 'Cuentas del backoffice. Acepta "role_id" para asignar rol al crear o actualizar.',
            fillable: ['name', 'email', 'password', 'locale', 'role_id'],
            rules: [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:190'],
                'password' => ['nullable', 'string', 'min:8'],
                'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            ],
            searchable: ['name', 'email'],
            columns: ['id', 'name', 'email', 'locale', 'created_at'],
            orderBy: 'id',
        );
    }

    private static function seoFilter(): CapabilityResource
    {
        return new CapabilityResource(
            key: 'seo_filter',
            label: 'Landing de filtro (SEO)',
            model: FilterPage::class,
            permission: 'manage:seo',
            description: 'Filtros y búsquedas curadas: título, descripción e indexación en Google.',
            fillable: ['module', 'market_code', 'locale', 'signature', 'path', 'title', 'heading', 'meta_description', 'is_indexable'],
            rules: [
                'module' => ['required', 'string', 'max:50'],
                'locale' => ['required', 'string', 'max:10'],
                'path' => ['required', 'string', 'max:191'],
                'is_indexable' => ['nullable', 'boolean'],
            ],
            searchable: ['path', 'title', 'module'],
            orderBy: 'id',
            orderDirection: 'desc',
        );
    }

    private static function module(): CapabilityResource
    {
        return new CapabilityResource(
            key: 'module',
            label: 'Módulo del CMS',
            model: CmsModule::class,
            permission: 'manage:modules',
            description: 'Módulos instalados y su estado (lectura; usa las acciones de ciclo de vida para cambiarlo).',
            fillable: [],
            searchable: ['identifier', 'name'],
            columns: ['id', 'identifier', 'name', 'version', 'is_active', 'is_pageable'],
            allowCreate: false,
            allowUpdate: false,
            allowDelete: false,
            orderBy: 'identifier',
        );
    }

    private static function token(): CapabilityResource
    {
        return new CapabilityResource(
            key: 'token',
            label: 'Token OAuth2 / MCP',
            model: Token::class,
            permission: 'manage:users',
            description: 'Tokens emitidos (lectura; revocar es una acción).',
            fillable: [],
            searchable: ['name', 'id'],
            columns: ['id', 'name', 'user_id', 'client_id', 'scopes', 'revoked', 'created_at', 'expires_at'],
            allowCreate: false,
            allowUpdate: false,
            allowDelete: false,
            orderBy: 'id',
            orderDirection: 'desc',
        );
    }
}
