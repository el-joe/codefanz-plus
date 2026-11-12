<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactTopic;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Read-only inbox of contact-form messages (leads.view). Milestone 5 turns these into leads.
 */
class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $topic = $request->validate(['topic' => ['nullable', Rule::enum(ContactTopic::class)]])['topic'] ?? null;

        return view('admin.contact-messages.index', [
            'messages' => ContactMessage::query()
                ->when($topic, fn ($query) => $query->where('topic', $topic))
                ->latest()->latest('id')
                ->paginate(25)->withQueryString(),
            'topic' => $topic,
        ]);
    }

    public function show(ContactMessage $contactMessage): View
    {
        return view('admin.contact-messages.show', ['message' => $contactMessage]);
    }
}
