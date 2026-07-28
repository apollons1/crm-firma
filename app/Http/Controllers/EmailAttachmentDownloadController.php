<?php

namespace App\Http\Controllers;

use App\Models\EmailAttachment;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailAttachmentDownloadController extends Controller
{
    public function __invoke(EmailAttachment $emailAttachment): StreamedResponse
    {
        // Un atașament e vizibil doar dacă emailul-părinte e vizibil pentru
        // userul curent (același scope ca lista de emailuri din CRM).
        abort_unless(
            EmailLog::query()->visibleTo(auth()->user())->whereKey($emailAttachment->email_log_id)->exists(),
            403
        );

        abort_unless(Storage::disk('public')->exists($emailAttachment->storage_path), 404);

        return Storage::disk('public')->download($emailAttachment->storage_path, $emailAttachment->filename);
    }
}
