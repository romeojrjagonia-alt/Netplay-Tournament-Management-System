<x-mail::message>
# Your application was approved

Hi {{ $player->name }}, the organizers verified your application for **{{ $application->tournamentCategory->tournament->name }}** ({{ $application->tournamentCategory->category_label }}).

The next step is to pay the registration fee of **₱{{ number_format((float) $application->tournamentCategory->registration_fee, 2) }}**. Your reference code is:

<x-mail::panel>
**{{ $application->reference_code }}**
</x-mail::panel>

<x-mail::button :url="route('applications.payment', $application->reference_code)">
Pay registration fee
</x-mail::button>
</x-mail::message>
