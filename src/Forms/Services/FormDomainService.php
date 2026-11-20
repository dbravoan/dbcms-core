<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Services;

use DbCms\Core\Forms\FieldTypeRegistry;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\FormSubmission;
use DbCms\Core\Forms\SchemaFactory;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Content\PublicTranslation;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FormDomainService
{
    public function __construct(
        private readonly FieldTypeRegistry $fieldTypes,
        private readonly SchemaFactory $schemaFactory,
    ) {}

    /**
     * Validates dynamic user submission against form schema, verifies honeypot, and persists payload.
     *
     * @param  array<string, mixed>  $rawInput
     */
    public function submitForm(CustomForm $form, array $rawInput, ?Market $market = null, ?string $locale = null): FormSubmission
    {
        app(LegalPageService::class)->requireProcessing('forms');
        $form->loadDefinitionAttributes();
        if (! $form->is_active
            || ($market === null && $form->markets()->exists())
            || ($market !== null && (! $market->is_active || ($locale !== null && ! $market->languages()->where('code', $locale)->where('is_active', true)->exists())))
            || ($market !== null && $form->markets()->exists() && ! $form->markets()->whereKey($market->id)->exists())) {
            throw ValidationException::withMessages(['form' => 'Formulario no disponible.']);
        }

        if ($locale !== null && $form->translation($locale, $market?->id) === null) {
            throw ValidationException::withMessages(['form' => 'Formulario no disponible en este idioma.']);
        }

        // 1. Anti-spam honeypot verification
        if (! empty($rawInput['_hp_check'] ?? '')) {
            throw ValidationException::withMessages(['spam' => 'Spam bot submission blocked.']);
        }

        // Normalize multilingual/alias keys (e.g. name <-> nombre, message <-> mensaje)
        if (isset($rawInput['name']) && ! isset($rawInput['nombre'])) {
            $rawInput['nombre'] = $rawInput['name'];
        }
        if (isset($rawInput['nombre']) && ! isset($rawInput['name'])) {
            $rawInput['name'] = $rawInput['nombre'];
        }
        if (isset($rawInput['message']) && ! isset($rawInput['mensaje'])) {
            $rawInput['mensaje'] = $rawInput['message'];
        }
        if (isset($rawInput['mensaje']) && ! isset($rawInput['message'])) {
            $rawInput['message'] = $rawInput['mensaje'];
        }

        // 2. Compile dynamic validation rules from the registered field types.
        //    A type declared by a disabled module resolves to null and the whole
        //    field (rules included) is ignored — never silently degraded.
        $rules = [];
        $transient = [];

        foreach ($this->schemaFactory->fromModels($form->fields) as $field) {
            $type = $this->fieldTypes->resolve($field->type);

            if ($type === null) {
                continue;
            }

            foreach ($type->rules($field) as $input => $inputRules) {
                $rules[$input] = $inputRules;

                if (! $type->stores()) {
                    $transient[] = $input;
                }
            }
        }

        $validator = Validator::make($rawInput, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = (array) $validator->validated();

        // Anti-spam inputs are validated but never persisted.
        foreach (array_unique($transient) as $input) {
            unset($validatedData[$input]);
        }

        $paths = [];
        try {
            return DB::transaction(function () use ($form, $validatedData, $market, $locale, &$paths): FormSubmission {
                $uploads = [];
                foreach ($validatedData as $key => $value) {
                    if ($value instanceof UploadedFile) {
                        $uploads[$key] = $value;
                        unset($validatedData[$key]);
                    }
                }
                $submission = FormSubmission::create([
                    'custom_form_id' => $form->id,
                    'payload' => $validatedData,
                    'locale' => $locale ?? app()->getLocale(),
                    'market_id' => $market?->id,
                    'ip_address' => config('privacy.forms.collect_network_metadata', false) ? request()->ip() : null,
                    'user_agent' => config('privacy.forms.collect_network_metadata', false) ? request()->userAgent() : null,
                    'is_reviewed' => false,
                ]);
                foreach ($uploads as $key => $upload) {
                    $path = $upload->store('forms/'.$submission->id, 'local');
                    if (! is_string($path)) {
                        throw new \RuntimeException('Could not store the private form attachment.');
                    }
                    $paths[] = $path;
                    $attachment = $submission->attachments()->create([
                        'field_key' => $key, 'path' => $path,
                        'filename' => mb_substr(preg_replace('/[\x00-\x1f\x7f]/', '_', basename(str_replace('\\', '/', $upload->getClientOriginalName()))) ?? 'attachment', 0, 255),
                        'mime_type' => $upload->getMimeType() ?? 'application/octet-stream',
                        'size' => $upload->getSize(),
                    ]);
                    // Payload carries only an owned reference; no storage path or public URL.
                    $validatedData[$key] = ['attachment_id' => $attachment->id, 'filename' => $attachment->filename];
                }
                $submission->update(['payload' => $validatedData]);
                app(FormActionService::class)->record($form, $submission, $form->actions ?? []);

                return $submission;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($paths);
            throw $error;
        }
    }

    /**
     * Resolves an active form assigned to the given market (or global) by slug.
     */
    public function getFormBySlug(string $slug, ?Market $market = null): ?CustomForm
    {
        return $this->publicQuery($market)->where('slug', $slug)->first();
    }

    /** @return Collection<int, CustomForm> */
    public function getPublicForms(Market $market, string $locale): Collection
    {
        return $this->publicFormsQuery($market, $locale)->orderBy('id')->get();
    }

    /** @return Builder<CustomForm> */
    public function publicFormsQuery(Market $market, string $locale): Builder
    {
        $query = $this->publicQuery($market);
        if (! $market->is_active
            || ! $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
            return $query->whereRaw('1 = 0');
        }
        PublicTranslation::constrainResolvedField($query, $market, $locale, 'name', 'like', '%');

        return $query;
    }

    /** @return Builder<CustomForm> */
    private function publicQuery(?Market $market): Builder
    {
        $marketId = $market?->id;

        return CustomForm::query()
            ->when($market !== null && ! $market->is_active, static fn ($query) => $query->whereRaw('1 = 0'))
            ->when($market === null, static fn ($query) => $query->whereDoesntHave('markets'))
            ->where('is_active', true)
            ->when($marketId !== null, function ($query) use ($marketId): void {
                $query->where(function ($nested) use ($marketId): void {
                    $nested->whereHas('markets', static fn ($markets) => $markets->where('markets.id', $marketId))
                        ->orWhereDoesntHave('markets');
                });
            })
            ->with(['translations', 'markets', 'fields.translations']);
    }
}
