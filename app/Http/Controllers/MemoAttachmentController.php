<?php

namespace App\Http\Controllers;

use App\Models\MemoAttachment;
use App\Models\ServiceDeficiencyMemo;
use App\Services\ServiceDeficiencyMemoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemoAttachmentController extends Controller
{
    public function __construct(private ServiceDeficiencyMemoService $memos) {}

    public function download(ServiceDeficiencyMemo $sdmMemo, MemoAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $sdmMemo);
        abort_unless($attachment->memo_id === $sdmMemo->id, 404);

        return Storage::disk('local')->download($attachment->storage_path, $attachment->original_name);
    }

    public function destroy(ServiceDeficiencyMemo $sdmMemo, MemoAttachment $attachment): RedirectResponse
    {
        Gate::authorize('update', $sdmMemo);
        abort_unless($attachment->memo_id === $sdmMemo->id, 404);
        $this->memos->deleteAttachment($attachment);

        return back()->with('status', 'Attachment removed.');
    }
}
