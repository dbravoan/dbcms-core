<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property list<string> $allowed_scopes */
final class OAuthServiceAccount extends Model
{
    protected $table = 'oauth_service_accounts';

    protected $primaryKey = 'client_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['client_id', 'user_id', 'allowed_scopes'];

    protected $casts = ['allowed_scopes' => 'array'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
