<x-mail::message>
# New enquiry

**From:** {{ $contactMessage->name }} &lt;{{ $contactMessage->email }}&gt;

@if ($contactMessage->phone)
**Phone:** {{ $contactMessage->phone }}
@endif

**Language:** {{ strtoupper($contactMessage->locale ?? 'en') }}

**Subject:** {{ $contactMessage->subject }}

<x-mail::panel>
{{ $contactMessage->message }}
</x-mail::panel>

<x-mail::button :url="url('/admin/contact-messages/'.$contactMessage->getKey().'/edit')">
Open in the admin
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
