<?php

namespace App\Http\Controllers;

use App\Models\MemoObjection;
use App\Models\MemoPenaltyLetter;
use App\Models\ServiceDeficiencyMemo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemoPrivateDocumentController extends Controller
{
    public function acknowledgement(ServiceDeficiencyMemo $sdmMemo): StreamedResponse
    {
        Gate::authorize('view', $sdmMemo);
        $document = $sdmMemo->staffAcknowledgement()->firstOrFail();

        return Storage::disk('local')->download($document->proof_path, $document->original_name);
    }

    public function letter(ServiceDeficiencyMemo $sdmMemo, MemoPenaltyLetter $letter): StreamedResponse
    {
        Gate::authorize('view', $sdmMemo);
        abort_unless($letter->memo_id === $sdmMemo->id, 404);

        return Storage::disk('local')->download($letter->storage_path, $letter->original_name);
    }

    public function objection(ServiceDeficiencyMemo $sdmMemo, MemoObjection $objection): StreamedResponse
    {
        Gate::authorize('view', $sdmMemo);
        abort_unless($objection->memo_id === $sdmMemo->id && filled($objection->storage_path), 404);

        return Storage::disk('local')->download($objection->storage_path, $objection->original_name);
    }
}
