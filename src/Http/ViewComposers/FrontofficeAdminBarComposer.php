<?php

declare(strict_types=1);

namespace DbCms\Core\Http\ViewComposers;

use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Content\EntitySchema;
use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Contracts\Services\PublishedSectionTextInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Seo\ContentQualityAuditorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

final class FrontofficeAdminBarComposer
{
    public function __construct(
        private readonly EntitySchemaRegistryInterface $schemas,
        private readonly ModuleManager $modules,
        private readonly ContentQualityAuditorService $auditor,
        private readonly AdminThemeServiceInterface $themes,
        private readonly PublishedSectionTextInterface $sections,
    ) {}

    public function compose(View $view): void
    {
        $user = auth('web')->user();
        $canAccess = $user !== null && ($user->isAdmin() || $user->hasPermission('access:admin') || $user->roles()->exists());
        $view->with(['currentUser' => $user, 'canAccessBackoffice' => $canAccess]);
        if (! $canAccess) {
            return;
        }
        $brand = brand();
        $cssPath = $this->themes->getThemePath($brand->theme).'/assets/admin-bar.css';
        $context = ['contentEntity' => null, 'entityType' => null, 'editUrl' => route('admin.dashboard'), 'editLabel' => __('admin.nav.items.dashboard')];
        $title = $brand->name;
        $description = '';
        $body = '';
        foreach ($this->schemas->all() as $schema) {
            if ($schema->editView === null || ! Route::has($schema->editView) || ($schema->ownerModule !== null && ! $this->modules->isModuleEnabled($schema->ownerModule)) || ($schema->permission !== null && ! $user->can($schema->permission))) {
                continue;
            }
            foreach ($view->getData() as $value) {
                if (! $value instanceof Model || ! is_a($value, $schema->model)) {
                    continue;
                }
                $context = ['contentEntity' => $value, 'entityType' => $schema->type, 'editUrl' => route($schema->editView, $value->getKey()), 'editLabel' => 'Editar '.$schema->label];
                $columns = $schema->translationAttributes();
                $translation = $value->getRelationValue('translations')->firstWhere('locale', app()->getLocale());
                $title = (string) ($translation?->getAttribute($columns[EntitySchema::ROLE_META_TITLE] ?? 'meta_title') ?: $translation?->getAttribute($columns[EntitySchema::ROLE_TITLE] ?? 'title'));
                $description = (string) $translation?->getAttribute($columns[EntitySchema::ROLE_META_DESCRIPTION] ?? 'meta_description');
                $body = isset($columns[EntitySchema::ROLE_BODY]) ? (string) $translation?->getAttribute($columns[EntitySchema::ROLE_BODY]) : '';
                $market = app()->bound('current.market') ? app('current.market') : null;
                if ($market instanceof Market) {
                    $body .= "\n".$this->sections->text($value, $market, app()->getLocale());
                }
                break 2;
            }
        }
        $audit = $this->auditor->audit($title, $body, $description);
        $score = $audit['overall_score'];
        $view->with($context + [
            'currentRoleName' => $user->roles->first()->name ?? 'Backoffice',
            'adminBarCssUrl' => is_file($cssPath) ? theme_asset('admin-bar.css', $brand->theme) : null,
            'brandLogo' => $brand->logo ? theme_asset($brand->logo) : null,
            'brandIcon' => $brand->icon ? theme_asset($brand->icon) : null,
            'brandAlt' => $brand->name,
            'contentTitle' => $title,
            'auditResult' => $audit,
            'score' => $score,
            'scoreBadgeColor' => $score >= 80 ? 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30' : ($score >= 60 ? 'text-amber-400 bg-amber-500/10 border-amber-500/30' : 'text-rose-400 bg-rose-500/10 border-rose-500/30'),
            'scoreDotColor' => $score >= 80 ? 'bg-emerald-400' : ($score >= 60 ? 'bg-amber-400' : 'bg-rose-400'),
        ]);
    }
}
