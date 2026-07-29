<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Store generic key-value pentru configurarea automatizărilor WhatsApp
 * (activare, template mesaj, praguri etc.) — vezi App\Filament\Pages\AutomationSettings.
 */
class AutomationSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Valorile se salvează JSON-encodate, ca să poată reprezenta uniform
     * bool/int/string/array (ex: enabled=true, message_template="...", zile=14).
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        return json_decode($value, true);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value)]
        );
    }
}
