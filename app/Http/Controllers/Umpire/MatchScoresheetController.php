<?php

namespace App\Http\Controllers\Umpire;

use App\Http\Controllers\Controller;
use App\Models\Matches;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MatchScoresheetController extends Controller
{
    /**
     * A print-friendly view of the match's rally score sheet.
     */
    public function show(Matches $match): View|RedirectResponse
    {
        $this->authorizeUmpire($match);

        return view('umpire.scoresheet', ['match' => $match]);
    }

    /**
     * The same score sheet, served as an editable Word document (plain
     * HTML with a .doc extension and Word's MIME type — Word opens and
     * edits this natively without needing a document-generation library).
     */
    public function download(Matches $match): Response
    {
        $this->authorizeUmpire($match);

        $html = view('umpire.scoresheet-doc', ['match' => $match])->render();

        return response($html, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Match-'.$match->id.'-Scoresheet.doc"',
        ]);
    }

    private function authorizeUmpire(Matches $match): void
    {
        abort_unless(auth()->user()?->isUmpire(), 403);
        abort_unless($match->assigned_umpire_id === auth()->id(), 403);
    }
}
