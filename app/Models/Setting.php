<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One stored setting. Read through App\Support\SiteSettings rather than
 * directly — that is where the defaults and the caching live.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];
}
