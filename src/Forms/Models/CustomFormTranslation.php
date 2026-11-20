<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $custom_form_id
 * @property string $locale
 * @property int|null $market_id
 * @property string $name
 * @property string|null $description
 * @property string $submit_button_text
 * @property string|null $success_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomFormTranslation extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'custom_form_id',
        'locale',
        'market_id',
        'name',
        'description',
        'submit_button_text',
        'success_message',
    ];

    /**
     * @return BelongsTo<CustomForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }
}
