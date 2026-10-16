<x-mail::message>
# Your password was changed

Hi {{ $user->name }}, the password for your NetPlay account was changed on **{{ $changedAt }}**.

If this was you, there is nothing more to do.

If you did not make this change, reset your password right away and make sure you still control this email address.

<x-mail::button :url="route('password.request')">
Reset my password
</x-mail::button>
</x-mail::message>
