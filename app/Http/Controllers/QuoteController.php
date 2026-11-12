<?php

namespace App\Http\Controllers;

use App\Actions\Leads\CreateLead;
use App\Enums\LeadSource;
use App\Http\Requests\QuoteRequest;
use App\Models\Service;
use App\Models\ServiceTranslation;
use App\Models\Technology;
use App\Services\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Request a Quote: a four-step form (one long form without JavaScript) that creates a CRM lead.
 * CTAs prefill it with ?service=<service slug>, ?topic=… and ?ref=<product, plan…>; UTM tags and the
 * referrer are carried through hidden fields.
 */
class QuoteController extends Controller
{
    public function show(Request $request, Countries $countries): View
    {
        $locale = app()->getLocale();
        $reference = $this->clean($request->query('ref'), 120);

        $selectedServiceId = null;

        if (is_string($request->query('service')) && $request->query('service') !== '') {
            $selectedServiceId = ServiceTranslation::query()
                ->where('slug', $request->query('service'))
                ->whereHas('service', fn ($query) => $query->published())
                ->orderByRaw('locale = ? desc', [$locale])
                ->value('service_id');
        }

        return view('quote.show', [
            'services' => Service::query()->published()->translatedIn()->with('translations')->ordered()->get(),
            'technologies' => Technology::query()->where('is_visible', true)->with('translations')->ordered()->get(),
            'countries' => $countries->options(),
            'selectedServices' => $selectedServiceId ? [$selectedServiceId] : [],
            'reference' => $reference,
            'topic' => $this->clean($request->query('topic'), 40),
            'tracking' => [
                'utm_source' => $this->clean($request->query('utm_source'), 100),
                'utm_medium' => $this->clean($request->query('utm_medium'), 100),
                'utm_campaign' => $this->clean($request->query('utm_campaign'), 100),
                'referrer' => $this->clean($request->headers->get('referer'), 500),
            ],
            'formToken' => QuoteRequest::timestampToken(),
        ]);
    }

    public function store(QuoteRequest $request, CreateLead $createLead): RedirectResponse
    {
        // Bots get the same answer as people, so the checks are not easy to probe.
        if ($request->isLikelySpam()) {
            Log::notice('Quote request discarded by the spam checks.', ['ip' => $request->ip()]);

            return redirect()->route('quote')->with('quote_sent', true);
        }

        $lead = $createLead->handle(
            $request->leadData(),
            LeadSource::QuoteForm,
            attachments: array_values((array) $request->file('attachments', [])),
            sendAutoReply: true,
        );

        return redirect()->route('quote')->with(['quote_sent' => true, 'quote_reference' => $lead->reference]);
    }

    private function clean(mixed $value, int $limit): string
    {
        return is_string($value) ? Str::of($value)->stripTags()->squish()->limit($limit, '')->toString() : '';
    }
}
