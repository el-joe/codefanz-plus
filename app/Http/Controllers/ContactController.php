<?php

namespace App\Http\Controllers;

use App\Actions\Contact\ReceiveContactMessage;
use App\Enums\ContactTopic;
use App\Http\Requests\ContactMessageRequest;
use App\Services\ContactChannels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Contact page: channels from Settings plus a small message form (stored for the admin inbox).
 * Links elsewhere prefill the subject with ?topic=quote|demo|careers and an optional ?ref= (product, role…).
 */
class ContactController extends Controller
{
    public function show(Request $request, ContactChannels $channels): View
    {
        $topic = ContactTopic::tryFrom((string) $request->query('topic')) ?? ContactTopic::Quote;
        $reference = Str::of((string) $request->query('ref'))->stripTags()->squish()->limit(120, '')->toString();

        return view('contact.show', [
            'channels' => $channels->all(),
            'topic' => $topic,
            'prefill' => $reference !== '' ? $reference.":\n\n" : '',
            'formToken' => ContactMessageRequest::timestampToken(),
        ]);
    }

    public function store(ContactMessageRequest $request, ReceiveContactMessage $receive): RedirectResponse
    {
        // Bots get the same answer as people, so the checks are not easy to probe.
        if ($request->isLikelySpam()) {
            Log::notice('Contact form submission discarded by the spam checks.', ['ip' => $request->ip()]);
        } else {
            $receive->handle($request->validated(), $request->ip(), $request->userAgent());
        }

        return redirect()->route('contact')->with('contact_sent', true);
    }
}
