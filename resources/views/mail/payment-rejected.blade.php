<x-mail::message>
# Please resubmit your payment proof

Hi {{ $player->name }}, we could not verify the proof of payment for **{{ $application->tournamentCategory->tournament->name }}** ({{ $application->tournamentCategory->category_label }}). Please upload a new, clear screenshot of your payment.

<x-mail::panel>
Reference code: **{{ $application->reference_code }}**
</x-mail::panel>

<x-mail::button :url="route('applications.payment', $application->reference_code)">
Resubmit payment
</x-mail::button>
</x-mail::message>
