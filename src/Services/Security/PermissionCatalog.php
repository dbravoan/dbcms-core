<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Security;

use Illuminate\Support\Str;

/**
 * Canonical catalogue of CMS permissions and the default role matrix.
 *
 * Core permissions live here. Modules may declare additional permissions in
 * their Composer metadata (`extra.dbcms.permissions`) or `module.json`; the
 * provisioner merges those declarations so every permission used by a route,
 * Gate or capability exists in the database.
 */
final class PermissionCatalog
{
    /**
     * Core permission slug => human readable name.
     *
     * @return array<string, string>
     */
    public static function core(): array
    {
        return [
            'access:admin' => 'Acceder al panel de administración',
            'manage:users' => 'Gestionar usuarios y roles',
            'manage:tokens' => 'Emitir y revocar tokens de acceso y clientes M2M',
            'manage:modules' => 'Gestionar módulos',
            'manage:blocks' => 'Gestionar paquetes de bloques',
            'manage:themes' => 'Gestionar plantillas y temas',
            'manage:pages' => 'Gestionar páginas',
            'manage:posts' => 'Gestionar artículos y categorías',
            'manage:content' => 'Gestionar contenido',
            'manage:media' => 'Gestionar archivos multimedia',
            'manage:galleries' => 'Gestionar galerías',
            'manage:menus' => 'Gestionar menús y zonas',
            'manage:forms' => 'Gestionar formularios y contactos',
            'manage:shop' => 'Gestionar tienda y catálogo',
            'manage:seo' => 'Gestionar SEO y filtros',
            'manage:storage' => 'Gestionar almacenamiento externo',
            'use:ai' => 'Utilizar las capacidades IA autorizadas',
            'manage:ai' => 'Administrar proveedores y configuración IA',
            'manage:creative-studio' => 'Gestionar Creative Studio',
            'read:social-publishing' => 'Leer borradores y resultados de publicación social',
            'manage:social-publishing' => 'Gestionar publicaciones sociales',
            'approve:social-publishing' => 'Aprobar contenido social',
            'manage:social-accounts' => 'Gestionar cuentas sociales autorizadas',
            'audit:approve' => 'Aprobar revisiones editoriales',
        ];
    }

    /**
     * Merge core permissions with the permissions declared by installed modules.
     *
     * @param  array<string, array<string, mixed>>  $modules
     * @return array<string, string>
     */
    public static function definitions(array $modules = []): array
    {
        $definitions = self::core();

        foreach ($modules as $module) {
            foreach ((array) ($module['permissions'] ?? []) as $permission) {
                if (! is_string($permission) || preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-z0-9_-]*$/D', $permission) !== 1) {
                    continue;
                }

                $definitions[$permission] ??= Str::headline(str_replace([':', '_', '-'], ' ', $permission));
            }
        }

        ksort($definitions);

        return $definitions;
    }

    /**
     * Default role matrix used by a clean installation.
     *
     * The `viewer` role is intentionally dashboard-only until read permissions
     * exist: it may enter the backoffice, but every manage:* route still
     * requires its explicit permission.
     *
     * @param  array<string, string>|null  $definitions
     * @return array<string, array{name: string, description: string, permissions: list<string>}>
     */
    public static function roles(?array $definitions = null): array
    {
        $definitions ??= self::core();
        $all = array_keys($definitions);

        return [
            'admin' => [
                'name' => 'Administrador',
                'description' => 'Acceso administrativo total',
                'permissions' => $all,
            ],
            'editor' => [
                'name' => 'Editor de contenido',
                'description' => 'Gestión de artículos, páginas, medios y menús',
                'permissions' => [
                    'access:admin', 'manage:pages', 'manage:posts', 'manage:content',
                    'manage:media', 'manage:galleries', 'manage:menus', 'manage:seo', 'use:ai',
                ],
            ],
            'author' => [
                'name' => 'Autor o redactor',
                'description' => 'Creación de artículos para el blog',
                'permissions' => [
                    'access:admin', 'manage:posts', 'manage:media', 'manage:galleries', 'use:ai',
                ],
            ],
            'marketing' => [
                'name' => 'Marketing y captación',
                'description' => 'Formularios, contactos y auditoría SEO',
                'permissions' => [
                    'access:admin', 'manage:forms', 'manage:seo', 'manage:media', 'manage:galleries', 'use:ai',
                ],
            ],
            'viewer' => [
                'name' => 'Lector (auditor)',
                'description' => 'Acceso de solo lectura al panel; sin permisos de gestión',
                'permissions' => ['access:admin'],
            ],
        ];
    }
}
