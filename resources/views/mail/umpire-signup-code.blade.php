<x-mail::message>
# Verify your email

Hi {{ $name }}, enter this code to finish creating your NetPlay umpire account:

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

The code is valid for {{ $validForMinutes }} minutes. If you didn't try to sign up as an umpire, you can ignore this email — no account will be created.
</x-mail::message>
