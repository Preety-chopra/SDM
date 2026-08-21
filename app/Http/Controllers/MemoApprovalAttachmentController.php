<?php

namespace App\Http\Controllers;

use App\Models\MemoApprovalAttachment;
use App\Models\ServiceDeficiencyMemo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemoApprovalAttachmentController extends Controller
{
    public function download(ServiceDeficiencyMemo $sdmMemo, MemoApprovalAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $sdmMemo);
        abort_unless($attachment->approval()->where('memo_id', $sdmMemo->id)->exists(), 404);

        return Storage::disk('local')->download($attachment->storage_path, $attachment->original_name);
    }
}
