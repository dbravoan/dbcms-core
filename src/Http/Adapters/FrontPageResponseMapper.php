<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Adapters;

use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\DTOs\Front\FrontPageData;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

final class FrontPageResponseMapper
{
    public function respond(FrontPageData $page): View|RedirectResponse
    {
        if ($page->redirectUrl !== null) {
            return redirect()->to($page->redirectUrl, $page->redirectStatus)->with($page->data);
        }

        return $this->view($page);
    }

    public function view(FrontPageData $page): View
    {
        abort_if($page->viewName === null, 404);
        $schemas = app(EntitySchemaRegistryInterface::class)->all();
        foreach ($page->data as $value) {
            if (! $value instanceof Model) {
                continue;
            }
            foreach ($schemas as $schema) {
                if ($schema->publicContent !== null && $value instanceof $schema->model) {
                    request()->attributes->set('cms.public.entity_type', $schema->type);
                    request()->attributes->set('cms.public.entity_id', $value->getKey());
                    $taxonomies = [];
                    foreach (['category', 'categories', 'tags'] as $relation) {
                        if (! $value->relationLoaded($relation)) {
                            continue;
                        }
                        $items = $value->getRelation($relation);
                        foreach ($items instanceof Model ? [$items] : ($items ?? []) as $item) {
                            foreach ($schemas as $taxonomy) {
                                if ($item instanceof Model && $item instanceof $taxonomy->model && $taxonomy->publicContent !== null) {
                                    $taxonomies[$taxonomy->type][] = $item->getKey();
                                }
                            }
                        }
                    }
                    request()->attributes->set('cms.public.taxonomies', $taxonomies);
                    break 2;
                }
            }
        }

        return view($page->viewName, $page->data);
    }
}
