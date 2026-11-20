<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Services;

use DbCms\Core\Content\Contracts\EditorialPreviewHandlerInterface;
use DbCms\Core\DTOs\Front\FrontPageData;
use DbCms\Core\DTOs\Front\PublicContentContext;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\FormField;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Database\Eloquent\Model;

final class FormEditorialPreview implements EditorialPreviewHandlerInterface
{
    public function __construct(private readonly FormPresentationService $presentation) {}

    public function render(Model $entity, PublicContentContext $context): FrontPageData
    {
        if (! $entity instanceof CustomForm) {
            return FrontPageData::missing();
        }
        $translation = PublicTranslation::resolve($entity, $context->locale, $context->market);
        if ($translation === null) {
            return FrontPageData::missing();
        }
        $form = clone $entity;
        $form->setRelation('translations', $entity->translations()->getRelated()->newCollection([$translation]));
        $fields = $entity->getRelation('fields')->map(function (FormField $field) use ($context): FormField {
            $copy = clone $field;
            $translation = PublicTranslation::resolve($field, $context->locale, $context->market);
            $copy->setRelation('translations', $field->translations()->getRelated()->newCollection($translation === null ? [] : [$translation]));

            return $copy;
        });
        $form->setRelation('fields', $fields);
        $data = $this->presentation->data(['form' => $form, 'market' => $context->market, 'locale' => $context->locale]);

        return FrontPageData::view('forms::preview', $data + ['currentMarket' => $context->market, 'currentLocale' => $context->locale]);
    }
}
