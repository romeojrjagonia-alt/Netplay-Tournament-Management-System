<x-mail::message>
@if ($linkRequest->status === \App\Models\PlayerLinkRequest::APPROVED)
# Your records were linked

Hi {{ $linkRequest->user->name }}, your earlier tournament registrations ({{ $linkRequest->player->name }}) now appear on your NetPlay account, including your match history and level.
@else
# Your link request was not approved

Hi {{ $linkRequest->user->name }}, the organizers did not link the earlier registration under {{ $linkRequest->player->name }} to your account.

@if ($linkRequest->rejection_reason)
<x-mail::panel>
Reason: {{ $linkRequest->rejection_reason }}
</x-mail::panel>
@endif
@endif

<x-mail::button :url="route('dashboard')">
Open NetPlay
</x-mail::button>
</x-mail::message>
