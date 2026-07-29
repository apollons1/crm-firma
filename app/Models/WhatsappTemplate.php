<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'twilio_content_sid',
        'body',
        'variables_count',
        'category',
        'language',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'variables_count' => 'integer',
        ];
    }

    /**
     * Înlocuiește {{1}}, {{2}}, ... din body cu valorile date, pentru
     * un preview/log lizibil al mesajului efectiv trimis.
     *
     * @param  array<int|string, string>  $variables
     */
    public function renderBody(array $variables): string
    {
        $rendered = $this->body;

        foreach ($variables as $key => $value) {
            $rendered = str_replace('{{'.$key.'}}', $value, $rendered);
        }

        return $rendered;
    }
}
