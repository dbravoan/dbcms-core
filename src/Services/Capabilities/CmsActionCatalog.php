<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Capabilities\Tools\Action\ActionTool;
use DbCms\Core\Services\Media\MediaUploadService;
use DbCms\Core\Services\Seo\FilterLandingActions;

/**
 * Catalog of the non-CRUD capabilities: platform lifecycle, caches, editorial
 * workflow, block sections, media, tokens and the agent audit trail.
 *
 * Every entry declares the admin permission and the OAuth ability it needs, so
 * the surface stays auditable in one file.
 */
final class CmsActionCatalog
{
    public function __construct(
        private readonly PlatformAdministrationActions $platform,
        private readonly ContentSectionActions $sections,
        private readonly AccountActions $accounts,
        private readonly MediaUploadService $media,
        private readonly FilterLandingActions $seo,
    ) {}

    /**
     * @return list<ActionTool>
     */
    public function all(): array
    {
        return array_merge(
            self::capabilities($this->platform),
            self::moduleLifecycle($this->platform),
            self::themes($this->platform),
            self::settings($this->platform),
            self::connection($this->platform),
            self::cache($this->platform),
            self::sections($this->sections),
            self::media($this->media),
            self::notifications($this->accounts),
            self::tokens($this->accounts),
            self::auditTrail($this->accounts),
            self::seo($this->seo),
        );
    }

    /**
     * @return list<ActionTool>
     */
    private static function capabilities(PlatformAdministrationActions $service): array
    {
        return [
            new ActionTool(
                toolName: 'cms_capabilities',
                toolDescription: 'Describes the available capabilities, entity types and platform resources authorized for the current actor, with their accepted arguments. Call it first to plan',
                toolProperties: [],
                toolRequired: [],
                toolPermission: null,
                toolAbility: ActionTool::ABILITY_READ,
                handler: static fn (array $args): array => $service->capabilities(),
            ),
        ];
    }

    /**
     * @return list<ActionTool>
     */
    private static function moduleLifecycle(PlatformAdministrationActions $service): array
    {
        $identifier = ['type' => 'string', 'description' => 'Module identifier, e.g. shop, blog, mcp'];

        return [
            new ActionTool(
                toolName: 'cms_module_toggle',
                toolDescription: 'MCP: activates or deactivates an installed module (the master switch of every feature). Without "enabled" it flips the current state',
                toolProperties: [
                    'identifier' => $identifier,
                    'enabled' => ['type' => 'boolean', 'description' => 'true to activate, false to deactivate; omit to toggle'],
                ],
                toolRequired: ['identifier'],
                toolPermission: 'manage:modules',
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->moduleToggle($args),
            ),
            new ActionTool(
                toolName: 'cms_module_install',
                toolDescription: 'MCP: installs a discovered module (runs its migrations) and activates it',
                toolProperties: ['identifier' => $identifier],
                toolRequired: ['identifier'],
                toolPermission: 'manage:modules',
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->moduleInstall((string) ($args['identifier'] ?? '')),
            ),
            new ActionTool(
                toolName: 'cms_module_uninstall',
                toolDescription: 'MCP: uninstalls a module. Requires review and human approval in the backoffice',
                toolProperties: ['identifier' => $identifier],
                toolRequired: ['identifier'],
                toolPermission: 'manage:modules',
                toolAbility: ActionTool::ABILITY_DESTRUCTIVE,
                handler: static fn (array $args): array => $service->moduleUninstall((string) ($args['identifier'] ?? '')),
            ),
            new ActionTool(
                toolName: 'cms_module_settings_update',
                toolDescription: 'MCP: updates the settings of a module (honouring its declared schema), e.g. the blog slug, comments or the MCP scopes',
                toolProperties: [
                    'identifier' => $identifier,
                    'settings' => ['type' => 'object', 'description' => 'Settings map, e.g. {"comments_enabled": true, "slug_fr": "nouvelles"}'],
                ],
                toolRequired: ['identifier', 'settings'],
                toolPermission: 'manage:modules',
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->moduleSettings(
                    (string) ($args['identifier'] ?? ''),
                    is_array($args['settings'] ?? null) ? $args['settings'] : [],
                ),
            ),
        ];
    }

    /**
     * @return list<ActionTool>
     */
    private static function themes(PlatformAdministrationActions $service): array
    {
        return [
            new ActionTool(
                toolName: 'cms_theme_activate',
                toolDescription: 'MCP: activates a front-office theme by its directory name (db-cyber, db-minimal…)',
                toolProperties: ['theme' => ['type' => 'string', 'description' => 'Theme identifier = directory under themes/']],
                toolRequired: ['theme'],
                toolPermission: 'manage:themes',
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->themeActivate((string) ($args['theme'] ?? '')),
            ),
        ];
    }

    /**
     * @return list<ActionTool>
     */
    private static function settings(PlatformAdministrationActions $service): array
    {
        return [
            new ActionTool(
                toolName: 'cms_settings_get',
                toolDescription: 'MCP: reads the site settings (brand, contact, social, SEO defaults…). Secret values are redacted',
                toolProperties: [],
                toolRequired: [],
                toolPermission: 'manage:modules',
                toolAbility: ActionTool::ABILITY_READ,
                handler: static fn (array $args): array => $service->settingsGet(),
            ),
            new ActionTool(
                toolName: 'cms_settings_update',
                toolDescription: 'MCP: updates site settings; send only the keys that change',
                toolProperties: ['attributes' => ['type' => 'object', 'description' => 'Setting key → value']],
                toolRequired: ['attributes'],
                toolPermission: 'manage:modules',
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->settingsUpdate(
                    is_array($args['attributes'] ?? null) ? $args['attributes'] : [],
                ),
            ),
        ];
    }

    /**
     * @return list<ActionTool>
     */
    private static function connection(PlatformAdministrationActions $service): array
    {
        return [
            new ActionTool(
                toolName: 'cms_connection_get',
                toolDescription: 'MCP: reads the external storage (S3-compatible) configuration. Secrets are redacted',
                toolProperties: [],
                toolRequired: [],
                toolPermission: 'manage:storage',
                toolAbility: ActionTool::ABILITY_READ,
                handler: static fn (array $args): array => $service->connectionGet(),
            ),
            new ActionTool(
                toolName: 'cms_connection_update',
                toolDescription: 'MCP: updates the external storage connection (provider, bucket, keys…)',
                toolProperties: ['attributes' => ['type' => 'object', 'description' => 'Connection attributes']],
                toolRequired: ['attributes'],
                toolPermission: 'manage:storage',
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->connectionUpdate(
                    is_array($args['attributes'] ?? null) ? $args['attributes'] : [],
                ),
            ),
            new ActionTool(
                toolName: 'cms_connection_test',
                toolDescription: 'MCP: tests the external storage connection and reports the result',
                toolProperties: [],
                toolRequired: [],
                toolPermission: 'manage:storage',
                toolAbility: ActionTool::ABILITY_READ,
                handler: static fn (array $args): array => $service->connectionTest(),
            ),
        ];
    }

    /**
     * @return list<ActionTool>
     */
    private static function cache(PlatformAdministrationActions $service): array
    {
        return [
            new ActionTool(
                toolName: 'cms_cache_purge',
                toolDescription: 'MCP: purges the application and public page caches (the backoffice button for when a change does not show up). Requires review and human approval in the backoffice',
                toolProperties: [],
                toolRequired: [],
                toolPermission: 'manage:modules',
                toolAbility: ActionTool::ABILITY_DESTRUCTIVE,
                handler: static fn (array $args): array => $service->cachePurge(),
            ),
        ];
    }

    /**
     * @return list<ActionTool>
     */
    private static function sections(ContentSectionActions $service): array
    {
        $entity = [
            'type' => ['type' => 'string', 'description' => 'Entity type with blocks: page, post, product'],
            'id' => ['type' => ['integer', 'string'], 'description' => 'Entity id'],
        ];

        return [
            new ActionTool(
                toolName: 'cms_content_sections_get',
                toolDescription: 'MCP: reads the blocks of a sectionable entity (page, post, product) with their per-locale content and styling',
                toolProperties: $entity,
                toolRequired: ['type', 'id'],
                toolPermission: null,
                toolAbility: ActionTool::ABILITY_READ,
                handler: static fn (array $args): array => $service->sectionsGet($args),
                permissionResolver: static fn (array $args): ?string => empty($args['type']) ? null : app(EntityReadService::class)->schemaFor((string) $args['type'])->permission,
            ),
            new ActionTool(
                toolName: 'cms_content_sections_sync',
                toolDescription: 'MCP: replaces the blocks through the entity publication workflow. Accepts the block-manager shape (locale → list of blocks); each block carries "type" plus its content',
                toolProperties: $entity + [
                    'sections' => ['type' => ['array', 'object'], 'description' => 'Locale-keyed lists of blocks; an empty object or array clears the sections'],
                ],
                toolRequired: ['type', 'id', 'sections'],
                toolPermission: null,
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->sectionsSync($args),
                permissionResolver: static fn (array $args): ?string => empty($args['type']) ? null : app(EntityReadService::class)->schemaFor((string) $args['type'])->permission,
            ),
        ];
    }

    /**
     * @return list<ActionTool>
     */
    private static function media(MediaUploadService $service): array
    {
        return [
            new ActionTool(
                toolName: 'cms_media_import',
                toolDescription: 'Imports an image from a public URL into the configured media library, without publishing content.',
                toolProperties: [
                    'url' => ['type' => 'string', 'maxLength' => 2048],
                    'title' => ['type' => 'string', 'maxLength' => 255],
                    'alt_text' => ['type' => 'string', 'maxLength' => 2000],
                ],
                toolRequired: ['url'],
                toolPermission: 'manage:media',
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->importImage($args),
            ),
            new ActionTool(
                toolName: 'cms_media_upload',
                toolDescription: 'MCP: uploads a file to the media library from base64 content, honouring the same type/size rules as the backoffice',
                toolProperties: [
                    'filename' => ['type' => 'string', 'description' => 'Original file name with extension'],
                    'content_base64' => ['type' => 'string', 'maxLength' => 69905068, 'description' => 'Base64-encoded file content (maximum decoded size: 50 MB)'],
                ],
                toolRequired: ['filename', 'content_base64'],
                toolPermission: 'manage:media',
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->mediaUpload($args),
            ),
        ];
    }

    /**
     * @return list<ActionTool>
     */
    private static function notifications(AccountActions $service): array
    {
        return [
            new ActionTool(
                toolName: 'cms_notifications_mark_all_read',
                toolDescription: 'MCP: marks every system notification as read',
                toolProperties: [],
                toolRequired: [],
                toolPermission: null,
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->notificationsMarkAllRead(),
            ),
        ];
    }

    /**
     * @return list<ActionTool>
     */
    private static function tokens(AccountActions $service): array
    {
        return [
            new ActionTool(
                toolName: 'cms_token_revoke',
                toolDescription: 'MCP: revokes an OAuth2 token by id (read them with cms_resource_list resource=token). Requires review and human approval in the backoffice',
                toolProperties: ['id' => ['type' => 'string', 'description' => 'Token id']],
                toolRequired: ['id'],
                toolPermission: 'manage:users',
                toolAbility: ActionTool::ABILITY_DESTRUCTIVE,
                handler: static fn (array $args): array => $service->tokenRevoke((string) ($args['id'] ?? '')),
            ),
        ];
    }

    /**
     * @return list<ActionTool>
     */
    private static function auditTrail(AccountActions $service): array
    {
        return [
            new ActionTool(
                toolName: 'cms_mcp_audit',
                toolDescription: 'MCP: reads the agent audit trail (who called what, with which arguments and result) plus usage statistics',
                toolProperties: [
                    'limit' => ['type' => 'integer', 'description' => 'Entries to return (default 20, max 200)'],
                    'tool' => ['type' => 'string', 'description' => 'Filter by capability name'],
                    'problems_only' => ['type' => 'boolean', 'description' => 'Only denials and errors'],
                ],
                toolRequired: [],
                toolPermission: 'manage:modules',
                toolAbility: ActionTool::ABILITY_READ,
                handler: static fn (array $args): array => $service->auditLog($args),
            ),
        ];
    }

    /**
     * @return list<ActionTool>
     */
    private static function seo(FilterLandingActions $service): array
    {
        return [
            new ActionTool(
                toolName: 'cms_seo_filter_upsert',
                toolDescription: 'MCP: curates a filter landing from its friendly path (the "Indexar en Google" button): sets title, heading, description and whether Google may index it',
                toolProperties: [
                    'module' => ['type' => 'string', 'description' => 'Filterable module: blog, shop'],
                    'path' => ['type' => 'string', 'description' => 'Friendly filter path, e.g. tecnologia/recientes'],
                    'market' => ['type' => 'string', 'description' => 'Market code (default: primary market)'],
                    'locale' => ['type' => 'string', 'description' => 'Locale code'],
                    'title' => ['type' => 'string', 'description' => 'SEO title'],
                    'heading' => ['type' => 'string', 'description' => 'On-page heading'],
                    'meta_description' => ['type' => 'string', 'description' => 'Meta description'],
                    'is_indexable' => ['type' => 'boolean', 'description' => 'Whether Google may index it (default true)'],
                ],
                toolRequired: ['module', 'path'],
                toolPermission: 'manage:seo',
                toolAbility: ActionTool::ABILITY_WRITE,
                handler: static fn (array $args): array => $service->seoFilterUpsert($args),
            ),
        ];
    }
}
