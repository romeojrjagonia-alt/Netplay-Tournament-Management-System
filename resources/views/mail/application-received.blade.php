<x-mail::message>
# Application received

Thanks for registering for **{{ $application->tournamentCategory->tournament->name }}** ({{ $application->tournamentCategory->category_label }}).

Your application reference code is:

<x-mail::panel>
**{{ $application->reference_code }}**
</x-mail::panel>

Enter this code under "Check Tournament Application Progress" on the NetPlay home page to see whether your application was approved and to pay your registration fee.

<x-mail::button :url="route('home')">
Open NetPlay
</x-mail::button>
</x-mail::message>
