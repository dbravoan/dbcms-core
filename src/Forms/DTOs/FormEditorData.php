<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\DTOs;

use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Services\FormWebhookPolicy;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use Illuminate\Database\Eloquent\Collection;

final readonly class FormEditorData
{
    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, array<string, string>>  $translationsMap
     * @param  Collection<int, Language>  $languages
     * @param  Collection<int, Market>  $markets
     * @param  list<int>  $assignedMarketIds
     * @param  list<array<string, mixed>>  $fieldTypes
     */
    public function __construct(
        public CustomForm $form,
        public array $fields,
        public array $translationsMap,
        public Collection $languages,
        public Collection $markets,
        public array $assignedMarketIds,
        public array $fieldTypes,
    ) {}

    /** @return array<string, mixed> */
    public function viewData(): array
    {
        $this->form->loadDefinitionAttributes();

        return get_object_vars($this) + [
            'composition' => $this->form->layout,
            'formActions' => $this->form->actions ?? [],
            'webhookTargets' => app(FormWebhookPolicy::class)->targets(),
        ];
    }
}
