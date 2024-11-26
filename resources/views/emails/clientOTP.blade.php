@component('mail::message')
# Verification OTP

Your OTP for verification is: **{{ $otp }}**


Thank you,<br>
{{ config('app.name') }}
@endcomponent